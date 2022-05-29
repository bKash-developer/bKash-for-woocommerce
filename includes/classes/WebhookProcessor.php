<?php
/**
 * Webhook Processor
 *
 * @category    Api
 * @package     bkash-for-woocommerce
 * @author      Md. Shahnawaz Ahmed <shahnawaz.ahmed@bkash.com>
 * @copyright   Copyright 2022 bKash Limited. All rights reserved.
 * @license     GNU General Public License version 2 or later; see LICENSE
 * @link        https://bkash.com
 */

namespace bKash\PGW;

use bKash\PGW\Models\Webhook;
use DateTime;
use Exception;
use WC_Logger;

class WebhookProcessor {
	private $payload;
	private $context = array( "source" => BKASH_FW_PLUGIN_SLUG );
	private $log;
	private $canSubscribe;

	private $messageType = "";
	private $signingCertURL;

	public function __construct( WC_Logger $logger = null, bool $canSubscribe = false ) {
		$this->canSubscribe = $canSubscribe;

		// GET THE RAW STREAM OF POST PAYLOAD
		$this->payload = json_decode( file_get_contents( 'php://input' ), false );
		if ( $this->payload ) {
			$this->messageType    = Utils::safeServerValue( "HTTP_X_AMZ_SNS_MESSAGE_TYPE" );
			$this->signingCertURL = $this->payload->SigningCertURL ?? null;
		}
		if ( $logger ) {
			$this->log = $logger;
		}
	}

	/**
	 * @return void
	 */
	final public function processRequest() {
		if ( $this->messageType === 'SubscriptionConfirmation' ) {
			$this->subscribe();
		} elseif ( $this->messageType === 'Notification' ) {
			$this->storeNotification();
		} else {
			$this->writeLog( "No method present to process the webhook" );
		}
	}

	/**
	 * Subscribe a WebhookModule URL
	 * @return void
	 */
	final public function subscribe() {
		if ( $this->canSubscribe ) {
			if ( $this->payload && $this->verifySource() ) {
				if ( isset( $this->payload->SubscribeURL ) ) {
					$this->writeLog( "Subscribing to ==> " . $this->payload->SubscribeURL );
					$subscriptionResponse = wp_remote_get( $this->payload->SubscribeURL );
					if ( is_wp_error( $subscriptionResponse ) ) {
						$this->writeLog( "Error subscribing the URL: " . wp_json_encode( $subscriptionResponse ) );
					} else {
						$subscriptionResponse = wp_remote_retrieve_body( $subscriptionResponse );
						$this->writeLog( $subscriptionResponse );
					}
				} else {
					$this->writeLog( "Could not found subscription URL" );
				}
			} else {
				$this->writeLog( "WebhookModule source can not be verified" );
			}
		} else {
			$this->writeLog( "Subscription to webhook disabled from settings" );
		}
	}

	/**
	 * Supporting Operations
	 * @methods verifySource, validateURL, getContent, getStringToSign, writeLog
	 * @return bool
	 */
	final public function verifySource(): bool {
		if ( $this->signingCertURL ) {
			$isValidURL = $this->validateURL( $this->signingCertURL );
			if ( $isValidURL ) {
				$publicCert = $this->getContent( $this->signingCertURL );
				$this->writeLog( $publicCert );
				$signature = isset( $this->payload->Signature ) ? base64_decode( $this->payload->Signature ) : null;

				$formattedString = $this->getStringToSign( $this->payload );
				if ( $formattedString ) {
					$verify = openssl_verify( $formattedString, $signature, $publicCert, OPENSSL_ALGO_SHA1 );
					$this->writeLog( "Verifying ..." . wp_json_encode( $verify ) );

					return $verify;
				}
			}
		}

		return false;
	}

	/**
	 * @param string $url
	 *
	 * @return bool
	 */
	final public function validateURL( string $url ): bool {
		$defaultHostPattern = '/^sns\.[a-zA-Z\d\-]{3,}\.amazonaws\.com(\.cn)?$/';
		$parsed             = wp_parse_url( $url );

		return ! (
			empty( $parsed['scheme'] ) || empty( $parsed['host'] )
			|| $parsed['scheme'] !== 'https' || substr( $url, - 4 ) !== '.pem'
			|| ! preg_match( $defaultHostPattern, $parsed['host'] )
		);
	}

	/**
	 * @param string $url
	 *
	 * @return string
	 */
	final public function getContent( string $url ): string {
		$body     = '';
		$response = wp_remote_get( $url );
		if ( is_wp_error( $response ) ) {
			$this->writeLog( "Error in getting content.. " . wp_json_encode( $response ) );
		} else {
			$body = wp_remote_retrieve_body( $response );
		}

		return $body;
	}

	/**
	 * @param mixed $logging_item
	 *
	 * @return void
	 */
	final public function writeLog( $logging_item ) {
		if ( $this->log ) {
			$this->log->debug( $logging_item, $this->context );
		}
	}

	/**
	 * @param $message
	 *
	 * @return string
	 */
	final public function getStringToSign( $message ): string {
		$signAbleKeys = [
			'Message',
			'MessageId',
			'Subject',
			'SubscribeURL',
			'Timestamp',
			'Token',
			'TopicArn',
			'Type'
		];

		$stringToSign = '';

		if ( isset( $message->SignatureVersion ) && $message->SignatureVersion !== '1' ) {
			$errorLog = "The SignatureVersion \"$message->SignatureVersion\" is not supported.";
			$this->writeLog( $errorLog );
		} else {
			foreach ( $signAbleKeys as $key ) {
				if ( isset( $message->$key ) ) {
					if ( is_string( $message->$key ) ) {
						$data = $message->$key;
					} else {
						$data = wp_json_encode( $message->$key );
					}
					$stringToSign .= "$key\n$data\n";
				}
			}
			$this->writeLog( $stringToSign . "\n" );
		}

		return $stringToSign;
	}

	/**
	 * Store WebhookModule notification payload
	 *
	 * @return bool
	 */
	final public function storeNotification(): bool {
		if ( $this->payload && $this->verifySource() ) {
			if ( is_string( $this->payload->Message ) ) {
				$this->payload->Message = json_decode( $this->payload->Message, false );
			}
			$message = $this->payload->Message;
			if ( $message ) {
				// process date format
				try {
					$parseDate = DateTime::createFromFormat( 'YmdHis', $message->dateTime );
				} catch ( Exception $e ) {
					$parseDate = date( 'Y-m-d H:i:s' );
				}


				$webhooks = new Webhook();
				$webhooks->setSender( $message->debitMSISDN ?? '' );
				$webhooks->setReceiver( $message->creditShortCode ?? '' );
				$webhooks->setAmount( isset( $message->amount ) ? (float) $message->amount : '' );
				$webhooks->setTrxId( $message->trxID ?? '' );
				$webhooks->setCurrency( $message->currency ?? '' );
				$webhooks->setDatetime(
					$parseDate->format( "Y-m-d H:i:s" )
				);
				$webhooks->setType( $message->transactionType ?? '' );
				if ( isset( $message->creditOrganizationName ) ) {
					$webhooks->setReceiverName( $message->creditOrganizationName );
				}
				$webhooks->setStatus( $message->transactionStatus ?? '' );
				$isSaved = $webhooks->save();
				$this->writeLog( "Saving webhook payment, " . wp_json_encode( $isSaved ) );
				if ( $isSaved ) {
					$this->writeLog( "Payment added successfully, " . wp_json_encode( $message ) );

					return true;
				}

				$this->writeLog( "Payment can't be added, " . wp_json_encode( $webhooks->errorMessage ) );
			}
		}

		return false;
	}
}
