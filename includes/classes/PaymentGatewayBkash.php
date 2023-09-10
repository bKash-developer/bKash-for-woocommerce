<?php
/**
 * Payment Gateway bKash
 *
 * @category    Payment
 * @package     bkash-for-woocommerce
 * @author      bKash Developer <developer@bkash.com>
 * @copyright   Copyright 2023 bKash Limited. All rights reserved.
 * @license     GNU General Public License version 2 or later; see LICENSE
 * @link        https://bkash.com
 */

namespace bKash\PGW;

use bKash\PGW\Models\Agreement;
use bKash\PGW\Models\Transaction;
use Exception;
use WC_AJAX;
use WC_Logger;
use WC_Order;
use WC_Payment_Gateway;
use WP_Error;

/**
 * WooCommerce bKash Payment Gateway.
 *
 * @class   PaymentGatewayBkash
 * @extends WC_Payment_Gateway
 * @version 1.0.0
 * @package bKash\PGW
 * @author  Md. Shahnawaz Ahmed
 */
class PaymentGatewayBkash extends WC_Payment_Gateway {
	public $log;
	public $refundObj;
	public $refundError;
	private $CALLBACK_URL         = 'bkash_payment_process';
	private $SUCCESS_CALLBACK_URL = 'bkash_payment_success';
	private $FAILURE_CALLBACK_URL = 'bkash_payment_failure';
	private $EXECUTE_URL          = 'bk_execute';
	private $PAYMENT_CANCEL_URL   = 'bk_cancel';
	private $CANCEL_AGREEMENT_URL = 'bk_cancel_agreement';
	private $REVIEW_ORDER_URL     = 'bk_review_order';
	private $WEBHOOK_URL          = 'bkash_webhook';
	/**
	 * @var string|null
	 */
	private $siteUrl;
	/**
	 * @var string|null
	 */
	private $is_webhook;
	private $sandbox;
	/**
	 * @var string|null
	 */
	private $debug;
	/**
	 * @var string|null
	 */
	private $password;
	/**
	 * @var string|null
	 */
	private $username;
	/**
	 * @var string|null
	 */
	private $app_secret;
	/**
	 * @var string|null
	 */
	private $app_key;
	/**
	 * @var string|null
	 */
	private $api_version;
	/**
	 * @var string|null
	 */
	private $intent;
	/**
	 * @var string|null
	 */
	private $integration_type;
	/**
	 * @var string|null
	 */
	private $enable_b2c;

	/**
	 * Constructor for the gateway.
	 *
	 * @access public
	 * @return void
	 */
	public function __construct() {
		$this->initiate();
		$this->hooks();
	}

	/**
	 * @return void
	 */
	final public function initiate() {
		$this->id                   = BKASH_FW_PLUGIN_SLUG;
		$this->icon                 = apply_filters(
			'woocommerce_payment_gateway_bkash_icon',
			plugins_url( '../assets/images/logo.png', __DIR__ )
		);
		$this->has_fields           = true;
		$this->order_button_text    = 'Pay with bKash';
		$this->method_title         = 'bKash Payment Gateway';
		$this->method_description   = 'Take payments via bKash PGW.';
		$this->siteUrl              = get_site_url();
		$this->supports             = array(
			'products',
			'refunds',
		);
		$this->view_transaction_url = '';

		// Load the form fields.
		$this->initFormFields();

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
		$this->app_key          = $this->getEnvSpecificOption( 'app_key' );
		$this->app_secret       = $this->getEnvSpecificOption( 'app_secret' );
		$this->username         = $this->getEnvSpecificOption( 'username' );
		$this->password         = $this->getEnvSpecificOption( 'password' );
		$this->debug            = $this->get_option( 'debug' );
		$this->enable_b2c       = $this->get_option( 'enable_b2c' );
		// Logs.
		if ( $this->debug === 'yes' ) {
			if ( class_exists( WC_Logger::class ) ) {
				$this->log = new WC_Logger();
			} else {
				global $woocommerce;
				$this->log = isset( $woocommerce ) ? $woocommerce->logger() : null;
			}
		}
		$this->is_webhook = $this->get_option( 'webhook' );
	}

	/**
	 * Initialise Gateway Settings Form Fields
	 *
	 * The standard gateway options have already been applied.
	 * Change the fields to match what the payment gateway your building requires.
	 *
	 * @access public
	 */
	final public function initFormFields() {
		$this->form_fields = array(
			'enabled'            => array(
				'title'       => 'Enable/Disable',
				'label'       => 'Enable bKash PGW',
				'type'        => 'checkbox',
				'description' => '',
				'default'     => 'no',
			),
			'title'              => array(
				'title'       => 'Title',
				'type'        => 'text',
				'description' => 'This controls the title which the user sees during checkout.',
				'default'     => 'bKash Payment Gateway',
				'desc_tip'    => true,
			),
			'description'        => array(
				'title'       => 'Description',
				'type'        => 'text',
				'description' => 'This controls the description which the user sees during checkout.',
				'default'     => 'Pay with bKash PGW.',
				'desc_tip'    => true,
			),
			'integration_type'   => array(
				'title'       => 'Integration Type',
				'type'        => 'select',
				'description' => 'Payment will be initiated with selected bKash PGW integration type',
				'options'     => array(
					'checkout'       => 'Checkout',
					'checkout-url'   => 'Checkout URL (Tokenized Non-Agreement)',
					'tokenized'      => 'Tokenized (With Agreement)',
					'tokenized-both' => 'Tokenized (With and without Agreement)',
				),
				'default'     => 'checkout',
				'desc_tip'    => true,
			),
			'intent'             => array(
				'title'       => 'Intent',
				'type'        => 'select',
				'description' => 'Payment will be initiated with selected bKash PGW integration type',
				'options'     => array(
					'sale'          => 'Sale',
					'authorization' => 'Authorized',
				),
				'default'     => 'checkout',
				'desc_tip'    => true,
			),
			'bkash_api_version'  => array(
				'title'       => 'API Version',
				'type'        => 'text',
				'description' => 'This api version will be used for calling API to bKash',
				'default'     => 'v1.2.0-beta',
				'desc_tip'    => true,
			),
			'debug'              => array(
				'title'       => 'Debug Log',
				'type'        => 'checkbox',
				'label'       => 'Enable logging',
				'default'     => 'no',
				'description' => sprintf(
					'Log bKash PGW events inside <code>%s</code>',
					esc_html( wc_get_log_file_path( $this->id ) )
				),
			),
			'enable_b2c'         => array(
				'title'       => 'Enable B2C API',
				'type'        => 'checkbox',
				'label'       => 'Enable B2C API',
				'default'     => 'no',
				'description' => 'Enable B2C Disbursement API',
			),
			'webhook'            => array(
				'title'       => 'Webhook',
				'type'        => 'checkbox',
				'label'       => 'Enable Webhook listener',
				'default'     => 'no',
				'description' => sprintf(
					'Share this webhook URL to bKash team - <code>%s</code>',
					esc_url( $this->siteUrl . BKASH_FW_WC_API . $this->WEBHOOK_URL )
				),
			),
			'sandbox'            => array(
				'title'       => 'Sandbox',
				'label'       => 'Enable Sandbox Mode',
				'type'        => 'checkbox',
				'description' => 'If Enabled, Sandbox mode will be applied (real payments will not be taken).',
				'default'     => 'yes',
			),
			'sandbox_app_key'    => array(
				'title'       => 'Sandbox Application Key',
				'type'        => 'text',
				'description' => 'Get your Sandbox App key from your bKash PGW account.',
				'default'     => '',
				'desc_tip'    => true,
			),
			'sandbox_app_secret' => array(
				'title'       => 'Sandbox Application Secret',
				'type'        => 'password',
				'description' => 'Get your Sandbox app secret from your bKash PGW account.',
				'default'     => '',
				'desc_tip'    => true,
			),
			'sandbox_username'   => array(
				'title'       => 'Sandbox Username',
				'type'        => 'text',
				'description' => 'Get your Sandbox username from your bKash PGW account.',
				'default'     => '',
				'desc_tip'    => true,
			),
			'sandbox_password'   => array(
				'title'       => 'Sandbox Password',
				'type'        => 'password',
				'description' => 'Get your Sandbox password from your bKash PGW account.',
				'default'     => '',
				'desc_tip'    => true,
			),
			'app_key'            => array(
				'title'       => 'Production Application Key',
				'type'        => 'text',
				'description' => 'Get your App Key from your bKash PGW account.',
				'default'     => '',
				'desc_tip'    => true,
			),
			'app_secret'         => array(
				'title'       => 'Production Application Secret Key',
				'type'        => 'password',
				'description' => 'Get your App Secret from your bKash PGW account.',
				'default'     => '',
				'desc_tip'    => true,
			),
			'username'           => array(
				'title'       => 'Production Username',
				'type'        => 'text',
				'description' => 'Get your Username from your bKash PGW account.',
				'default'     => '',
				'desc_tip'    => true,
			),
			'password'           => array(
				'title'       => 'Production Password',
				'type'        => 'password',
				'description' => 'Get your password from your bKash PGW account.',
				'default'     => '',
				'desc_tip'    => true,
			),
		);
	}

	private function getEnvSpecificOption( string $key ): string {
		$isProduction = $this->sandbox === 'no';

		return $isProduction ? $this->get_option( $key ) : $this->get_option( 'sandbox_' . $key );
	}

	/**
	 * @return void
	 */
	final public function hooks() {
		// Hooks.
		if ( is_admin() ) {
			add_action( 'admin_notices', array( $this, 'checks' ) );
			add_action( 'admin_notices', array( $this, 'displayFlashNotices' ), 12 );

			add_action( 'woocommerce_receipt_' . $this->id, array( $this, 'receiptPage' ) );
			add_action(
				'woocommerce_update_options_payment_gateways_' . $this->id,
				array(
					$this,
					'process_admin_options',
				)
			);
		}
		add_action( 'wp_enqueue_scripts', array( $this, 'paymentScripts' ) );
		add_action( 'woocommerce_thankyou_' . $this->id, array( $this, 'thankYouPage' ) );

		// Customer Emails.
		add_action( 'woocommerce_email_before_order_table', array( $this, 'emailInstructions' ), 10, 3 );

		add_action(
			'woocommerce_order_status_completed',
			array(
				__CLASS__,
				'captureTransactionFromStatus',
			),
			10,
			2
		);
		add_action( 'woocommerce_order_status_cancelled', array( __CLASS__, 'voidTransactionOnCanceled' ), 10, 2 );

		add_action( 'woocommerce_api_' . $this->CALLBACK_URL, array( $this, 'createPaymentCallbackProcess' ) );
		add_action( 'woocommerce_api_' . $this->SUCCESS_CALLBACK_URL, array( $this, 'paymentSuccess' ) );
		add_action( 'woocommerce_api_' . $this->FAILURE_CALLBACK_URL, array( $this, 'paymentFailure' ) );
		add_action( 'woocommerce_api_' . $this->EXECUTE_URL, array( $this, 'createPaymentCallbackProcess' ) );
		add_action( 'woocommerce_api_' . $this->PAYMENT_CANCEL_URL, array( $this, 'cancelPaymentProcess' ) );
		add_action( 'woocommerce_api_' . $this->CANCEL_AGREEMENT_URL, array( $this, 'cancelAgreementApi' ) );
		add_action( 'woocommerce_api_' . $this->REVIEW_ORDER_URL, array( $this, 'processReviewOrderPayment' ) );
		// WebhookModule
		add_action( 'woocommerce_api_' . $this->WEBHOOK_URL, array( $this, 'webhook' ) );

		// reset token when setting changes
		add_action( 'update_option', array( $this, 'onUpdateResetToken' ), 10, 3 );
	}

	/**
	 *
	 * @param int      $order_id
	 * @param WC_Order $order
	 */
	public static function captureTransactionFromStatus( int $order_id, WC_Order $order ) {
		$orderDetails   = wc_get_order( $order_id );
		$id             = $orderDetails->get_transaction_id();
		$payment_method = $orderDetails->get_payment_method();

		if ( $payment_method === BKASH_FW_PLUGIN_SLUG ) {
			$trxObj      = new Transaction();
			$transaction = $trxObj->getTransaction( '', $id );
			if ( $transaction ) {
				if ( $transaction->getStatus() === 'Authorized' ) {
					$comm        = new ApiComm();
					$captureCall = $comm->capturePayment( $transaction->getPaymentID() );

					if ( isset( $captureCall['status_code'] ) && $captureCall['status_code'] === 200 ) {
						$captured = array();
						if ( isset( $captureCall['response'] ) && is_string( $captureCall['response'] ) ) {
							$captured = json_decode( $captureCall['response'], true );
						}
						if ( $captured ) {
							// Sample payload - array(3)
							// {
							// ["status_code"]=> int(200)
							// ["header"]=> NULL
							// ["response"]=> string(177) "{"completedTime":"2021-02-21T18:46:18:085 GMT+0000",
							// "trxID":"8BM304KJ37","transactionStatus":"Completed","amount":"10",
							// "currency":"BDT","transferType":"Collection2Disbursement"}"
							// }

							// If any error for tokenized
							if ( isset( $captured['statusMessage'] ) && $captured['statusMessage'] !== 'Successful' ) {
								$trx = $captured['statusMessage'];
							} elseif ( isset( $captured['errorCode'] ) ) { // If any error for checkout
								$trx = $captured['errorMessage'] ?? '';
							} elseif ( isset( $captured['transactionStatus'] ) && $captured['transactionStatus'] === BKASH_FW_COMPLETED_STATUS
							) {
								$trx = $captured;

								$updated = $trxObj->update(
									array( 'status' => BKASH_FW_COMPLETED_STATUS ),
									array( 'trx_id' => $transaction->getTrxID() )
								);
								if ( ! $updated ) {
									// on update error
									$orderDetails->add_order_note(
										sprintf(
											'bKash PGW: Status update failed in DB, %s',
											$trxObj->errorMessage
										)
									);
								}

								$orderDetails->add_order_note(
									sprintf(
										'bKash PGW: Payment Capture of amount %s - Payment ID: %s',
										$transaction->getAmount(),
										$captured['trxID']
									)
								);
							} else {
								$trx = 'Transfer is not possible right now. try again';
							}
						} else {
							$trx = 'Cannot parse capture response from API, try again';
						}
					} else {
						$trx = 'Cannot capture using bKash server right now, try again';
					}
				} else {
					$trx = 'Transaction is not in authorized state, thus ignore, try again';
				}
			} else {
				$trx = 'no transaction found with this order, try again';
			}
		} else {
			// payment gateway is not bKash, try again
			$trx = '';
		}

		if ( isset( $trx ) && ! empty( $trx ) ) {
			if ( is_string( $trx ) ) {
				// error occurred, show message
				// $orderDetails->update_status('on-hold', $trx, false);
				self::addFlashNotice( 'Capture Error, ' . $trx );
			} elseif ( is_array( $trx ) ) {
				// Capture Success
				self::addFlashNotice( 'Payment has been captured', 'success' );
			}
		}
	}

	/**
	 * Add a flash notice to {prefix}options table until a full page refresh is done
	 *
	 * @param string $notice our notice message
	 * @param string $type This can be "info", "warning", "error" or "success", "warning" as default
	 * @param bool   $dismissible set this to TRUE to add is-dismissible functionality to your notice
	 *
	 * @return void
	 */

	public static function addFlashNotice( $notice = '', $type = 'warning', $dismissible = true ) {
		// Here we return the notices saved on our option, if there are not notices, then an empty array is returned
		$notices = get_option( 'bKash_flash_notices', array() );

		$dismissible_text = ( $dismissible ) ? 'is-dismissible' : '';

		// We add our new notice.
		$notices[] = array(
			'notice'      => $notice,
			'type'        => $type,
			'dismissible' => $dismissible_text,
		);

		// Then we update the option with our notices array
		update_option( 'bKash_flash_notices', $notices );
	}

	/**
	 *
	 * @param int      $order_id
	 * @param WC_Order $order
	 */
	public static function voidTransactionOnCanceled( $order_id, $order ) {
		$trx            = '';
		$orderDetails   = $order;
		$trxId          = $orderDetails->get_transaction_id();
		$payment_method = $orderDetails->get_payment_method();

		if ( $payment_method === BKASH_FW_PLUGIN_SLUG ) {
			$trxObj      = new Transaction();
			$transaction = $trxObj->getTransaction( '', $trxId );
			if ( $transaction ) {
				if ( $transaction->getStatus() === 'Authorized' ) {
					$comm      = new ApiComm();
					$void_call = $comm->voidPayment( $transaction->getPaymentID() );

					if ( isset( $void_call['status_code'] ) && $void_call['status_code'] === 200 ) {
						$voided = array();
						if ( isset( $void_call['response'] ) && is_string( $void_call['response'] ) ) {
							$voided = json_decode( $void_call['response'], true );
						}

						if ( $voided ) {
							// Sample payload - array(3) {
							// ["status_code"]=> int(200)
							// ["header"]=> NULL
							// ["response"]=> string(177) "{"completedTime":"2021-02-21T18:46:18:085 GMT+0000",
							// "trxID":"8BM304KJ37","transactionStatus":"Completed","amount":"10",
							// "currency":"BDT","transferType":"Collection2Disbursement"}" }

							// If any error for tokenized
							if ( isset( $voided['statusMessage'] ) && $voided['statusMessage'] !== 'Successful' ) {
								$trx = $voided['statusMessage'];
							} elseif ( isset( $voided['errorCode'] ) ) { // If any error for checkout
								$trx = $voided['errorMessage'] ?? '';
							} elseif ( isset( $voided['transactionStatus'] ) && $voided['transactionStatus'] === BKASH_FW_CANCELLED_STATUS
							) {
								$trx = $voided;

								$updated = $trxObj->update(
									array( 'status' => BKASH_FW_CANCELLED_STATUS ),
									array( 'trx_id' => $transaction->getTrxID() )
								);
								if ( ! $updated ) {
									// on update error
									$orderDetails->add_order_note(
										'bKash PGW: Status update failed in DB, ' . $trxObj->errorMessage
									);
								}

								$orderDetails->add_order_note(
									sprintf(
										'bKash PGW: Payment was updated as Void of amount %s - Payment ID: %s',
										$transaction->getAmount(),
										$voided['trxID']
									)
								);
							} else {
								$trx = 'Transfer is not possible right now. try again';
							}
						} else {
							$trx = 'Cannot find the transaction in your database, try again';
						}
					} else {
						$trx = 'Cannot void using bKash server right now, try again';
					}
				} else {
					$trx = 'Transaction is not in authorized state, thus ignore, try again';
				}
			}
		}

		if ( isset( $trx ) && ! empty( $trx ) ) {
			if ( is_string( $trx ) ) {
				self::addFlashNotice( 'Void Error, ' . $trx );
			} elseif ( is_array( $trx ) ) {
				// Void Success
				self::addFlashNotice( 'Payment has been voided', 'success' );
			}
		}
	}

	/**
	 * @param string    $option_name
	 * @param $old_value
	 * @param $value
	 *
	 * @return void
	 */
	final public function onUpdateResetToken( string $option_name, $old_value, $value ) {
		if ( $option_name === 'woocommerce_' . BKASH_FW_PLUGIN_SLUG . '_settings' ) {
			$apiComm = new ApiComm();
			$apiComm->resetToken();
		}
	}

	/**
	 * Admin Panel Options
	 * - Options for bits like 'title' and availability on a country-by-country basis
	 *
	 * @access public
	 * @return void
	 */
	final public function admin_options() {
		include_once WooCommerceBkashPgw()->pluginPath() . '/includes/classes/Admin/views/admin-options.php';
	}

	/**
	 * Check if SSL is enabled and notify the user.
	 *
	 * @access public
	 */
	final public function checks() {
		if ( $this->enabled === 'no' ) {
			return;
		}

		// PHP Version.
		if ( PHP_VERSION_ID < 50300 ) {
			echo wp_kses_post(
				'<div class="error version-error"><p>bKash PGW Error: ' . sprintf(
					'bKash PGW Error: bKash PGW requires PHP 5.3 and above. You are using version %s.',
					esc_html( PHP_VERSION )
				) . '</p></div>'
			);
		} elseif ( ! $this->app_key || ! $this->app_secret ) { // Check required fields.
			echo '<div class="error app-key-error"><p>bKash PGW Error: Please enter your app keys and secrets</p></div>';
		} elseif ( 'BDT' !== get_woocommerce_currency() ) {
			echo '<div class="error currency-error"><p>bKash PGW Error: Only supports BDT as currency</p></div>';
		} elseif ( ! class_exists( 'WordPressHTTPS' ) && 'no' === get_option( 'woocommerce_force_ssl_checkout' ) && ! is_ssl()
		) {
			// Show message if enabled and FORCE SSL is disabled and WordPress HTTPS plugin is not detected.
			$admin_checkout_setting_url = esc_url( admin_url( 'admin.php?page=wc-settings&tab=checkout' ) ); ?>
			<div class="error ssl-error"><p>bKash PGW is enabled, but the<a href="
			<?php
					esc_html_e( $admin_checkout_setting_url, 'bkash-for-woocommerce' );
			?>
					">force SSL option</a>is
					disabled;
					your checkout may not be secure! Please enable SSL and ensure your server has a valid SSL
					certificate -
					bKash PGW will only work in sandbox mode.</p></div>
			<?php
		}

		// APP KEY APP SECRET CHECK
		if ( empty( $this->app_key ) || empty( $this->app_secret ) || empty( $this->username ) || empty( $this->password ) ) {
			$this->appKeyMissingNotice();
		}
	}

	/**
	 * WooCommerce Payment Gateway App key missing Notice.
	 *
	 * @access public
	 */
	final public function appKeyMissingNotice() {
		$notice = '<div class="error woocommerce-message wc-connect">
                   <p>Please set bKash PGW credentials for accepting payments!</p></div>';
		add_action( 'admin_notices', $notice );
	}

	/**
	 * Payment form on checkout page.
	 *
	 * @access public
	 */
	final public function payment_fields() {
		$description = $this->get_description();

		if ( $this->sandbox === 'yes' ) {
			$description .= ' (IN SANDBOX)';
		}

		if ( ! empty( $description ) ) {
			echo wpautop( wptexturize( trim( $description ) ) );
		}

		if ( is_user_logged_in() ) {
			$user_id        = get_current_user_id();
			$agreementModel = new Agreement();
			$agreements     = $agreementModel->getAgreements( $user_id );

			// This includes your custom payment fields.
			include_once WooCommerceBkashPgw()->pluginPath() . '/includes/classes/views/html-payment-fields.php';
		} elseif ( $this->integration_type === 'tokenized' ) {
			echo "<p style='color:red'>Please login to complete the payment</p>";
		}
	}

	/**
	 * Outputs scripts used for the payment gateway.
	 *
	 * @access public
	 */
	final public function paymentScripts() {
		// if our payment gateway is disabled, we do not have to enqueue JS too
		// do not work with bKash PGW without SSL unless your website is in a test mode
		if ( 'no' === $this->enabled || ( $this->sandbox === 'no' && ! is_ssl() ) ) {
			return;
		}

		// no reason to enqueue JavaScript if API keys are not set
		if ( empty( $this->app_key ) || empty( $this->app_secret ) ) {
			return;
		}

		if ( ! is_checkout() || ! $this->isAvailable() ) {
			return;
		}

		if ( $this->integration_type === 'checkout' ) {
			$bk_script_url = Operations::checkoutScriptURL( $this->sandbox === 'yes', $this->api_version );

			// and this is our custom JS in your plugin directory that works with token.js
			wp_register_script(
				'woocommerce-payment-gateway-bkash',
				plugins_url( '../../assets/js/checkout.js?' . time(), __FILE__ ),
				array()
			);

			// in most payment processors you have to use PUBLIC KEY to obtain a token
			wp_localize_script(
				'woocommerce-payment-gateway-bkash',
				'bKash_objects',
				array(
					'apiVersion'           => $this->api_version,
					'sandbox'              => $this->sandbox,
					'bKash_slug'           => BKASH_FW_PLUGIN_SLUG,
					'submit_order'         => esc_url( WC_AJAX::get_endpoint( 'checkout' ) ),
					'ajaxURL'              => esc_url( admin_url( 'admin-ajax.php' ) ),
					'wcAjaxURL'            => esc_url( $this->siteUrl . BKASH_FW_WC_API . $this->EXECUTE_URL ),
					'wcPaymentCancelUrl'   => esc_url( $this->siteUrl . BKASH_FW_WC_API . $this->PAYMENT_CANCEL_URL ),
					'cancelAgreement'      => esc_url( $this->siteUrl . BKASH_FW_WC_API . $this->CANCEL_AGREEMENT_URL ),
					'review_order_payment' => esc_url( $this->siteUrl . BKASH_FW_WC_API . $this->REVIEW_ORDER_URL ),
					'bKashScriptURL'       => esc_url( $bk_script_url ),
				)
			);
		} else {
			// and this is our custom JS in your plugin directory that works with token.js
			wp_register_script(
				'woocommerce-payment-gateway-bkash',
				plugins_url( '../../assets/js/tokenized.js?' . time(), __FILE__ ),
				array()
			);

			// in most payment processors you have to use PUBLIC KEY to obtain a token
			wp_localize_script(
				'woocommerce-payment-gateway-bkash',
				'bKash_objects',
				array(
					'apiVersion'      => $this->api_version,
					'sandbox'         => $this->sandbox,
					'cancelAgreement' => esc_url( $this->siteUrl . BKASH_FW_WC_API . $this->CANCEL_AGREEMENT_URL ),
				)
			);
		}
		wp_enqueue_script( 'woocommerce-payment-gateway-bkash' );
	}

	/**
	 * Check if this gateway is enabled.
	 *
	 * @access public
	 */
	final public function isAvailable(): bool {
		if ( $this->enabled === 'no' || ( ! is_ssl() && 'no' === $this->sandbox ) ) {
			return false;
		}

		if ( ! $this->app_key || ! $this->app_secret || 'BDT' !== get_woocommerce_currency() ) {
			return false;
		}

		return true;
	}

	final public function processReviewOrderPayment() {
		$order_id = Utils::safePostValue( 'order_id' );
		if ( ! $order_id ) {
			$order_id = Utils::safeGetValue( 'order_id' );
		}

		header( 'Content-Type: application/json' );

		if ( $order_id ) {
			echo wp_json_encode( $this->process_payment( $order_id ) );
		} else {
			echo wp_json_encode(
				array(
					'result'  => 'failure',
					'message' => 'Order ID is missing',
				)
			);
		}
		die();
	}

	final public function process_payment( $order_id ) {
		$cbURL = get_site_url() . BKASH_FW_WC_API . $this->CALLBACK_URL . '?orderId=' . $order_id;

		return ( new ProcessPayments( $this->integration_type ) )->createPayment( $order_id, $this->intent, $cbURL );
	}

	final public function createPaymentCallbackProcess() {
		$order_id = Utils::safePostValue( 'orderId' );
		if ( ! $order_id ) {
			$order_id = Utils::safeGetValue( 'orderId' );
		}

		// To receive order id
		$order = wc_get_order( $order_id );
		if ( $order ) {
			$cbURL = get_site_url() . BKASH_FW_WC_API . $this->CALLBACK_URL . '?orderId=' . $order_id;

			$process = new ProcessPayments( $this->integration_type );
			$process->executePayment( $this->get_return_url( $order ), $cbURL );
		} else {
			echo wp_json_encode(
				array(
					'result'  => 'failure',
					'message' => 'Order not found',
				)
			);
		}
		die();
	}

	final public function cancelPaymentProcess() {
		$order_id = Utils::safePostValue( 'orderId' );
		if ( ! $order_id ) {
			$order_id = Utils::safeGetValue( 'orderId' );
		}

		$process = new ProcessPayments( $this->integration_type );
		$resp    = $process->cancelPayment( $order_id );
		echo wp_json_encode( $resp );

		die();
	}

	final public function cancelAgreementApi() {
		$agreement_id = Utils::safeGetValue( 'id' );

		$agreementModel = new Agreement();
		$agreement      = $agreementModel->getAgreement( $agreement_id );
		$isSameUser     = $agreement && ( (int) $agreement->getUserID() ) === get_current_user_id();
		if ( $isSameUser ) {
			$api            = new ApiComm();
			$cancelUsingAPI = $api->agreementCancel( $agreement_id );

			$decoded_response = isset( $cancelUsingAPI['response'] ) && is_string( $cancelUsingAPI['response'] ) ?
				json_decode( $cancelUsingAPI['response'], true ) : array();
			if ( isset( $decoded_response['agreementStatus'] ) && $decoded_response['agreementStatus'] === BKASH_FW_CANCELLED_STATUS
			) {
				// CANCELED

				$agreementModel->delete( $agreement_id );

				echo wp_json_encode(
					array(
						'result'  => 'success',
						'message' => 'Token for that agreement has been deleted',
					)
				);
				die();
			}

			if ( isset( $decoded_response['errorCode'] ) ) {
				$message = $decoded_response['errorMessage'] ?? 'Please try later';
			} else {
				$message = 'Cannot cancel right now. Please try later';
			}
		} else {
			$message = 'Agreement not found';
		}

		echo wp_json_encode(
			array(
				'result'  => 'failure',
				'message' => $message,
			)
		);
		// Return message to customer.
		die();
	}

	final public function paymentSuccess() {
		// for later use
	}

	final public function paymentFailure() {
		// for later use
	}

	/**
	 * Process refunds.
	 * WooCommerce 2.2 or later
	 *
	 * @access public
	 *
	 * @param $order_id
	 * @param $amount
	 * @param $reason
	 *
	 * @return bool
	 * @throws Exception
	 */
	final public function process_refund( $order_id, $amount = null, $reason = '' ) {
		$order = wc_get_order( $order_id );
		$id    = $order->get_transaction_id();

		$response = '';

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$trxObject   = new Transaction();
		$transaction = $trxObject->getTransaction( '', $id );
		if ( $transaction ) {
			if ( empty( $transaction->getRefundID() ) ) {
				$refundAmount = $amount ?? $transaction->getAmount();

				$comm = new ApiComm();
				$call = $comm->refund(
					$refundAmount,
					$transaction->getPaymentID(),
					$transaction->getTrxID(),
					$transaction->getOrderID(),
					$reason ?? 'Refund Purpose'
				);

				if ( isset( $call['status_code'] ) && $call['status_code'] === 200 ) {
					// response sample
					// array(7) {
					// ["completedTime"]=> string(32) "2021-02-21T15:40:17:162 GMT+0000"
					// ["transactionStatus"]=> string(9) "Completed"
					// ["originalTrxID"]=> string(10) "8BI704KGJX"
					// ["refundTrxID"]=> string(10) "8BL204KJ0E"
					// ["amount"]=> string(5) "10.00"
					// ["currency"]=> string(3) "BDT"
					// ["charge"]=> string(4) "0.00"
					// }

					$trx = array();
					if ( isset( $call['response'] ) && is_string( $call['response'] ) ) {
						$trx = json_decode( $call['response'], true );
					}

					// If any error for tokenized
					if ( isset( $trx['statusMessage'] ) && $trx['statusMessage'] !== 'Successful' ) {
						$trx = $trx['statusMessage'];
					} elseif ( isset( $trx['errorCode'] ) ) { // If any error for checkout
						$trx = $trx['errorMessage'] ?? '';
					} elseif ( isset( $trx['transactionStatus'] ) && $trx['transactionStatus'] === 'Completed' ) {
						if ( isset( $trx['refundTrxID'] ) && ! empty( $trx['refundTrxID'] ) ) {
							$this->refundObj = $trx; // so that another class can get the information

							wc_create_refund(
								array(
									'amount'         => $amount,
									'reason'         => $reason,
									'order_id'       => $order_id,
									'refund_payment' => false,
								)
							);

							$order->add_order_note(
								sprintf(
									'bKash PGW: Refunded %s - Refund ID: %s',
									$refundAmount,
									$trx['refundTrxID']
								)
							);

							$transaction->update(
								array(
									'refund_id'     => $trx['refundTrxID'],
									'refund_amount' => $trx['amount'] ?? 0,
								),
								array( 'invoice_id' => $transaction->getInvoiceID() )
							);

							if ( $this->debug === 'yes' ) {
								$this->log->add(
									$this->id,
									'bKash PGW order #' . $order_id . ' refunded successfully!'
								);
							}

							return true;
						}

						$trx = 'Refund was not successful, no refund id found, try again';
					} else {
						$trx = 'Refund was not successful, transaction is not in completed state, try again';
					}
				} else {
					$trx = 'Cannot refund the transaction using bKash server right now, try again';
				}
			} else {
				$trx = 'This transaction already has been refunded, try again';
			}
		} else {
			$trx = 'Cannot find the transaction to refund in your database, try again';
		}

		if ( is_string( $trx ) ) {
			$this->refundError = $trx;
			$order->add_order_note( 'Error in refunding the order. ' . esc_html( $trx ) );

			if ( $this->debug === 'yes' ) {
				$this->log->add(
					$this->id,
					'Error in refunding the order #' . $order_id . '. bKash PGW response: '
					. print_r( esc_html( $response ), true )
				);
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
	final public function queryRefund( int $order_id ) {
		$order = wc_get_order( $order_id );
		$id    = $order->get_transaction_id();

		$response = '';

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$trxObject   = new Transaction();
		$transaction = $trxObject->getTransaction( '', $id );
		if ( $transaction ) {
			if ( ! empty( $transaction->getRefundID() ) ) {
				$comm = new ApiComm();
				$call = $comm->refund(
					null,
					$transaction->getPaymentID(),
					$transaction->getTrxID(),
					null,
					null
				);

				if ( isset( $call['status_code'] ) && $call['status_code'] === 200 ) {
					return isset( $call['response'] ) && is_string( $call['response'] )
						? json_decode( $call['response'], true ) : array();
				}

				$trx = 'Cannot check refund status using bKash server right now, try again';
			} else {
				$trx = 'This transaction is not refunded yet, try again';
			}
		} else {
			$trx = 'Cannot find the transaction to query in your database, try again';
		}

		return $trx;
	}

	/**
	 * Capture the provided amount.
	 *
	 * @param float    $amount
	 * @param WC_Order $order
	 *
	 * @return WP_Error
	 */
	final public function capture_charge( float $amount, WC_Order $order ): WP_Error {
		return new WP_Error(
			'capture-error',
			sprintf(
				'There was an error capturing amount of %s the charge for order: %s.',
				$amount,
				$order->get_id()
			)
		);
	}

	/**
	 *
	 * @param WC_Order $order
	 *
	 * @return bool|WP_Error
	 */
	final public function void_charge( WC_Order $order ) {
		$id = $order->get_transaction_id();
		try {
			$response = $this->gateway->transaction()->void( $id );
			if ( $response->success ) {
				$this->save_order_meta( $response->transaction, $order );
				$order->update_status( 'cancelled' );
				$order->add_order_note( sprintf( 'Transaction %1$s has been voided in bKash.', $id ) );

				return true;
			}

			return new WP_Error(
				'capture-error',
				sprintf( 'There was an error voiding the transaction. Reason: %1$s', wp_json_encode( $response ) )
			);
		} catch ( Exception $e ) {
			return new WP_Error(
				'capture-error',
				sprintf( 'There was an error voiding the transaction. Reason: %1$s', wp_json_encode( $e ) )
			);
		}
	}


	/**
	 * WebhookModule Integration
	 *
	 * @return void
	 */
	final public function webhook() {
		if ( isset( $this->is_webhook ) && $this->is_webhook === 'yes' ) {
			$webhook = new WebhookProcessor( wc_get_logger(), true );
			$webhook->processRequest();
		} else {
			$this->log->add( $this->id, 'WebhookModule is not enabled in settings' );
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
	final public function receiptPage() {
		echo wp_kses_post( '<p>Thank you - your order is now pending payment.</p>' );
	}

	/**
	 * Output for the order received page.
	 *
	 * @access public
	 *
	 * @param $order_id
	 */
	final public function thankYouPage( $order_id ) {
		$this->extraDetails( $order_id );
	}


	/**
	 * Gets the extra details you set here to be
	 * displayed on the 'Thank you' page.
	 *
	 * @access private
	 *
	 * @param string $order_id
	 */
	private function extraDetails( $order_id = '' ) {
		$order = wc_get_order( $order_id );
		$id    = $order->get_transaction_id();

		echo wp_kses_post( '<h2> Payment Details </h2>' ) . PHP_EOL;

		$trxObj = new Transaction();
		$trx    = $trxObj->getTransaction( '', $id );
		if ( $trx ) {
			include_once 'Admin/pages/extra_details.php';
		}
	}

	/**
	 * Add content to the WC emails.
	 *
	 * @access public
	 *
	 * @param WC_Order $order
	 * @param bool     $sent_to_admin
	 * @param bool     $plain_text
	 */
	final public function emailInstructions( WC_Order $order, bool $sent_to_admin, bool $plain_text = false ) {
		if ( ! $sent_to_admin && $this->id === $order->get_payment_method() && $order->has_status( 'on-hold' ) ) {
			$this->extraDetails( $order->get_id() );
		}
	}

	/**
	 * Function executed when the 'admin_notices' action is called, here we check if there are notices on
	 * our database and display them, after that, we remove the option to prevent notices being displayed forever.
	 *
	 * @return void
	 */

	final public function displayFlashNotices() {
		$notices = get_option( 'bKash_flash_notices', array() );

		// Iterate through our notices to be displayed and print them.
		foreach ( $notices as $notice ) {
			printf(
				'<div class="notice notice-%1$s %2$s"><p>%3$s</p></div>',
				esc_attr( $notice['type'] ),
				$notice['dismissible'],
				esc_html( $notice['notice'] )
			);
		}

		// Now we reset our options to prevent notices being displayed forever.
		if ( ! empty( $notices ) ) {
			delete_option( 'bKash_flash_notices' );
		}
	}

	/**
	 * Get the transaction URL.
	 *
	 * @param WC_Order $order
	 *
	 * @return string
	 */
	final public function getTransactionUrl( WC_Order $order ): string {
		return $this->get_transaction_url( $order );
	}
} // end class.
