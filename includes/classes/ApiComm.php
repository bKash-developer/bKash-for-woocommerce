<?php
/**
 * Api Communicator
 *
 * @category    Api
 * @package     bkash-for-woocommerce
 * @author      Md. Shahnawaz Ahmed <shahnawaz.ahmed@bkash.com>
 * @copyright   Copyright 2022 bKash Limited. All rights reserved.
 * @license     GNU General Public License version 2 or later; see LICENSE
 * @link        https://bkash.com
 */

namespace bKash\PGW;

use Exception;
use Throwable;
use UnexpectedValueException;

class ApiComm {
	public $debug;
	private $integration_product = 'checkout';
	private $sandbox             = false;
	private $api_version;
	private $app_key;
	private $app_secret;
	private $username;
	private $password;
	private $constructed_url;
	/**
	 * @var false|mixed|void|null
	 */
	private $token;

	public function __construct() {
		/* Initializing parameters using required fields from calling class */
		$this->initializeParams();

		/* Constructing API URL for later use */
		$this->constructURL();

		/* Initiate Token Generate Process */
		$this->processToken();
	}

	/**
	 * Initialize Properties
	 *
	 * Get all required properties for this class to operate
	 *
	 * @return void
	 */
	final public function initializeParams() {
		$this->integration_product = $this->getOption( 'integration_type', 'checkout' );
		$this->intent              = $this->getOption( 'intent', 'sale' );
		$this->api_version         = $this->getOption( 'bkash_api_version', 'v1.2.0-beta' );

		$this->sandbox    = $this->getOption( 'sandbox', false );
		$this->app_key    = $this->getEnvSpecificOption( 'app_key' );
		$this->app_secret = $this->getEnvSpecificOption( 'app_secret' );
		$this->username   = $this->getEnvSpecificOption( 'username' );
		$this->password   = $this->getEnvSpecificOption( 'password' );
		$this->debug      = $this->getOption( 'debug', 'no' );
	}

	final public function getOption( $key, $default = null ) {
		$settings = get_option( 'woocommerce_' . BKASH_FW_PLUGIN_SLUG . '_settings' );
		if ( ! is_null( $settings ) ) {
			return $settings[ $key ] ?? $default;
		}

		return $default;
	}

	private function getEnvSpecificOption( $key ): string {
		$isProduction = $this->sandbox === 'no';

		return $isProduction ? $this->getOption( $key ) : $this->getOption( 'sandbox_' . $key );
	}

	/**
	 * Construct API Base Path
	 *
	 * Depending on integration product type, URL will vary. Use this constructed URL along with method path
	 *
	 * @return void
	 */
	private function constructURL() {
		// the subdomain for bka.sh
		$url_prefix = $this->integration_product === 'checkout' ? 'checkout' : 'tokenized';
		// integration name after version
		$url_suffix = $this->integration_product === 'checkout' ? 'checkout' : 'tokenized/checkout';
		// set the environment, either Sandbox or Pay  (Production)
		$env = $this->sandbox === 'yes' ? 'sandbox' : 'pay';

		// rest of the part is related with individual api call
		$this->constructed_url = 'https://' . $url_prefix . '.' . $env . '.bka.sh/' . $this->api_version . '/' . $url_suffix . '/';
	}


	/**
	 * Process Token
	 *
	 * Get or Set token from local, if expire then call from bKash API.
	 *
	 * @access private
	 * @return void
	 */
	private function processToken() {
		try {
			$token   = get_option( 'bkash_grant_token' );
			$expiry  = get_option( 'bkash_grant_token_expiry' );
			$product = get_option( 'bkash_integration_product' );

			// if expiry time in seconds is greater than current time
			if ( $this->integration_product === $product && ! is_null( $token ) && ( $expiry - time() > 0 ) ) {
				$this->token = $token;
			} else {
				$this->readTokenFromAPI();
			}
		} catch ( Exception $e ) {
			Log::debug( $e );
			Log::error( 'bKash PGW ERROR: exception generated while processing token, ' . $e->getMessage() );
		}
	}

	private function readTokenFromAPI() {
		if ( empty( $this->app_key ) || empty( $this->app_secret ) ) {
			Log::error( 'App key or secret is not set, required for bKash APIs' );
		} else {
			$get_token = $this->getToken();
			if ( isset( $get_token['status_code'] ) && $get_token['status_code'] === 200 ) {
				$response = json_decode( $get_token['response'], true );
				if ( isset( $response['id_token'] ) ) {
					$this->token = $response['id_token'];
					$expiry      = time() + $response['expires_in'];

					$this->addOrUpdateOption( 'bkash_grant_token', $this->token );
					$this->addOrUpdateOption( 'bkash_grant_token_expiry', $expiry );
					$this->addOrUpdateOption( 'bkash_integration_product', $this->integration_product );
				} else {
					Log::error( 'Cannot read token from server, response ==> ' . wp_json_encode( $get_token ) );
				}
			} else {
				Log::error( 'Cannot get response from get token API, response ==>' . wp_json_encode( $get_token ) );
			}
		}
	}

	/**
	 * Get Grant Token
	 *
	 * This token has to be used as an authentication medium between bKash and this plugin server
	 *
	 * @method $token get id_token as API token, store it in filesystem and use until expire for all api call
	 * @see https://developer.bka.sh/reference#gettokenusingpost
	 */
	final public function getToken(): array {
		$url = $this->constructed_url . 'token/grant';

		$body = array(
			'app_key'    => $this->app_key,
			'app_secret' => $this->app_secret,
		);

		$response = $this->httpRequest( 'Grant Token', $url, $http_status, 'POST', $body, $header, true );

		return array(
			'status_code' => $http_status,
			'header'      => $header,
			'response'    => $response,
		);
	}

	final public function httpRequest(
		$api_title,
		$url,
		&$http_status,
		$method = 'POST',
		$post_data = null,
		&$header = null,
		$grantHeader = false
	): string {
		$log  = "\n======== bKash PGW REQUEST LOG ========== \n\nAPI TITLE: $api_title \n";
		$log .= "REQUEST METHOD: $method \n";
		$log .= "REQUEST URL: $url \n";

		$headers                 = array();
		$headers['Accept']       = 'application/json';
		$headers['Content-Type'] = 'application/json';
		if ( $grantHeader ) {
			$headers['username'] = $this->username;
			$headers['password'] = $this->password;
		} else {
			$headers['authorization'] = $this->token;
			$headers['x-app-key']     = $this->app_key;
		}
		if ( ! is_null( $header ) ) {
			$headers = array_merge( $headers, $header );
		}

		$log .= 'HEADERS: ' . wp_json_encode( $headers ) . "\n";
		$log .= 'BODY: ' . wp_json_encode( $post_data ) . "\n";

		$response = wp_remote_post(
			$url,
			array(
				'method'      => $method,
				'timeout'     => 29,
				'redirection' => 5,
				'httpversion' => '1.0',
				'blocking'    => true,
				'headers'     => $headers,
				'body'        => strtolower( $method ) === 'get' ? $post_data : wp_json_encode( $post_data ),
			)
		);

		$log .= 'RESPONSE: ' . wp_json_encode( $response ) . "\n\n";

		if ( is_wp_error( $response ) ) {
			$http_status = - 1;
			$body        = $response->get_error_message();

			Log::error( 'CURL Error: = ' . $body );
		} else {
			// parsing http status code
			$http_status = wp_remote_retrieve_response_code( $response );

			if ( $http_status === 401 ) {
				$this->readTokenFromAPI();
			}

			$header = wp_remote_retrieve_headers( $response );
			$body   = wp_remote_retrieve_body( $response );
		}

		Log::debug( $log );

		return $body;
	}

	private function addOrUpdateOption( $key, $value ) {
		if ( ! get_option( $key ) ) {
			add_option( $key, $value );
		} else {
			update_option( $key, $value );
		}
	}

	/**
	 * Reset Stored Token
	 * */
	final public function resetToken() {
		delete_option( 'bkash_grant_token' );
		delete_option( 'bkash_grant_token_expiry' );
		delete_option( 'bkash_integration_product' );
	}

	/**
	 * Get Refresh Token
	 *
	 * After the certain expiry time, one can refresh the token to extend its expiry
	 * and get new token, or regenerate using getToken()
	 *
	 * @param string $refresh_token
	 *
	 * @return array
	 */
	final public function getRefreshToken( string $refresh_token ): array {
		$url = $this->constructed_url . 'token/refresh';

		$body = array(
			'app_key'       => $this->app_key,
			'app_secret'    => $this->app_secret,
			'refresh_token' => $refresh_token,
		);

		$response = $this->httpRequest( 'Refresh Token', $url, $http_status, 'POST', $body, $header, true );

		return array(
			'status_code' => $http_status,
			'header'      => $header,
			'response'    => $response,
		);
	}

	/**
	 * Create Payment
	 *
	 * Use this API to create a payment at bKash end. Will work for both tokenized and checkout.
	 *
	 * @param array $params
	 *
	 * @return array
	 */
	final public function paymentCreate( array $params ): array {
		$url = $this->constructed_url . ( $this->integration_product === 'checkout' ? 'payment/create' : 'create' );

		if ( $this->integration_product === 'checkout' ) {
			$body = array(
				'amount'                  => $params['amount'] ?? '',
				'currency'                => $params['currency'] ?? '',
				'intent'                  => $params['intent'] ?? '',
				'merchantInvoiceNumber'   => $params['merchantInvoiceNumber'] ?? '',
				'merchantAssociationInfo' => $params['merchantAssociationInfo'] ?? '',
			);
		} else {
			$body = array(
				'mode'                    => $params['mode'] ?? '',
				'payerReference'          => $params['payerReference'] ?? '',
				'callbackURL'             => $params['callbackURL'] ?? '',
				'agreementID'             => $params['agreementID'] ?? '',
				'amount'                  => $params['amount'] ?? '',
				'currency'                => $params['currency'] ?? '',
				'intent'                  => $params['intent'] ?? '',
				'merchantInvoiceNumber'   => $params['merchantInvoiceNumber'] ?? '',
				'merchantAssociationInfo' => $params['merchantAssociationInfo'] ?? '',
			);
		}

		$response = $this->httpRequest( 'Create Payment', $url, $http_status, 'POST', $body, $header );

		return array(
			'status_code' => $http_status,
			'header'      => $header,
			'response'    => $response,
		);
	}

	/**
	 * Execute Payment
	 *
	 * Confirming a payment via API calls
	 *
	 * @param string $payment_id
	 *
	 * @return array
	 */
	final public function executePayment( string $payment_id ): array {
		return $this->executeCompleteCaptureVoid( $payment_id, 'execute' );
	}

	/**
	 * @param string $payment_id
	 * @param string $type
	 *
	 * @return array
	 */
	final public function executeCompleteCaptureVoid( string $payment_id, string $type ): array {
		switch ( $type ) {
			case 'execute':
				$api_path     = $this->integration_product === 'checkout' ? 'payment/execute' : 'execute';
				$extra_in_url = '';
				break;
			case 'capture':
				$api_path     = $this->integration_product === 'checkout' ? 'payment/capture' : 'payment/confirm';
				$extra_in_url = '/capture';
				break;
			case 'void':
				$api_path     = $this->integration_product === 'checkout' ? 'payment/void' : 'payment/confirm';
				$extra_in_url = '/void';
				break;
			default:
				$api_path     = '';
				$extra_in_url = '';
		}

		if ( $this->integration_product === 'checkout' ) {
			$url      = $this->constructed_url . $api_path . '/' . $payment_id;
			$apiTitle = 'Checkout ' . ucwords( $type ) . ' Payment';
			$body     = null;
		} else {
			$url      = $this->constructed_url . $api_path . $extra_in_url;
			$apiTitle = 'Tokenized ' . ucwords( $type ) . ' Payment';
			$body     = array( 'paymentID' => $payment_id );
		}

		$response = $this->httpRequest(
			$apiTitle,
			$url,
			$http_status,
			'POST',
			$body,
			$header
		);

		// QUERY PAYMENT IN CASE OF ANY NETWORK OR NO RESPONSE OR TIMED OUT ISSUE
		$decoded_response = isset( $response['response'] ) && is_string( $response['response'] ) ?
			json_decode( $response['response'], true ) : array();

		if ( $http_status !== 200 || isset( $decoded_response['message'] ) ) {
			return $this->queryPayment( $payment_id );
		}

		return array(
			'status_code' => $http_status,
			'header'      => $header,
			'response'    => $response,
		);
	}

	/**
	 * Query Payment
	 *
	 * Query a payment using bKash payment ID directly from bKash server. Will work for both Checkout and Tokenized
	 *
	 * @param string $payment_id
	 *
	 * @return array
	 */
	final public function queryPayment( string $payment_id ): array {
		$api_path = $this->integration_product === 'checkout' ? 'payment/query' : 'payment/status';

		if ( $this->integration_product === 'checkout' ) {
			$url = $this->constructed_url . $api_path . '/' . $payment_id;

			$response = $this->httpRequest( 'Checkout Query Payment', $url, $http_status, 'GET', null, $header );
		} else {
			$url = $this->constructed_url . $api_path;

			$body = array(
				'paymentID' => $payment_id,
			);

			$response = $this->httpRequest( 'Tokenization Query Payment', $url, $http_status, 'POST', $body, $header );
		}

		return array(
			'status_code' => $http_status,
			'header'      => $header,
			'response'    => $response,
		);
	}

	/**
	 * Capture Payment
	 *
	 * For intent authorize only. For capturing an authorized amount,
	 * one can call this API to bring payment amount from bKash to merchant wallet.
	 *
	 * @param string $payment_id
	 *
	 * @return array
	 */
	final public function capturePayment( string $payment_id ): array {
		return $this->executeCompleteCaptureVoid( $payment_id, 'capture' );
	}

	/**
	 * Void Payment
	 *
	 * For intent authorize only. For voiding an authorized amount on failure to provide service,
	 * one can call this API to return payment amount from bKash to customer bKash account.
	 *
	 * @param string $payment_id
	 *
	 * @return array
	 */
	final public function voidPayment( string $payment_id ): array {
		return $this->executeCompleteCaptureVoid( $payment_id, 'void' );
	}

	/**
	 * Search Transaction
	 *
	 * Searching a transaction using bKash transaction ID directly from bKash server.
	 * Will work for both Checkout and Tokenized
	 *
	 * @param string $trx_id
	 *
	 * @return array
	 */
	final public function searchTransaction( string $trx_id ): array {
		$api_path = $this->integration_product === 'checkout' ? 'payment/search' : 'general/searchTransaction';

		if ( $this->integration_product === 'checkout' ) {
			$url = $this->constructed_url . $api_path . '/' . $trx_id;

			$response = $this->httpRequest( 'Checkout Search Transaction', $url, $http_status, 'GET', null, $header );
		} else {
			$url = $this->constructed_url . $api_path;

			$body = array(
				'trxID' => $trx_id,
			);

			$response = $this->httpRequest( 'TokenizedSearch Transaction', $url, $http_status, 'POST', $body, $header );
		}

		return array(
			'status_code' => $http_status,
			'header'      => $header,
			'response'    => $response,
		);
	}

	/**
	 * Refund a transaction
	 *
	 * Can be refund a transaction which is no older than 15 days
	 *
	 * @param $amount
	 * @param $paymentID
	 * @param $trxID
	 * @param $SKU
	 * @param $reason
	 *
	 * @return array
	 * @see https://developer.bka.sh/reference#post_checkout-payment-refund
	 */
	final public function refund( $amount, $paymentID, $trxID, $SKU, $reason ): array {
		$url = $this->constructed_url . 'payment/refund';

		$body = array(
			'amount'    => $amount,
			'paymentID' => $paymentID,
			'trxID'     => $trxID,
			'sku'       => $SKU,
			'reason'    => $reason,
		);

		$response = $this->httpRequest( 'Refund', $url, $http_status, 'POST', $body, $header );

		return array(
			'status_code' => $http_status,
			'header'      => $header,
			'response'    => $response,
		);
	}

	/**
	 * Get Status of a Refunded transaction
	 *
	 * get status if the transaction is already refunded otherwise invalid payment id will return
	 *
	 * @param $paymentID
	 * @param $trxID
	 *
	 * @return array
	 */
	final public function refundStatus( $paymentID, $trxID ): array {
		$url = $this->constructed_url . 'payment/refund';

		$body = array(
			'paymentID' => $paymentID,
			'trxID'     => $trxID,
		);

		$response = $this->httpRequest( 'Refund Status', $url, $http_status, 'POST', $body, $header );

		return array(
			'status_code' => $http_status,
			'header'      => $header,
			'response'    => $response,
		);
	}

	/**
	 * Check Merchant Balances
	 *
	 * Query current collection and disbursement balance directly from bKash server. Only for Checkout products
	 *
	 * @method GET
	 * @return array
	 * @throws UnexpectedValueException
	 * @see https://developer.bka.sh/reference#queryorganizationbalanceusingget
	 */
	final public function checkBalances(): array {
		if ( $this->integration_product === 'checkout' ) {
			$url = $this->constructed_url . 'payment/organizationBalance';

			$response = $this->httpRequest( 'Query Organization Balance', $url, $http_status, 'GET', null, $header );

			return array(
				'status_code' => $http_status,
				'header'      => $header,
				'response'    => $response,
			);
		}

		throw  new UnexpectedValueException( 'Query organization balance is only available in Checkout integration' );
	}

	/**
	 * Intra Account Transfer
	 *
	 * To transfer amount from merchant wallet's internal entity - Collection, Disbursement
	 *
	 * @method POST
	 * @param $amount
	 * @param string $transferType
	 *
	 * @return array
	 * @throws UnexpectedValueException
	 * @see https://developer.bka.sh/reference#intraaccounttransferusingpost
	 */
	final public function intraAccountTransfer( $amount, string $transferType ): array {
		if ( $this->integration_product === 'checkout' ) {
			$url = $this->constructed_url . 'payment/intraAccountTransfer';

			$body = array(
				'amount'       => $amount,
				'currency'     => 'BDT',
				'transferType' => $transferType,
			);

			$response = $this->httpRequest( 'Intra Account Transfer', $url, $http_status, 'POST', $body, $header );

			return array(
				'status_code' => $http_status,
				'header'      => $header,
				'response'    => $response,
			);
		}

		throw  new UnexpectedValueException( 'Intra Account Transfer is only available in Checkout integration' );
	}

	/**
	 * B2C Payout - Disbursement
	 *
	 * To send money from merchant account to a bKash personal account, as called Disbursement
	 *
	 * @method POST
	 * @param $amount
	 * @param string $invoiceNumber
	 * @param string $receiver
	 *
	 * @return array
	 * @throws UnexpectedValueException
	 * @see https://developer.bka.sh/reference#b2cpaymentusingpost
	 */
	final public function b2cPayout( $amount, string $invoiceNumber, string $receiver ): array {
		if ( $this->integration_product === 'checkout' ) {
			$url = $this->constructed_url . 'payment/b2cPayment';

			$body = array(
				'amount'                => $amount,
				'currency'              => 'BDT',
				'merchantInvoiceNumber' => $invoiceNumber,
				'receiverMSISDN'        => $receiver,
			);

			$response = $this->httpRequest( 'B2C Payout', $url, $http_status, 'POST', $body, $header );

			return array(
				'status_code' => $http_status,
				'header'      => $header,
				'response'    => $response,
			);
		}

		throw  new UnexpectedValueException( 'B2C Payout is only available in Checkout integration' );
	}

	/**
	 * Agreement Status
	 *
	 * Get agreement status using bKash agreement ID.
	 *
	 * @param string $agreement_id
	 *
	 * @return array
	 */
	final public function agreementStatus( string $agreement_id ): array {
		$url = $this->constructed_url . 'agreement/status';

		$body = array(
			'agreementID' => $agreement_id,
		);

		$response = $this->httpRequest( 'Agreement Status', $url, $http_status, 'POST', $body, $header );

		return array(
			'status_code' => $http_status,
			'header'      => $header,
			'response'    => $response,
		);
	}

	/**
	 * Agreement Cancellation
	 *
	 * Cancel an agreement using bKash agreement ID.
	 *
	 * @param string $agreement_id
	 *
	 * @return array
	 */
	final public function agreementCancel( string $agreement_id ): array {
		$url = $this->constructed_url . 'agreement/cancel';

		$body = array(
			'agreementID' => $agreement_id,
		);

		$response = $this->httpRequest( 'Agreement Cancel', $url, $http_status, 'POST', $body, $header );

		return array(
			'status_code' => $http_status,
			'header'      => $header,
			'response'    => $response,
		);
	}

	final public function prepareResponse( int $status_code, string $response = '', $headers = null ): array {
		/*
		 * Logic: Get the CURL response, header and status code
		 *  Read Status code, if 200, bKash responded or connectivity issue or any fatal error.
		 *  If 200 but fail response, read if there are any error message for checkout integration
		 * and statusCode not 0000 for Tokenized integration.
		 *  If 200 and success response, nothing left, we have the actual successful response.
		 * */
		$data    = array();
		$message = 'Cannot process your request right now, try again';
		if ( $status_code === 200 ) {
			// > SERVER RESPONSE IS OKAY
			try {
				$data = json_decode( $response, true );
			} catch ( Throwable $e ) {
				// nothing to do
			}
			if ( isset( $data['errorMessage'] ) && ! empty( $data['errorMessage'] ) ) {
				$message = $data['errorMessage'];
			} elseif ( isset( $data['statusCode'] ) && $data['statusCode'] !== '0000' ) {
				$message = $data['statusMessage'] ?? '';
			} else {
				$message = '';
			}
		}

		return array(
			'success'     => ( empty( $message ) ),
			'status_code' => $status_code ?? 0,
			'message'     => $message,
			'response'    => $data,
			'headers'     => $headers,
		);
	}
}
