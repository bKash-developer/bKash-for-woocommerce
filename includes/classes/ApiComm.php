<?php
/**
 * Api Communicator
 *
 * @category    Api
 * @package     bkash-for-woocommerce
 * @author      bKash Developer <developer@bkash.com>
 * @copyright   Copyright 2023 bKash Limited. All rights reserved.
 * @license     GNU General Public License version 2 or later; see LICENSE
 * @link        https://bkash.com
 */

namespace bKash\PGW;

use Exception;
use Throwable;
use bKash\PGW\Log;

class ApiComm {
	public $debug;
	private $integration_product = 'checkout';
	private $sandbox             = false;
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
		$this->integration_product = $this->getOption( 'integration_type', 'tokenized' );
		$this->intent              = $this->getOption( 'intent', 'sale' );

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
		$env = $this->sandbox === 'yes' ? 'sandbox' : 'pay';
		
		$this->constructed_url = 'https://tokenized.' . $env . '.bka.sh/v2/tokenized-checkout/';
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
					$this->token = sanitize_text_field( $response['id_token'] );
					$expiry      = time() + absint( $response['expires_in'] );

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
	 * @see https://developer.bka.sh/docs/grant-token-3
	 */
	final public function getToken(): array {
		$url = $this->constructed_url . 'auth/grant-token';

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

		$log .= 'HEADERS: ' . wp_json_encode( Log::redact_sensitive( $headers ) ) . "\n";
		$log .= 'BODY: ' . wp_json_encode( Log::redact_sensitive( $post_data ) ) . "\n";

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

		$response_body = is_wp_error( $response ) ? $response->get_error_message() : wp_remote_retrieve_body( $response );
		$response_decoded = json_decode( $response_body, true );
		// Redact sensitive data from response body
		$response_copy = $response;
		if ( ! is_wp_error( $response ) && isset( $response['body'] ) ) {
			$decoded_body = json_decode( $response['body'], true );
			if ( is_array( $decoded_body ) ) {
				$response_copy['body'] = wp_json_encode( Log::redact_sensitive( $decoded_body ) );
			}
		}
		$log .= 'RESPONSE: ' . wp_json_encode( $response_copy ) . "\n\n";

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
		$url = $this->constructed_url . 'auth/refresh-token';

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
	 * Create Payment with or without agreement ID
	 *
	 * Use this API to create a payment at bKash end..
	 *
	 * @param array $params
	 *
	 * @return array
	 */
	final public function paymentCreate( array $params, string $mode ): array {
		$body = array(
				'payerReference'          => $params['payerReference'] ?? '',
				'callbackURL'             => $params['callbackURL'] ?? '',
				'amount'                  => $params['amount'] ?? '',
				'currency'                => $params['currency'] ?? '',
				'intent'                  => $params['intent'] ?? '',
				'merchantInvoiceNumber'   => $params['merchantInvoiceNumber'] ?? '',
				'merchantAssociationInfo' => $params['merchantAssociationInfo'] ?? '',
			);

		if($mode === '0011') {
			$api_title = 'Create Payment';
			$url = $this->constructed_url . 'payment/create';
		} elseif($mode === '0001') {
			$api_title = 'Create Payment With Agreement';
			$url = $this->constructed_url . 'payment-with-agreement/create';
			$body['agreementId'] = $params['agreementId'] ?? '';
		} else {
			// invalid mode for create payment
			return array(
				'status_code' => - 1,
				'header'      => array(),
				'response'    => 'Invalid mode for create payment',
			);
		}

		$response = $this->httpRequest( $api_title, $url, $http_status, 'POST', $body, $header );
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
	final public function executePayment( string $payment_id, string $mode, string $agreementId = '' ): array {
		$body = array(
				'paymentId'	=> $payment_id
			);

		if($mode === '0001')
		{
			$url = $this->constructed_url . 'payment-with-agreement/execute';
			$apiTitle = 'Execute Payment With Agreement';
			$body['agreementId'] = $agreementId;
		} elseif($mode === '0011') {
			$url = $this->constructed_url . 'payment/execute';
			$apiTitle = 'Execute Payment';
		}
		else {
			// invalid mode for execute payment
			return array(
				'status_code' => - 1,
				'header'      => array(),
				'response'    => 'Invalid mode for execute payment',
			);
		}

		$response = $this->httpRequest( $apiTitle, $url, $http_status, 'POST', $body, $header );

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
		$url = $this->constructed_url . 'query/payment';

		$body = array(
			'paymentID' => $payment_id,
		);

		$response = $this->httpRequest( 'Tokenization Query Payment', $url, $http_status, 'POST', $body, $header );

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
	final public function capturePayment( string $payment_id, string $mode = '0011', string $agreement_id = '' ): array {
		$body = array( 'paymentId' => $payment_id );

		if ( $mode === '0001' ) {
			$url      = $this->constructed_url . 'payment-with-agreement/capture';
			$apiTitle = 'Capture Payment With Agreement';
			if ( ! empty( $agreement_id ) ) {
				$body['agreementId'] = $agreement_id;
			}
		} elseif ( $mode === '0011' ) {
			$url      = $this->constructed_url . 'payment/capture';
			$apiTitle = 'Capture Payment';
		} else {
			return array(
				'status_code' => -1,
				'header'      => array(),
				'response'    => 'Invalid mode for capture payment',
			);
		}

		$response = $this->httpRequest( $apiTitle, $url, $http_status, 'POST', $body, $header );

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
	 * Void Payment
	 *
	 * For intent authorize only. For voiding an authorized amount on failure to provide service,
	 * one can call this API to return payment amount from bKash to customer bKash account.
	 *
	 * @param string $payment_id
	 *
	 * @return array
	 */
	final public function voidPayment( string $payment_id, string $mode = '0011', string $agreement_id = '' ): array {
		$body = array( 'paymentId' => $payment_id );

		if ( $mode === '0001' ) {
			$url      = $this->constructed_url . 'payment-with-agreement/void';
			$apiTitle = 'Void Payment With Agreement';
			if ( ! empty( $agreement_id ) ) {
				$body['agreementId'] = $agreement_id;
			}
		} elseif ( $mode === '0011' ) {
			$url      = $this->constructed_url . 'payment/void';
			$apiTitle = 'Void Payment';
		} else {
			return array(
				'status_code' => -1,
				'header'      => array(),
				'response'    => 'Invalid mode for void payment',
			);
		}

		$response = $this->httpRequest( $apiTitle, $url, $http_status, 'POST', $body, $header );

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
		$url = $this->constructed_url . 'general/search-transaction';
		$body = ['trxId' => $trx_id];

		$response = $this->httpRequest( 'Tokenized Search Transaction', $url, $http_status, 'POST', $body, $header );
		return array(
			'status_code' => $http_status,
			'header'      => $header,
			'response'    => $response,
		);
	}

	/**
	 * Create Agreement
	 *
	 * Calls the bKash API to create an agreement.
	 * @param array $params ['payerReference', 'callbackURL']
	 * @return array
	 */
	final public function agreementCreate(array $params): array {
		$url = $this->constructed_url . 'agreement/create';
		$body = [
			'payerReference' => $params['payerReference'] ?? '',
			'callbackURL'    => $params['callbackURL'] ?? '',
		];

		$response = $this->httpRequest('Create Agreement', $url, $http_status, 'POST', $body, $header);
		return [
			'status_code' => $http_status,
			'header'      => $header,
			'response'    => $response,
		];
	}

	/**
	 * Execute Agreement
	 *
	 * Calls the bKash API to execute an agreement.
	 * @param string $agreementId
	 * @return array
	 */
	final public function agreementExecute(string $agreementId): array {
		$url = $this->constructed_url . 'agreement/execute';
		$body = [
			'agreementId' => $agreementId,
		];
		$response = $this->httpRequest('Execute Agreement', $url, $http_status, 'POST', $body, $header);
		return [
			'status_code' => $http_status,
			'header'      => $header,
			'response'    => $response,
		];
	}

	/**
	 * Agreement Query
	 *
	 * Get agreement status using bKash agreement ID.
	 *
	 * @param string $agreement_id
	 *
	 * @return array
	 */
	final public function queryAgreement( string $agreement_id ): array {
		$url = $this->constructed_url . 'query/agreement';

		$body = array(
			'agreementId' => $agreement_id,
		);

		$response = $this->httpRequest( 'Agreement Query', $url, $http_status, 'POST', $body, $header );

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
			'agreementId' => $agreement_id,
		);

		$response = $this->httpRequest( 'Agreement Cancel', $url, $http_status, 'POST', $body, $header );

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
		$url = $this->constructed_url . 'refund/payment/transaction';

		$body = array(
			'refundAmount'  => $amount,
			'paymentId' 	=> $paymentID,
			'trxId'     	=> $trxID,
			'sku'       	=> $SKU,
			'reason'    	=> $reason,
		);

		$response = $this->httpRequest( 'Refund Transaction', $url, $http_status, 'POST', $body, $header );

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
		$url = $this->constructed_url . 'refund/payment/status';

		$body = array(
			'paymentId' => $paymentID,
			'trxId'     => $trxID,
		);

		$response = $this->httpRequest( 'Refund Status', $url, $http_status, 'POST', $body, $header );

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
