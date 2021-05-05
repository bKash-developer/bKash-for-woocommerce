<?php

namespace bKash\PGW;


use bKash\PGW\Models\Webhooks;

class Webhook {
	private $payload;
	private $context = array( "source" => 'bKash PGW' );
	private $log;
	private $canSubscribe;

	private $messageType = "";
	private $signingCertURL;

	public function __construct( $logger = null, $canSubscribe = false ) {
		$this->canSubscribe = $canSubscribe;

		// GET THE RAW STREAM OF POST PAYLOAD
		$this->payload = json_decode( file_get_contents( 'php://input' ), false );
		if ( $this->payload ) {
			$this->messageType    = isset( $_SERVER['HTTP_X_AMZ_SNS_MESSAGE_TYPE'] ) ? $_SERVER['HTTP_X_AMZ_SNS_MESSAGE_TYPE'] : null;
			$this->signingCertURL = isset( $this->payload->SigningCertURL ) ? $this->payload->SigningCertURL : null;
		}
		if ( $logger ) {
			$this->log = $logger;
		}
	}

	public function processRequest() {

		if ( $this->messageType === 'SubscriptionConfirmation' ) {
			$this->subscribe();
		} else if ( $this->messageType === 'Notification' ) {
			$this->storeNotification( $this->payload->Message );
		} else {
			$this->writeLog("No method present to process the webhook");
		}
	}

	/**
	 * Subscribe a Webhook URL
	 */
	public function subscribe() {
		if ( $this->canSubscribe ) {
			if ( $this->payload && $this->verifySource() ) {

				if ( isset( $this->payload->SubscribeURL ) ) {
					$this->writeLog( "Subscribing to ==> " . $this->payload->SubscribeURL );
					//$subscriptionResponse = wp_remote_get( $this->payload->SubscribeURL );
					//$this->writeLog( $subscriptionResponse );
				} else {
					$this->writeLog( "Could not found subscription URL" );
				}
			} else {
				$this->writeLog( "Webhook source can not be verified" );
			}
		} else {
			$this->writeLog( "Subscription to webhook disabled from settings" );
		}
	}

	/**
	 * Supporting Operations
	 * @methods verifySource, validateURL, getContent, getStringToSign, writeLog
	 */
	public function verifySource() {
		if ( $this->signingCertURL ) {
			$isValidURL = $this->validateURL( $this->signingCertURL );
			if ( $isValidURL ) {
				$publicCert = $this->getContent( $this->signingCertURL );
				$signature  = isset( $this->payload->Signature ) ? base64_decode( $this->payload->Signature ) : null;

				$formattedString = $this->getStringToSign( $this->payload );
				if ( $formattedString ) {
					return openssl_verify( $formattedString, $signature, $publicCert, OPENSSL_ALGO_SHA1 );
				}
			}
		}

		return false;
	}

	public function validateURL( $url ) {
		$defaultHostPattern = '/^sns\.[a-zA-Z0-9\-]{3,}\.amazonaws\.com(\.cn)?$/';
		$parsed             = parse_url( $url );

		return ! (
			empty( $parsed['scheme'] ) || empty( $parsed['host'] )
			|| $parsed['scheme'] !== 'https' || substr( $url, - 4 ) !== '.pem'
			|| ! preg_match( $defaultHostPattern, $parsed['host'] )
		);
	}

	public function getContent( $url ) {
		return wp_remote_get( $url );
	}

	public function getStringToSign( $message ) {
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
			$errorLog = "The SignatureVersion \"{$message->SignatureVersion}\" is not supported.";
			$this->writeLog( $errorLog );
		} else {
			foreach ( $signAbleKeys as $key ) {
				if ( isset( $message->$key ) ) {
					$stringToSign .= "{$key}\n{$message->$key}\n";
				}
			}
			$this->writeLog( $stringToSign . "\n" );
		}

		return $stringToSign;
	}

	public function writeLog( $logging_item ) {
		if ( $this->log ) {
			$this->log->debug( $logging_item, $this->context );
		}
	}

	/**
	 * Store Webhook notification payload
	 *
	 * @param $message
	 *
	 * @return bool
	 */
	public function storeNotification( $message ) {
		if ( $message ) {
			$webhooks = new Webhooks();
			$webhooks->set_sender( isset( $message->debitMSISDN ) ? $message->debitMSISDN : '' );
			$webhooks->set_receiver( isset( $message->creditShortCode ) ? $message->creditShortCode : '' );
			$webhooks->set_amount( isset( $message->amount ) ? (float) $message->amount : '' );
			$webhooks->set_trx_id( isset( $message->trxID ) ? $message->trxID : '' );
			$webhooks->set_currency( isset( $message->currency ) ? $message->currency : '' );
			$webhooks->set_datetime(
				isset( $message->dateTime ) ? date_create_from_format( 'YmdHis', $message->dateTime ) : ''
			);
			$webhooks->set_type( isset( $message->transactionType ) ? $message->transactionType : '' );
			$webhooks->set_receiver_name( isset( $message->creditOrganizationName ) ? $message->creditOrganizationName : '' );
			$webhooks->save();
			if ( $webhooks ) {
				$this->writeLog( "Payment added successfully, " . json_encode( $message ) );

				return true;
			}
		}

		return false;
	}
	

}