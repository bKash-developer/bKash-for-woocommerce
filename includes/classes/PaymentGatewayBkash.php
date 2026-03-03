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
use WC_Order;
use WC_Payment_Gateway;
use WP_Error;
use bKash\PGW\ApiComm;
use bKash\PGW\ProcessPayments;
use bKash\PGW\Operations;
use bKash\PGW\Utils;
use WC_AJAX;

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
	private $CANCEL_AGREEMENT_URL     = 'bk_cancel_agreement';
	private $AGREEMENT_CALLBACK_URL   = 'bk_agreement_callback';
	private $REVIEW_ORDER_URL         = 'bk_review_order';
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
		// Logs.
		if ( $this->debug === 'yes' ) {
			if ( function_exists( 'wc_get_logger' ) ) {
				$this->log = wc_get_logger();
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
					// 'checkout'       => 'Checkout',
					'checkout-url'   => 'Checkout URL (Tokenized Non-Agreement)',
					'tokenized'      => 'Tokenized (With Agreement)',
					'tokenized-both' => 'Tokenized (With or without Agreement)',
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
			// 'bkash_api_version'  => array(
			// 	'title'       => 'API Version',
			// 	'type'        => 'text',
			// 	'description' => 'This api version will be used for calling API to bKash',
			// 	'default'     => 'v1.2.0-beta',
			// 	'desc_tip'    => true,
			// ),
			'debug'              => array(
				'title'       => 'Debug Log',
				'type'        => 'checkbox',
				'label'       => 'Enable logging',
				'default'     => 'no',
				'description' => sprintf(
					'Log bKash PGW events inside <code>%s</code>',
					esc_html( WC_LOG_DIR . $this->id . '-' . wp_hash( $this->id ) . '.log' )
				),
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
		add_action( 'woocommerce_api_' . $this->AGREEMENT_CALLBACK_URL, array( $this, 'createAgreementCallbackProcess' ) );
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
					$captureCall = $comm->capturePayment( $transaction->getPaymentID(), $transaction->getMode() ?? '0011' );

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
					$void_call = $comm->voidPayment( $transaction->getPaymentID(), $transaction->getMode() ?? '0011' );

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
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_die( 'You do not have sufficient permissions to access this page.' );
		}

		include_once \WooCommerceBkashPgw()->pluginPath() . '/includes/classes/Admin/views/admin-options.php';
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

			// If WooCommerce payment tokens are available, merge them so tokenized agreements are shown.
			if ( class_exists( 'WC_Payment_Tokens' ) ) {
				try {
					$tokens = \WC_Payment_Tokens::get_customer_tokens( $user_id, BKASH_FW_PLUGIN_SLUG );
					if ( is_array( $tokens ) && ! empty( $tokens ) ) {
						$token_agreements = array();
						foreach ( $tokens as $t ) {
							if ( method_exists( $t, 'get_token' ) ) {
								$token_agreements[] = (object) array(
									'agreement_token' => $t->get_token(),
									'phone'           => $t->get_meta( 'phone' ),
									'ID'              => $t->get_id(),
								);
							}
						}
						// Prefer WC tokens; append DB agreements that are not already represented
						if ( empty( $agreements ) ) {
							$agreements = $token_agreements;
						} else {
							foreach ( $token_agreements as $ta ) {
								$found = false;
								foreach ( $agreements as $a ) {
									if ( isset( $a->agreement_token ) && $a->agreement_token === $ta->agreement_token ) {
										$found = true;
										break;
									}
								}
								if ( ! $found ) {
									$agreements[] = $ta;
								}
							}
						}
					}
				} catch ( \Exception $e ) {
					// ignore token read errors, fall back to DB agreements
				}
			}

			// This includes your custom payment fields.
			include_once \WooCommerceBkashPgw()->pluginPath() . '/includes/classes/views/html-payment-fields.php';
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
					'ajaxNonce'            => wp_create_nonce( 'bkash-ajax-nonce' ),
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
					'ajaxNonce'       => wp_create_nonce( 'bkash-ajax-nonce' ),
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

		// Verify nonce
		$nonce = Utils::safePostValue( 'security' );
		if ( ! $nonce ) {
			$nonce = Utils::safeGetValue( 'security' );
		}
		if ( empty( $nonce ) || ! wp_verify_nonce( $nonce, 'bkash-ajax-nonce' ) ) {
			echo wp_json_encode( array( 'result' => 'failure', 'message' => 'Invalid nonce' ) );
			die();
		}

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
		$cbURL          = get_site_url() . BKASH_FW_WC_API . $this->CALLBACK_URL . '?orderId=' . $order_id;
		$agreementCbURL = get_site_url() . BKASH_FW_WC_API . $this->AGREEMENT_CALLBACK_URL . '?orderId=' . $order_id;

		return ( new ProcessPayments( $this->integration_type ) )->createPayment( $order_id, $this->intent, $cbURL, null, $agreementCbURL );
	}

	final public function createPaymentCallbackProcess() {
		$order_id = Utils::safePostValue( 'orderId' );
		if ( ! $order_id ) {
			$order_id = Utils::safeGetValue( 'orderId' );
		}

		$invoice_id = Utils::safePostValue( 'invoiceId' );
		if ( ! $invoice_id ) {
			$invoice_id = Utils::safeGetValue( 'invoiceId' );
		}

		$agreement_id = Utils::safePostValue( 'agreementId' );
		if ( ! $agreement_id ) {
			$agreement_id = Utils::safeGetValue( 'agreementId' );
		}

		$order = wc_get_order( $order_id );
		if ( ! $order ) {
			echo wp_json_encode(
				array(
					'result'  => 'failure',
					'message' => 'Order not found. Order ID: ' . $order_id,
				)
			);
			die();
		}

		// Validate invoice ID against the stored transaction
		$trx         = new Transaction();
		$transaction = $trx->getTransaction( $invoice_id );
		if ( ! $transaction || $transaction->getOrderID() !== $order_id ) {
			echo wp_json_encode(
				array(
					'result'  => 'failure',
					'message' => 'Invoice ID mismatch or transaction not found.',
				)
			);
			die();
		}

		$cbURL = get_site_url() . BKASH_FW_WC_API . $this->CALLBACK_URL . '?orderId=' . $order_id . '&invoiceID=' . $invoice_id;

		$process = new ProcessPayments( $this->integration_type );
		$process->executePayment( $this->get_return_url( $order ), $cbURL, $agreement_id );
		die();
	}

	/**
	 * Agreement Callback Handler
	 *
	 * Called when bKash redirects the user back after agreement approval/failure/cancel.
	 * bKash sends: orderId, agreementId, status, signature (no paymentID or invoiceID).
	 * Looks up the transaction by order_id, then calls handleAgreementCallback.
	 */
	final public function createAgreementCallbackProcess() {
		$order_id = Utils::safeGetValue( 'orderId' );
		$status   = Utils::safeGetValue( 'status' );
		$agreement_id = Utils::safeGetValue( 'agreementId' );

		header( 'Content-Type: application/json' );

		if ( empty( $order_id ) ) {
			wp_send_json_error( array( 'message' => 'Order ID is missing.' ) );
		}

		$order = wc_get_order( $order_id );
		if ( ! $order ) {
			wp_send_json_error( array( 'message' => 'Order not found.' ) );
		}

		$trx         = new Models\Transaction();
		$transaction = $trx->getTransactionByOrderId( $order_id );

		if ( ! $transaction || $transaction->getMode() !== '0000' ) {
			wp_send_json_error( array( 'message' => 'Agreement transaction not found for this order.' ) );
		}

		$process = new ProcessPayments( $this->integration_type );
		if ( $status === 'success' ) {
			$transaction->update( array( 'status' => 'CALLBACK_REACHED' ) );

			$agreementResult = $process->handleAgreementCallback(
				$agreement_id,
				$order->get_user_id()
			);

			if ( $agreementResult['success'] ) {
				$transaction->update( array( 'status' => 'AgreementCompleted', 'mode' => '0001' ) );
				add_post_meta( $order->get_id(), '_bkmode', '0001', true );

				wp_send_json_success( array(
					'message'     => 'Agreement created and executed successfully.',
					'agreementID' => $agreementResult['agreementID'],
					'data'        => $agreementResult['data'] ?? array(),
				) );
			} else {
				$transaction->update( array( 'status' => 'AgreementFailed' ) );
				$order->add_order_note( 'bKash Agreement failed: ' . ( $agreementResult['message'] ?? '' ) );

				wp_send_json_error( array(
					'message' => $agreementResult['message'] ?? 'Agreement execution failed.',
					'data'    => $agreementResult['data'] ?? array(),
				) );
			}
			die();
		}

		// Agreement was cancelled or failed at bKash
		$statusLabel = str_replace( array( 'cancel', 'failure' ), array( 'Cancelled', 'Failed' ), $status );
		$transaction->update( array( 'status' => $statusLabel ) );
		$order->add_order_note( 'bKash Agreement ' . $statusLabel );

		wp_send_json_error( array( 'message' => 'Agreement ' . $statusLabel ) );
		die();
	}

	final public function cancelPaymentProcess() {
		$order_id = Utils::safePostValue( 'orderId' );
		if ( ! $order_id ) {
			$order_id = Utils::safeGetValue( 'orderId' );
		}

		// Verify nonce for frontend cancel
		$nonce = Utils::safePostValue( 'security' );
		if ( ! $nonce ) {
			$nonce = Utils::safeGetValue( 'security' );
		}
		if ( empty( $nonce ) || ! wp_verify_nonce( $nonce, 'bkash-ajax-nonce' ) ) {
			echo wp_json_encode( array( 'result' => 'failure', 'message' => 'Invalid nonce' ) );
			die();
		}

		$process = new ProcessPayments( $this->integration_type );
		$resp    = $process->cancelPayment( $order_id );
		echo wp_json_encode( $resp );

		die();
	}

	final public function cancelAgreementApi() {
		$agreement_id = Utils::safePostValue( 'id' );
		if ( ! $agreement_id ) {
			$agreement_id = Utils::safeGetValue( 'id' );
		}

		// Verify nonce to protect this action from CSRF
		$nonce = Utils::safePostValue( 'bkash-ajax-nonce' );
		if ( ! $nonce ) {
			$nonce = Utils::safeGetValue( 'bkash-ajax-nonce' );
		}
		if ( empty( $nonce ) || ! wp_verify_nonce( $nonce, 'bkash-ajax-nonce' ) ) {
			echo wp_json_encode( array( 'result' => 'failure', 'message' => 'Invalid nonce' ) );
			die();
		}

		$agreementModel = new Agreement();
		$agreement      = $agreementModel->getAgreement( $agreement_id );

		if ( $agreement && ( (int) $agreement->getUserID() ) === get_current_user_id() ) {
			$api            = new ApiComm();
			$cancelUsingAPI = $api->agreementCancel( $agreement->getAgreementID() );

			$decoded_response = isset( $cancelUsingAPI['response'] ) && is_string( $cancelUsingAPI['response'] )
									? json_decode( $cancelUsingAPI['response'], true )
									: array();
			if ( isset( $decoded_response['agreementStatus'] ) && $decoded_response['agreementStatus'] === BKASH_FW_CANCELLED_STATUS)
			{
				$agreementModel->delete( $agreement->getAgreementID() );
				// Also attempt to remove corresponding WC payment token if present.
				if ( method_exists( $agreementModel, 'deleteWcTokenByToken' ) ) {
					$agreementModel->deleteWcTokenByToken( $agreement->getAgreementID() );
				}

				echo wp_json_encode(
					array(
						'result'  => 'success',
						'message' => 'Agreement Token has been deleted',
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
					! empty( $reason ) ? $reason : 'Refund Purpose'
				);

				if ( isset( $call['status_code'] ) && $call['status_code'] === 200 ) {
					$trx = array();
					if ( isset( $call['response'] ) && is_string( $call['response'] ) ) {
						$trx = json_decode( $call['response'], true );
					}

					// If any error for tokenized
					if ( isset( $trx['statusMessage'] ) && $trx['statusMessage'] !== 'Successful' ) {
						$trx = $trx['statusMessage'];
					} elseif ( isset( $trx['errorCode'] ) ) { // If any error for checkout
						$trx = $trx['errorMessage'] ?? '';
					} else {
						// Normalize v2 keys to v1 keys for compatibility
						$txnStatus  = $trx['refundTransactionStatus'] ?? $trx['transactionStatus'] ?? '';
						$refundTrxId = $trx['refundTrxId'] ?? $trx['refundTrxID'] ?? '';
						$originalTrxId = $trx['originalTrxId'] ?? $trx['originalTrxID'] ?? '';
						$refundAmt  = $trx['refundAmount'] ?? $trx['amount'] ?? 0;

						if ( $txnStatus === 'Completed' ) {
							if ( ! empty( $refundTrxId ) ) {
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
										$refundTrxId
									)
								);

								$transaction->update(
									array(
										'refund_id'     => $refundTrxId,
										'refund_amount' => $refundAmt,
									),
									array( 'invoice_id' => $transaction->getInvoiceID() )
								);

								if ( $this->debug === 'yes' ) {
									$this->safe_log( 'bKash PGW order #' . $order_id . ' refunded successfully!', 'info' );
								}

								return true;
							}

							$trx = 'Refund was not successful, no refund id found, try again';
						} else {
							$trx = 'Refund was not successful, transaction is not in completed state, try again';
						}
					}
				} else {
					// Non-200 response — try to extract bKash error message
					$errBody = array();
					if ( isset( $call['response'] ) && is_string( $call['response'] ) ) {
						$errBody = json_decode( $call['response'], true );
					}
					$trx = $errBody['errorMessageEn'] ?? $errBody['errorMessage'] ?? $errBody['statusMessage']
						?? 'Cannot refund the transaction using bKash server right now, try again';
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
				$this->safe_log( 'Error in refunding the order #' . $order_id . '. bKash PGW response: ' . wp_json_encode( $response ), 'error' );
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
				$call = $comm->refundStatus(
					$transaction->getPaymentID(),
					$transaction->getTrxID()
				);

				if ( isset( $call['status_code'] ) && $call['status_code'] === 200 ) {
					$data = isset( $call['response'] ) && is_string( $call['response'] )
						? json_decode( $call['response'], true ) : array();

					// Refund status API nests refund details inside refundTransactions array.
					// Flatten the first entry into the top-level array so the template can read it.
					if ( ! empty( $data['refundTransactions'] ) && is_array( $data['refundTransactions'] ) ) {
						$first = $data['refundTransactions'][0];
						$data  = array_merge( $data, $first );
						unset( $data['refundTransactions'] );
					}

					return $data;
				}

				// Parse error message from non-200 response.
				$error_msg = 'Cannot check refund status using bKash server right now, try again';
				if ( isset( $call['response'] ) && is_string( $call['response'] ) ) {
					$err_data = json_decode( $call['response'], true );
					if ( is_array( $err_data ) ) {
						$error_msg = $err_data['errorMessageEn'] ?? $err_data['errorMessage'] ?? $err_data['statusMessage'] ?? $error_msg;
					}
				}
				$trx = $error_msg;
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
			$this->safe_log( 'WebhookModule is not enabled in settings', 'warning' );
		}

		$payload = (array) json_decode( file_get_contents( 'php://input' ), true );
		// Avoid logging full webhook payloads to prevent leaking secrets. Log payload keys only.
		$keys = is_array( $payload ) ? array_keys( $payload ) : array();
		$this->safe_log( 'WEBHOOK => BODY KEYS: ' . implode( ',', $keys ), 'info' );

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

	/**
	 * Safe logging wrapper that uses WooCommerce logger if available and debug is enabled.
	 *
	 * @param string $message
	 * @param string $level one of 'info', 'warning', 'error'
	 *
	 * @return void
	 */
	public function safe_log( string $message, string $level = 'info' ): void {
		if ( ! function_exists( 'wc_get_logger' ) ) {
			return;
		}
		if ( isset( $this->debug ) && $this->debug !== 'yes' ) {
			return;
		}
		$logger  = wc_get_logger();
		$context = array( 'source' => $this->id ?? BKASH_FW_PLUGIN_SLUG );
		switch ( $level ) {
			case 'warning':
				$logger->warning( $message, $context );
				break;
			case 'error':
				$logger->error( $message, $context );
				break;
			default:
				$logger->info( $message, $context );
		}
	}
} // end class.
