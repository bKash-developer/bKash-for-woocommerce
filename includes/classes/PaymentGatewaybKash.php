<?php

namespace bKash\PGW;

use bKash\PGW\Models\Agreement;
use bKash\PGW\Models\Transactions;
use Exception;
use WC_AJAX;
use WC_Logger;
use WC_Order;
use WC_Payment_Gateway;
use WP_Error;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
} // Exit if accessed directly.

define( "BKASH_WC_API", "/wc-api/" );
define( "BKASH_CLOSE_PDIV", "</p></div>" );

/**
 * WooCommerce bKash Payment Gateway.
 *
 * @class   PaymentGatewaybKash
 * @extends WC_Payment_Gateway
 * @version 1.0.0
 * @package bKash\PGW
 * @author  Md. Shahnawaz Ahmed
 */
class PaymentGatewaybKash extends WC_Payment_Gateway {
	public $log;
	public $bKashObj;
	public $refundObj;
	public $refundError;
	private $CALLBACK_URL = "bkash_payment_process";
	private $SUCCESS_CALLBACK_URL = "bkash_payment_success";
	private $FAILURE_CALLBACK_URL = "bkash_payment_failure";
	private $EXECUTE_URL = "bk_execute";
	private $CANCEL_AGREEMENT_URL = "bk_cancel_agreement";
	private $REVIEW_ORDER_URL = "bk_review_order";
	private $WEBHOOK_URL = "bkash_webhook";
	/**
	 * @var false
	 */
	private $credit_fields;
	/**
	 * @var string
	 */
	private $notify_url;
	/**
	 * @var string
	 */
	private $siteUrl;
	/**
	 * @var string
	 */
	private $is_webhook;

	/**
	 * Constructor for the gateway.
	 *
	 * @access public
	 * @return void
	 */
	public function __construct() {
		$this->Initiate();
		$this->Hooks();
	}

	public function Initiate() {
		$this->id                   = 'bkash_pgw';
		$this->icon                 = apply_filters( 'woocommerce_payment_gateway_bkash_icon', plugins_url( '../assets/images/logo.png', __DIR__ ) );
		$this->has_fields           = true;
		$this->credit_fields        = false;
		$this->order_button_text    = __( 'Pay with bKash', 'woocommerce-payment-gateway-bkash' );
		$this->method_title         = __( 'bKash Payment Gateway', 'woocommerce-payment-gateway-bkash' );
		$this->method_description   = __( 'Take payments via bKash PGW.', 'woocommerce-payment-gateway-bkash' );
		$this->notify_url           = WC()->api_request_url( 'WC_Gateway_bKash' );
		$this->siteUrl              = get_site_url();
		$this->supports             = array(
			'products'
		);
		$this->view_transaction_url = '';

		// Load the form fields.
		$this->init_form_fields();

		// Load the settings.
		$this->init_settings();

		// Get setting values.
		$this->enabled          = $this->get_option( 'enabled' );
		$this->title            = $this->get_option( 'title' );
		$this->description      = $this->get_option( 'description' );
		$this->integration_type = $this->get_option( 'integration_type' );
		$this->intent           = $this->get_option( 'intent' );
		$this->api_version      = $this->get_option( 'bkash_api_version' );
		$this->sandbox          = $this->get_option( 'sandbox' );
		$this->app_key          = $this->sandbox == 'no' ? $this->get_option( 'app_key' ) : $this->get_option( 'sandbox_app_key' );
		$this->app_secret       = $this->sandbox == 'no' ? $this->get_option( 'app_secret' ) : $this->get_option( 'sandbox_app_secret' );
		$this->username         = $this->sandbox == 'no' ? $this->get_option( 'username' ) : $this->get_option( 'sandbox_username' );
		$this->password         = $this->sandbox == 'no' ? $this->get_option( 'password' ) : $this->get_option( 'sandbox_password' );
		$this->debug            = $this->get_option( 'debug' );
		$this->enable_b2c       = $this->get_option( 'enable_b2c' );
		// Logs.
		if ( $this->debug == 'yes' ) {
			if ( class_exists( '\\WC_Logger' ) ) {
				$this->log = new WC_Logger();
			} else {
				$this->log = isset( $woocommerce ) ? $woocommerce->logger() : null;
			}
		}
		$this->is_webhook = $this->get_option( 'webhook' );

		$this->init_gateway_sdk();
	}

	/**
	 * Initialise Gateway Settings Form Fields
	 *
	 * The standard gateway options have already been applied.
	 * Change the fields to match what the payment gateway your building requires.
	 *
	 * @access public
	 */
	public function init_form_fields() {
		$this->form_fields = array(
			'enabled'            => array(
				'title'       => __( 'Enable/Disable', 'woocommerce-payment-gateway-bkash' ),
				'label'       => __( 'Enable bKash PGW', 'woocommerce-payment-gateway-bkash' ),
				'type'        => 'checkbox',
				'description' => '',
				'default'     => 'no'
			),
			'title'              => array(
				'title'       => __( 'Title', 'woocommerce-payment-gateway-bkash' ),
				'type'        => 'text',
				'description' => __( 'This controls the title which the user sees during checkout.', 'woocommerce-payment-gateway-bkash' ),
				'default'     => __( 'bKash Payment Gateway', 'woocommerce-payment-gateway-bkash' ),
				'desc_tip'    => true
			),
			'description'        => array(
				'title'       => __( 'Description', 'woocommerce-payment-gateway-bkash' ),
				'type'        => 'text',
				'description' => __( 'This controls the description which the user sees during checkout.', 'woocommerce-payment-gateway-bkash' ),
				'default'     => 'Pay with bKash PGW.',
				'desc_tip'    => true
			),
			'integration_type'   => array(
				'title'       => __( 'Integration Type', 'woocommerce-payment-gateway-bkash' ),
				'type'        => 'select',
				'description' => __( 'Payment will be initiated with selected bKash PGW integration type', 'woocommerce-payment-gateway-bkash' ),
				'options'     => array(
					'checkout'       => 'Checkout',
					'checkout-url'   => 'Checkout URL (Tokenized Non-Agreement)',
					'tokenized'      => 'Tokenized (With Agreement)',
					'tokenized-both' => 'Tokenized (With and without Agreement)'
				),
				'default'     => 'checkout',
				'desc_tip'    => true,
			),
			'intent'             => array(
				'title'       => __( 'Intent', 'woocommerce-payment-gateway-bkash' ),
				'type'        => 'select',
				'description' => __( 'Payment will be initiated with selected bKash PGW integration type', 'woocommerce-payment-gateway-bkash' ),
				'options'     => array(
					'sale'          => 'Sale',
					'authorization' => 'Authorized'
				),
				'default'     => 'checkout',
				'desc_tip'    => true,
			),
			'bkash_api_version'  => array(
				'title'       => __( 'API Version', 'woocommerce-payment-gateway-bkash' ),
				'type'        => 'text',
				'description' => __( 'This api version will be used for calling API to bKash', 'woocommerce-payment-gateway-bkash' ),
				'default'     => 'v1.2.0-beta',
				'desc_tip'    => true,
			),
			'debug'              => array(
				'title'       => __( 'Debug Log', 'woocommerce-payment-gateway-bkash' ),
				'type'        => 'checkbox',
				'label'       => __( 'Enable logging', 'woocommerce-payment-gateway-bkash' ),
				'default'     => 'no',
				'description' => sprintf( __( 'Log bKash PGW events inside <code>%s</code>', 'woocommerce-payment-gateway-bkash' ), wc_get_log_file_path( $this->id ) )
			),
			'enable_b2c'         => array(
				'title'       => __( 'Enable B2C API', 'woocommerce-payment-gateway-bkash' ),
				'type'        => 'checkbox',
				'label'       => __( 'Enable B2C API', 'woocommerce-payment-gateway-bkash' ),
				'default'     => 'no',
				'description' => sprintf( __( 'Enable B2C Disbursement API', 'woocommerce-payment-gateway-bkash' ) )
			),
			'webhook'            => array(
				'title'       => __( 'Webhook', 'woocommerce-payment-gateway-bkash' ),
				'type'        => 'checkbox',
				'label'       => __( 'Enable Webhook listener', 'woocommerce-payment-gateway-bkash' ),
				'default'     => 'no',
				'description' => sprintf( __( 'Share this webhook URL to bKash team - <code>%s</code>', 'woocommerce-payment-gateway-bkash' ), $this->siteUrl . BKASH_WC_API . $this->WEBHOOK_URL )
			),
			'sandbox'            => array(
				'title'       => __( 'Sandbox', 'woocommerce-payment-gateway-bkash' ),
				'label'       => __( 'Enable Sandbox Mode', 'woocommerce-payment-gateway-bkash' ),
				'type'        => 'checkbox',
				'description' => __( 'Place the payment gateway in sandbox mode using sandbox API keys (real payments will not be taken).', 'woocommerce-payment-gateway-bkash' ),
				'default'     => 'yes'
			),
			'sandbox_app_key'    => array(
				'title'       => __( 'Sandbox Application Key', 'woocommerce-payment-gateway-bkash' ),
				'type'        => 'text',
				'description' => __( 'Get your Sandbox App key from your bKash PGW account.', 'woocommerce-payment-gateway-bkash' ),
				'default'     => '',
				'desc_tip'    => true
			),
			'sandbox_app_secret' => array(
				'title'       => __( 'Sandbox Application Secret', 'woocommerce-payment-gateway-bkash' ),
				'type'        => 'password',
				'description' => __( 'Get your Sandbox app secret from your bKash PGW account.', 'woocommerce-payment-gateway-bkash' ),
				'default'     => '',
				'desc_tip'    => true
			),
			'sandbox_username'   => array(
				'title'       => __( 'Sandbox Username', 'woocommerce-payment-gateway-bkash' ),
				'type'        => 'text',
				'description' => __( 'Get your Sandbox username from your bKash PGW account.', 'woocommerce-payment-gateway-bkash' ),
				'default'     => '',
				'desc_tip'    => true
			),
			'sandbox_password'   => array(
				'title'       => __( 'Sandbox Password', 'woocommerce-payment-gateway-bkash' ),
				'type'        => 'password',
				'description' => __( 'Get your Sandbox password from your bKash PGW account.', 'woocommerce-payment-gateway-bkash' ),
				'default'     => '',
				'desc_tip'    => true
			),
			'app_key'            => array(
				'title'       => __( 'Production Application Key', 'woocommerce-payment-gateway-bkash' ),
				'type'        => 'text',
				'description' => __( 'Get your App Key from your bKash PGW account.', 'woocommerce-payment-gateway-bkash' ),
				'default'     => '',
				'desc_tip'    => true
			),
			'app_secret'         => array(
				'title'       => __( 'Production Application Secret Key', 'woocommerce-payment-gateway-bkash' ),
				'type'        => 'password',
				'description' => __( 'Get your App Secret from your bKash PGW account.', 'woocommerce-payment-gateway-bkash' ),
				'default'     => '',
				'desc_tip'    => true
			),
			'username'           => array(
				'title'       => __( 'Production Username', 'woocommerce-payment-gateway-bkash' ),
				'type'        => 'text',
				'description' => __( 'Get your Username from your bKash PGW account.', 'woocommerce-payment-gateway-bkash' ),
				'default'     => '',
				'desc_tip'    => true
			),
			'password'           => array(
				'title'       => __( 'Production Password', 'woocommerce-payment-gateway-bkash' ),
				'type'        => 'password',
				'description' => __( 'Get your password from your bKash PGW account.', 'woocommerce-payment-gateway-bkash' ),
				'default'     => '',
				'desc_tip'    => true
			),
		);
	}

	/**
	 * Init Payment Gateway SDK.
	 *
	 * @access protected
	 * @return void
	 */
	protected function init_gateway_sdk() {
	}

	public function Hooks() {
		// Hooks.
		if ( is_admin() ) {
			add_action( 'admin_notices', array( $this, 'checks' ) );
			add_action( 'admin_notices', array( $this, 'display_flash_notices' ), 12 );

			add_action( 'woocommerce_receipt_' . $this->id, array( $this, 'receipt_page' ) );
			add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array(
				$this,
				'process_admin_options'
			) );
		}
		add_action( 'wp_enqueue_scripts', array( $this, 'payment_scripts' ) );
		add_action( 'woocommerce_thankyou_' . $this->id, array( $this, 'thankyou_page' ) );

		// Customer Emails.
		add_action( 'woocommerce_email_before_order_table', array( $this, 'email_instructions' ), 10, 3 );


		add_action( 'woocommerce_order_status_completed', array(
			__CLASS__,
			'capture_transaction_from_status'
		), 10, 2 );
		add_action( 'woocommerce_order_status_cancelled', array( __CLASS__, 'void_transaction_from_status' ), 10, 2 );

		add_action( 'woocommerce_api_' . $this->CALLBACK_URL, array( $this, 'create_payment_callback_process' ) );
		add_action( 'woocommerce_api_' . $this->SUCCESS_CALLBACK_URL, array( $this, 'payment_success' ) );
		add_action( 'woocommerce_api_' . $this->FAILURE_CALLBACK_URL, array( $this, 'payment_failure' ) );
		add_action( 'woocommerce_api_' . $this->EXECUTE_URL, array( $this, 'create_payment_callback_process' ) );
		add_action( 'woocommerce_api_' . $this->CANCEL_AGREEMENT_URL, array( $this, 'cancel_agreement_api' ) );
		add_action( 'woocommerce_api_' . $this->REVIEW_ORDER_URL, array( $this, 'process_review_order_payment' ) );
		// Webhook
		add_action( 'woocommerce_api_' . $this->WEBHOOK_URL, array( $this, 'webhook' ) );

		// reset token when setting changes
		add_action( 'update_option', function ( $option_name, $old_value, $value ) {

			if ( $option_name === 'woocommerce_bkash_pgw_settings' ) {
				$apiComm = new ApiComm();
				$apiComm->resetToken();
			}
		}, 10, 3 );
	}

	/**
	 *
	 * @param int $order_id
	 * @param WC_Order $order
	 */
	public static function capture_transaction_from_status( $order_id, $order ) {
		$trx            = '';
		$orderDetails   = wc_get_order( $order_id );
		$id             = $orderDetails->get_transaction_id();
		$payment_method = $orderDetails->get_payment_method(); // bkash_pgw

		if ( $payment_method === 'bkash_pgw' ) {
			$trxObj      = new Transactions();
			$transaction = $trxObj->getTransaction( '', $id );
			if ( $transaction ) {
				if ( $transaction->getStatus() === 'Authorized' ) {
					$comm        = new ApiComm();
					$captureCall = $comm->capturePayment( $transaction->getPaymentID() );

					if ( isset( $captureCall['status_code'] ) && $captureCall['status_code'] === 200 ) {
						$captured = isset( $captureCall['response'] ) && is_string( $captureCall['response'] ) ? json_decode( $captureCall['response'], true ) : [];

						if ( $captured ) {
							// Sample payload - array(3) { ["status_code"]=> int(200) ["header"]=> NULL ["response"]=> string(177) "{"completedTime":"2021-02-21T18:46:18:085 GMT+0000","trxID":"8BM304KJ37","transactionStatus":"Completed","amount":"10","currency":"BDT","transferType":"Collection2Disbursement"}" }

							// If any error for tokenized
							if ( isset( $captured['statusMessage'] ) && $captured['statusMessage'] !== 'Successful' ) {
								$trx = $captured['statusMessage'];
							} // If any error for checkout
							else if ( isset( $captured['errorCode'] ) ) {
								$trx = isset( $captured['errorMessage'] ) ? $captured['errorMessage'] : '';
							} else if ( isset( $captured['transactionStatus'] ) && $captured['transactionStatus'] === 'Completed' ) {
								$trx = $captured;

								$updated = $trxObj->update( [ 'status' => 'Completed' ], [ 'trx_id' => $transaction->getTrxID() ] );
								if ( $updated == 0 ) {
									// on update error
									$orderDetails->add_order_note( sprintf( __( 'bKash PGW: Status update failed in DB, ' . $trxObj->errorMessage, 'woocommerce-payment-gateway-bkash' ) ) );
								}

								$orderDetails->add_order_note( sprintf( __( 'bKash PGW: Payment Capture of amount %s - Payment ID: %s', 'woocommerce-payment-gateway-bkash' ), $transaction->getAmount(), $captured['trxID'] ) );
							} else {
								$trx = "Transfer is not possible right now. try again";
							}
						} else {
							$trx = "Cannot parse capture response from API, try again";
						}
					} else {
						$trx = "Cannot capture using bKash server right now, try again";
					}
				} else {
					$trx = "Transaction is not in authorized state, thus ignore, try again";
				}
			} else {
				$trx = "no transaction found with this order, try again";
			}
		} else {
			// payment gateway is not bKash, try again
			$trx = "";
		}

		if ( isset( $trx ) && ! empty( $trx ) && is_string( $trx ) ) {
			// error occurred, show message
			// $orderDetails->update_status('on-hold', $trx, false);
			self::add_flash_notice( __( "Capture Error, " . $trx ), "warning", true );
		} else if ( isset( $trx ) && ! empty( $trx ) && is_array( $trx ) ) {
			// Capture Success
			self::add_flash_notice( __( "Payment has been captured" ), "success", true );
		} else {
			// nothing to do
		}
	}

	/**
	 * Add a flash notice to {prefix}options table until a full page refresh is done
	 *
	 * @param string $notice our notice message
	 * @param string $type This can be "info", "warning", "error" or "success", "warning" as default
	 * @param boolean $dismissible set this to TRUE to add is-dismissible functionality to your notice
	 *
	 * @return void
	 */

	public static function add_flash_notice( $notice = "", $type = "warning", $dismissible = true ) {
		// Here we return the notices saved on our option, if there are not notices, then an empty array is returned
		$notices = get_option( "bKash_flash_notices", array() );

		$dismissible_text = ( $dismissible ) ? "is-dismissible" : "";

		// We add our new notice.
		$notices[] = array(
			"notice"      => $notice,
			"type"        => $type,
			"dismissible" => $dismissible_text
		);

		// Then we update the option with our notices array
		update_option( "bKash_flash_notices", $notices );
	}

	/**
	 *
	 * @param int $order_id
	 * @param WC_Order $order
	 */
	public static function void_transaction_from_status( $order_id, $order ) {
		$trx            = '';
		$orderDetails   = wc_get_order( $order_id );
		$id             = $orderDetails->get_transaction_id();
		$payment_method = $orderDetails->get_payment_method(); // bkash_pgw

		if ( $payment_method === 'bkash_pgw' ) {
			$trxObj      = new Transactions();
			$transaction = $trxObj->getTransaction( '', $id );
			if ( $transaction ) {
				if ( $transaction->getStatus() === 'Authorized' ) {
					$comm        = new ApiComm();
					$captureCall = $comm->voidPayment( $transaction->getPaymentID() );

					if ( isset( $captureCall['status_code'] ) && $captureCall['status_code'] === 200 ) {
						$captured = isset( $captureCall['response'] ) && is_string( $captureCall['response'] ) ? json_decode( $captureCall['response'], true ) : [];

						if ( $captured ) {
							// Sample payload - array(3) { ["status_code"]=> int(200) ["header"]=> NULL ["response"]=> string(177) "{"completedTime":"2021-02-21T18:46:18:085 GMT+0000","trxID":"8BM304KJ37","transactionStatus":"Completed","amount":"10","currency":"BDT","transferType":"Collection2Disbursement"}" }

							// If any error for tokenized
							if ( isset( $captured['statusMessage'] ) && $captured['statusMessage'] !== 'Successful' ) {
								$trx = $captured['statusMessage'];
							} // If any error for checkout
							else if ( isset( $captured['errorCode'] ) ) {
								$trx = isset( $captured['errorMessage'] ) ? $captured['errorMessage'] : '';
							} else if ( isset( $captured['transactionStatus'] ) && $captured['transactionStatus'] === 'Completed' ) {
								$trx = $captured;

								$updated = $trxObj->update( [ 'status' => 'Void' ], [ 'trx_id' => $transaction->getTrxID() ] );
								if ( $updated == 0 ) {
									// on update error
									$orderDetails->add_order_note( sprintf( __( 'bKash PGW: Status update failed in DB, ' . $trxObj->errorMessage, 'woocommerce-payment-gateway-bkash' ) ) );
								}

								$orderDetails->add_order_note( sprintf( __( 'bKash PGW: Payment was updated as Void of amount %s - Payment ID: %s', 'woocommerce-payment-gateway-bkash' ), $transaction->getAmount(), $captured['trxID'] ) );
							} else {
								$trx = "Transfer is not possible right now. try again";
							}
						} else {
							$trx = "Cannot find the transaction in your database, try again";
						}
					} else {
						$trx = "Cannot void using bKash server right now, try again";
					}
				} else {
					$trx = "Transaction is not in authorized state, thus ignore, try again";
				}
			} else {
				$trx = "no transaction found with this order, try again";
			}
		} else {
			$trx = "payment gateway is not bKash, try again";
		}

		if ( isset( $trx ) && ! empty( $trx ) && is_string( $trx ) ) {
			// error occurred, show message
			// $orderDetails->update_status('on-hold', $trx, false);
			self::add_flash_notice( __( "Capture Error, " . $trx ), "warning", true );
		} else if ( isset( $trx ) && ! empty( $trx ) && is_array( $trx ) ) {
			// Capture Success
			self::add_flash_notice( __( "Payment has been captured" ), "success", true );
		} else {
			// nothing to do
		}
	}

	/**
	 * Admin Panel Options
	 * - Options for bits like 'title' and availability on a country-by-country basis
	 *
	 * @access public
	 * @return void
	 */
	public function admin_options() {
		include_once( WC_Gateway_bKash()->plugin_path() . '/includes/classes/Admin/views/admin-options.php' );
	}

	/**
	 * Check if SSL is enabled and notify the user.
	 *
	 * @access public
	 */
	public function checks() {
		if ( $this->enabled === 'no' ) {
			return;
		}

		// PHP Version.
		if ( PHP_VERSION_ID < 50300 ) {
			echo '<div class="error version-error"><p>' . sprintf( __( 'bKash PGW Error: bKash PGW requires PHP 5.3 and above. You are using version %s.', 'woocommerce-payment-gateway-bkash' ), phpversion() ) . BKASH_CLOSE_PDIV;
		} // Check required fields.
		else if ( ! $this->app_key || ! $this->app_secret ) {
			echo '<div class="error app-key-error"><p>' . __( 'bKash PGW Error: Please enter your app keys and secrets', 'woocommerce-payment-gateway-bkash' ) . BKASH_CLOSE_PDIV;
		} else if ( 'BDT' !== get_woocommerce_currency() ) {
			echo '<div class="error currency-error"><p>' . __( 'bKash PGW Error: Only supports BDT as currency', 'woocommerce-payment-gateway-bkash' ) . BKASH_CLOSE_PDIV;
		} // Show message if enabled and FORCE SSL is disabled and WordPress HTTPS plugin is not detected.
		else if ( 'no' == get_option( 'woocommerce_force_ssl_checkout' ) && ! class_exists( 'WordPressHTTPS' ) && ! is_ssl() ) {
			echo '<div class="error ssl-error"><p>' . sprintf( __( 'bKash PGW is enabled, but the <a href="%s">force SSL option</a> is disabled; your checkout may not be secure! Please enable SSL and ensure your server has a valid SSL certificate - bKash PGW will only work in sandbox mode.', 'woocommerce-payment-gateway-bkash' ), admin_url( 'admin.php?page=wc-settings&tab=checkout' ) ) . BKASH_CLOSE_PDIV;
		}

		// APP KEY APP SECRET CHECK
		if ( empty( $this->app_key ) || empty( $this->app_secret ) || empty( $this->username ) || empty( $this->password ) ) {
			$this->app_key_missing_notice();
		}
	}

	/**
	 * WooCommerce Payment Gateway App key missing Notice.
	 *
	 * @access public
	 */
	public function app_key_missing_notice() {
		$notice = '<div class="error woocommerce-message wc-connect"><p>' . sprintf( __( 'Please set bKash PGW credentials for accepting payments!', 'payment-gateway-bkash' ), "Payment Gateway bKash" ) . BKASH_CLOSE_PDIV;
		add_action( 'admin_notices', $notice );
	}

	/**
	 * Payment form on checkout page.
	 *
	 * @access public
	 */
	public function payment_fields() {
		$description = $this->get_description();

		if ( $this->sandbox == 'yes' ) {
			$description .= ' ' . __( ' (IN SANDBOX)' );
		}

		if ( ! empty( $description ) ) {
			echo wpautop( wptexturize( trim( $description ) ) );
		}

		if ( is_user_logged_in() ) {
			$user_id        = get_current_user_id();
			$agreementModel = new Agreement();
			$agreements     = $agreementModel->getAgreements( $user_id );

			// This includes your custom payment fields.
			include_once( WC_Gateway_bKash()->plugin_path() . '/includes/classes/views/html-payment-fields.php' );
		} else {
			if ( $this->integration_type === 'tokenized' ) {
				echo "<p style='color:red'>Please login to complete the payment</p>";
			}
		}
	}

	/**
	 * Outputs scripts used for the payment gateway.
	 *
	 * @access public
	 */
	public function payment_scripts() {
		// we need JavaScript to process a token only on cart/checkout pages, right?
		/*if ( ! is_cart() && ! is_checkout() && ! isset( $_GET['pay_for_order'] ) ) {
			return;
		}*/
		// if our payment gateway is disabled, we do not have to enqueue JS too
		// do not work with bKash PGW without SSL unless your website is in a test mode
		if ( 'no' === $this->enabled || ( $this->sandbox === 'no' && ! is_ssl() ) ) {
			return;
		}

		// no reason to enqueue JavaScript if API keys are not set
		if ( empty( $this->app_key ) || empty( $this->app_secret ) ) {
			return;
		}

		if ( ! is_checkout() || ! $this->is_available() ) {
			return;
		}

		if ( $this->integration_type === 'checkout' ) {
			$bk_script_url = Operations::CheckoutScriptURL( $this->sandbox === 'yes', $this->api_version );

			// and this is our custom JS in your plugin directory that works with token.js
			wp_register_script( 'woocommerce-payment-gateway-bkash', plugins_url( '../../assets/js/checkout.js?' . time(), __FILE__ ), array() );

			// in most payment processors you have to use PUBLIC KEY to obtain a token
			wp_localize_script( 'woocommerce-payment-gateway-bkash', 'bKash_objects', array(
				'apiVersion'           => $this->api_version,
				'sandbox'              => $this->sandbox,
				'submit_order'         => WC_AJAX::get_endpoint( 'checkout' ),
				'ajaxURL'              => admin_url( 'admin-ajax.php' ),
				'wcAjaxURL'            => $this->siteUrl . BKASH_WC_API . $this->EXECUTE_URL,
				'cancelAgreement'      => $this->siteUrl . BKASH_WC_API . $this->CANCEL_AGREEMENT_URL,
				'review_order_payment' => $this->siteUrl . BKASH_WC_API . $this->REVIEW_ORDER_URL,
				'bKashScriptURL'       => $bk_script_url
			) );

			wp_enqueue_script( 'woocommerce-payment-gateway-bkash' );

		} else {
			// and this is our custom JS in your plugin directory that works with token.js
			wp_register_script( 'woocommerce-payment-gateway-bkash', plugins_url( '../../assets/js/tokenized.js?' . time(), __FILE__ ), array() );

			// in most payment processors you have to use PUBLIC KEY to obtain a token
			wp_localize_script( 'woocommerce-payment-gateway-bkash', 'bKash_objects', array(
				'apiVersion'      => $this->api_version,
				'sandbox'         => $this->sandbox,
				'cancelAgreement' => $this->siteUrl . BKASH_WC_API . $this->CANCEL_AGREEMENT_URL
			) );

			wp_enqueue_script( 'woocommerce-payment-gateway-bkash' );
		}

	}

	/**
	 * Check if this gateway is enabled.
	 *
	 * @access public
	 */
	public function is_available() {
		if ( $this->enabled == 'no' || ( ! is_ssl() && 'no' == $this->sandbox ) ) {
			return false;
		}

		if ( ! $this->app_key || ! $this->app_secret || 'BDT' !== get_woocommerce_currency() ) {
			return false;
		}

		return true;
	}

	public function process_review_order_payment() {
		$order_id = sanitize_text_field( $_POST['order_id'] );
		header( 'Content-Type: application/json' );

		if ( $order_id ) {
			echo json_encode( $this->process_payment( $order_id ) );
		} else {
			echo json_encode(array(
				'result' => 'failure',
				'message' => "Order ID is missing"
			));
		}
		die();
	}

	public function process_payment( $order_id ) {
		$cbURL          = get_site_url() . BKASH_WC_API . $this->CALLBACK_URL . '?orderId=' . $order_id;
		$processPayment = new ProcessPayments( $this->integration_type );

		return $processPayment->createPayment( $order_id, $this->intent, $cbURL );
	}

	public function create_payment_callback_process() {
		$order_id = sanitize_text_field( $_REQUEST['orderId'] );

		global $woocommerce;
		//To receive order id
		$order = wc_get_order( $order_id );
		if ( $order ) {

			$cbURL = get_site_url() . BKASH_WC_API . $this->CALLBACK_URL . '?orderId=' . $order_id;

			$process = new ProcessPayments( $this->integration_type );
			$process->executePayment( $this->get_return_url( $order ), $cbURL );
		} else {
			echo json_encode( array(
				'result'  => 'failure',
				'message' => 'Order not found'
			) );
		}
		die();
	}

	public function cancel_agreement_api() {
		$message      = "";
		$agreement_id = sanitize_text_field( $_REQUEST['id'] );

		$agreementModel = new Agreement();
		$agreement      = $agreementModel->getAgreement( $agreement_id );
		$isSameUser     = $agreement && ( (int) $agreement->getUserID() ) === get_current_user_id();
		if ( $isSameUser ) {
			$api            = new ApiComm();
			$cancelUsingAPI = $api->agreementCancel( $agreement_id );

			$decoded_response = isset( $cancelUsingAPI['response'] ) && is_string( $cancelUsingAPI['response'] ) ?
				json_decode( $cancelUsingAPI['response'], true ) : [];
			if ( isset( $decoded_response['agreementStatus'] ) && $decoded_response['agreementStatus'] === 'Cancelled' ) {
				// CANCELED

				$agreementModel->delete( $agreement_id );

				echo json_encode( array(
					'result'  => 'success',
					'message' => 'Token for that agreement has been deleted'
				) );
				die();
			} else if ( isset( $decoded_response['errorCode'] ) ) {
				$message = $decoded_response['errorMessage'] ?? "Please try later";
			} else {
				$message = "Cannot cancel right now. Please try later";
			}
		} else {
			$message = "Agreement not found";
		}

		echo json_encode( array(
			'result'  => 'failure',
			'message' => $message
		) );
		// Return message to customer.
		die();
	}

	public function payment_success() {
		// for later use
	}

	public function payment_failure() {
		// for later use
	}

	/**
	 * Process refunds.
	 * WooCommerce 2.2 or later
	 *
	 * @access public
	 *
	 * @param int $order_id
	 * @param float $amount
	 * @param string $reason
	 *
	 * @return bool|WP_Error
	 * @throws Exception
	 */
	public function process_refund( $order_id, $amount = null, $reason = '' ) {

		$order = wc_get_order( $order_id );
		$id    = $order->get_transaction_id();

		$response = '';

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$trxObject   = new Transactions();
		$transaction = $trxObject->getTransaction( "", $id );
		if ( $transaction ) {
			if ( empty( $transaction->getRefundID() ) ) {
				$refundAmount = $amount ?? $transaction->getAmount();


				$comm = new ApiComm();
				$call = $comm->refund(
					$refundAmount, $transaction->getPaymentID(), $transaction->getTrxID(), $transaction->getOrderID(), $reason ?? 'Refund Purpose'
				);

				if ( isset( $call['status_code'] ) && $call['status_code'] === 200 ) {
					// response sample
					// array(7) { ["completedTime"]=> string(32) "2021-02-21T15:40:17:162 GMT+0000" ["transactionStatus"]=> string(9) "Completed" ["originalTrxID"]=> string(10) "8BI704KGJX" ["refundTrxID"]=> string(10) "8BL204KJ0E" ["amount"]=> string(5) "10.00" ["currency"]=> string(3) "BDT" ["charge"]=> string(4) "0.00" }

					$trx = isset( $call['response'] ) && is_string( $call['response'] ) ? json_decode( $call['response'], true ) : [];


					// If any error for tokenized
					if ( isset( $trx['statusMessage'] ) && $trx['statusMessage'] !== 'Successful' ) {
						$trx = $trx['statusMessage'];
					} // If any error for checkout
					else if ( isset( $trx['errorCode'] ) ) {
						$trx = $trx['errorMessage'] ?? '';
					} else if ( isset( $trx['transactionStatus'] ) && $trx['transactionStatus'] === 'Completed' ) {
						if ( isset( $trx['refundTrxID'] ) && ! empty( $trx['refundTrxID'] ) ) {
							$this->refundObj = $trx; // so that another class can get the information

							wc_create_refund( array(
								'amount'         => $amount,
								'reason'         => $reason,
								'order_id'       => $order_id,
								// 'line_items'     => $line_items,
								'refund_payment' => false
							) );

							$order->add_order_note( sprintf( __( 'bKash PGW: Refunded %s - Refund ID: %s', 'woocommerce-payment-gateway-bkash' ), $refundAmount, $trx['refundTrxID'] ) );


							$transaction->update( [
								'refund_id'     => $trx['refundTrxID'] ?? '',
								'refund_amount' => $trx['amount'] ?? 0
							], [ 'invoice_id' => $transaction->getInvoiceID() ] );

							if ( $this->debug == 'yes' ) {
								$this->log->add( $this->id, 'bKash PGW order #' . $order_id . ' refunded successfully!' );
							}

							return true;

						} else {
							$trx = "Refund was not successful, no refund id found, try again";
						}
					} else {
						$trx = "Refund was not successful, transaction is not in completed state, try again";
					}
				} else {
					$trx = "Cannot refund the transaction using bKash server right now, try again";
				}
			} else {
				$trx = "This transaction already has been refunded, try again";
			}
		} else {
			$trx = "Cannot find the transaction to refund in your database, try again";
		}

		if ( is_string( $trx ) ) {
			$this->refundError = $trx;
			$order->add_order_note( __( 'Error in refunding the order. ' . $trx, 'woocommerce-payment-gateway-bkash' ) );

			if ( $this->debug == 'yes' ) {
				$this->log->add( $this->id, 'Error in refunding the order #' . $order_id . '. bKash PGW response: ' . print_r( $response, true ) );
			}
		}

		return false;
	}

	/**
	 * Query refund.
	 * WooCommerce 2.2 or later
	 *
	 * @access public
	 *
	 * @param int $order_id
	 *
	 * @return mixed
	 * @throws Exception
	 */
	public function query_refund( $order_id ) {

		$order = wc_get_order( $order_id );
		$id    = $order->get_transaction_id();

		$response = '';

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$trxObject   = new Transactions();
		$transaction = $trxObject->getTransaction( "", $id );
		if ( $transaction ) {
			if ( ! empty( $transaction->getRefundID() ) ) {
				$comm = new ApiComm();
				$call = $comm->refund(
					null, $transaction->getPaymentID(), $transaction->getTrxID(), null, null
				);

				if ( isset( $call['status_code'] ) && $call['status_code'] === 200 ) {
					return isset( $call['response'] ) && is_string( $call['response'] ) ? json_decode( $call['response'], true ) : [];
				}

				$trx = "Cannot check refund status using bKash server right now, try again";
			} else {
				$trx = "This transaction is not refunded yet, try again";
			}
		} else {
			$trx = "Cannot find the transaction to query in your database, try again";
		}

		return $trx;
	}

	/**
	 * Capture the provided amount.
	 *
	 * @param float $amount
	 * @param WC_Order $order
	 *
	 * @return bool|WP_Error
	 */
	public function capture_charge( $amount, $order ) {
		return new WP_Error( 'capture-error', sprintf( __( 'There was an error capturing the charge. Reason: %1$s', 'woocommerce-payment-gateway-bkash' ) ) );
	}

	/**
	 *
	 * @param WC_Order $order
	 *
	 * @return bool|WP_Error
	 */
	public function void_charge( $order ) {
		$id = $order->get_transaction_id();
		try {
			$response = $this->gateway->transaction()->void( $id );
			if ( $response->success ) {
				$this->save_order_meta( $response->transaction, $order );
				$order->update_status( 'cancelled' );
				$order->add_order_note( sprintf( __( 'Transaction %1$s has been voided in Braintree.', 'woo-payment-gateway' ), $id ) );

				return true;
			} else {
				return new WP_Error( 'capture-error', sprintf( __( 'There was an error voiding the transaction. Reason: %1$s', 'woo-payment-gateway' ), wc_braintree_errors_from_object( $response ) ) );
			}
		} catch ( Exception $e ) {
			return new WP_Error( 'capture-error', sprintf( __( 'There was an error voiding the transaction. Reason: %1$s', 'woo-payment-gateway' ), wc_braintree_errors_from_object( $e ) ) );
		}
	}


	/**
	 * Webhook Integration
	 *
	 * @return void
	 */
	public function webhook() {

		if ( isset( $this->is_webhook ) && $this->is_webhook === 'yes' ) {
			$webhook = new Webhook( wc_get_logger(), true );
			$webhook->processRequest();
		} else {
			$this->log->add( $this->id, 'Webhook is not enabled in settings' );
		}

		$payload = (array) json_decode( file_get_contents( 'php://input' ), true );
		$this->log->add( $this->id, 'WEBHOOK => BODY: ' . print_r( $payload, true ) );

		die();
	}

	/**
	 * Output for the order received page.
	 *
	 * @access public
	 * @return void
	 */
	public function receipt_page( $order ) {
		echo '<p>' . __( 'Thank you - your order is now pending payment.', 'woocommerce-payment-gateway-bkash' ) . '</p>';
	}

	/**
	 * Output for the order received page.
	 *
	 * @access public
	 *
	 * @param $order_id
	 */
	public function thankyou_page( $order_id ) {
		$this->extra_details( $order_id );
	}


	/**
	 * Gets the extra details you set here to be
	 * displayed on the 'Thank you' page.
	 *
	 * @access private
	 */
	private function extra_details( $order_id = '' ) {
		$order = wc_get_order( $order_id );
		$id    = $order->get_transaction_id();

		echo '<h2>' . __( 'Payment Details', 'woocommerce-payment-gateway-bkash' ) . '</h2>' . PHP_EOL;

		$trxObj = new Transactions();
		$trx    = $trxObj->getTransaction( '', $id );
		if ( $trx ) {
			include_once "Admin/pages/extra_details.php";
		}
	}

	/**
	 * Add content to the WC emails.
	 *
	 * @access public
	 *
	 * @param WC_Order $order
	 * @param bool $sent_to_admin
	 * @param bool $plain_text
	 */
	public function email_instructions( $order, $sent_to_admin, $plain_text = false ) {
		if ( ! $sent_to_admin && $this->id === $order->get_payment_method() && $order->has_status( 'on-hold' ) ) {
			$this->extra_details( $order->get_id() );
		}
	}

	/**
	 * Function executed when the 'admin_notices' action is called, here we check if there are notices on
	 * our database and display them, after that, we remove the option to prevent notices being displayed forever.
	 * @return void
	 */

	public function display_flash_notices() {
		$notices = get_option( "bKash_flash_notices", array() );

		// Iterate through our notices to be displayed and print them.
		foreach ( $notices as $notice ) {
			printf( '<div class="notice notice-%1$s %2$s"><p>%3$s</p></div>',
				esc_attr($notice['type']),
				$notice['dismissible'],
				esc_html($notice['notice'])
			);
		}

		// Now we reset our options to prevent notices being displayed forever.
		if ( ! empty( $notices ) ) {
			delete_option( "bKash_flash_notices" );
		}
	}

	/**
	 * Get the transaction URL.
	 *
	 *
	 * @param WC_Order $order
	 *
	 * @return string
	 */
	public function get_transaction_url( $order ) {
		return parent::get_transaction_url( $order );
	}

} // end class.