<?php

namespace bKash\PGW;

use bKash\PGW\Models\Agreement;
use bKash\PGW\Models\Transactions;
use InvalidArgumentException;
use WC_AJAX;
use WC_Logger;
use WC_Order;
use WC_Payment_Gateway;
use WP_Error;

if (!defined('ABSPATH')) {
    exit;
} // Exit if accessed directly.

/**
 * WooCommerce bKash Payment Gateway.
 *
 * @class   WC_bKash
 * @extends WC_Payment_Gateway
 * @version 1.0.0
 * @package bKash\PGW
 * @author  Md. Shahnawaz Ahmed
 */
class WC_bKash extends WC_Payment_Gateway
{
	public $log;
    public $bKashObj;
    public $refundObj;
    public $refundError;
    private $CALLBACK_URL = "bkash_payment_process";
    private $SUCCESS_CALLBACK_URL = "bkash_payment_success";
    private $FAILURE_CALLBACK_URL = "bkash_payment_failure";
    private $SUCCESS_REDIRECT_URL = "/checkout/order-received/";
    private $FAILURE_REDIRECT_URL = "/checkout/order-received/";
    private $API_HOST = " ";
    private $API_SESSION_CREATE_ENDPOINT = "/checkout/v1/session/create";
    private $EXECUTE_URL = "bk_execute";
    private $CANCEL_AGREEMENT_URL = "bk_cancel_agreement";
    private $WEBHOOK_URL = "bkash_webhook";

    /**
     * Constructor for the gateway.
     *
     * @access public
     * @return void
     */
    public function __construct()
    {
        $this->Initiate();
        $this->Hooks();

        add_action('admin_notices', array($this, 'display_flash_notices'), 12);
    }

    public function Initiate()
    {
        $this->id = 'bkash_pgw';
        $this->icon = apply_filters('woocommerce_payment_gateway_bkash_icon', plugins_url('../assets/images/logo.png', dirname(__FILE__)));
        $this->has_fields = true;
        $this->credit_fields = false;
        $this->order_button_text = __('Pay with bKash', 'woocommerce-payment-gateway-bkash');
        $this->method_title = __('bKash Payment Gateway', 'woocommerce-payment-gateway-bkash');
        $this->method_description = __('Take payments via bKash PGW.', 'woocommerce-payment-gateway-bkash');
        $this->notify_url = WC()->api_request_url('WC_Gateway_bKash');
        $this->siteUrl = get_site_url();
        $this->supports = array(
            'products',
            'refunds'
        );
        $this->view_transaction_url = 'https://developer.bka.sh';

        // Load the form fields.
        $this->init_form_fields();

        // Load the settings.
        $this->init_settings();

        // Get setting values.
        $this->enabled = $this->get_option('enabled');
        $this->title = $this->get_option('title');
        $this->description = $this->get_option('description');
        $this->instructions = $this->get_option('instructions');
        $this->integration_type = $this->get_option('integration_type');
        $this->intent = $this->get_option('intent');
        $this->api_version = $this->get_option('bkash_api_version');
        $this->sandbox = $this->get_option('sandbox');
        $this->app_key = $this->sandbox == 'no' ? $this->get_option('app_key') : $this->get_option('sandbox_app_key');
        $this->app_secret = $this->sandbox == 'no' ? $this->get_option('app_secret') : $this->get_option('sandbox_app_secret');
        $this->username = $this->sandbox == 'no' ? $this->get_option('username') : $this->get_option('sandbox_username');
        $this->password = $this->sandbox == 'no' ? $this->get_option('password') : $this->get_option('sandbox_password');
        $this->debug = $this->get_option('debug');
        // Logs.
        if ($this->debug == 'yes') {
            if (class_exists('\\WC_Logger')) {
                $this->log = new WC_Logger();
            } else {
                $this->log = isset($woocommerce) ? $woocommerce->logger() : null;
            }
        }

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
    public function init_form_fields()
    {
        $this->form_fields = array(
            'enabled' => array(
                'title' => __('Enable/Disable', 'woocommerce-payment-gateway-bkash'),
                'label' => __('Enable bKash PGW', 'woocommerce-payment-gateway-bkash'),
                'type' => 'checkbox',
                'description' => '',
                'default' => 'no'
            ),
            'title' => array(
                'title' => __('Title', 'woocommerce-payment-gateway-bkash'),
                'type' => 'text',
                'description' => __('This controls the title which the user sees during checkout.', 'woocommerce-payment-gateway-bkash'),
                'default' => __('bKash Payment Gateway', 'woocommerce-payment-gateway-bkash'),
                'desc_tip' => true
            ),
            'description' => array(
                'title' => __('Description', 'woocommerce-payment-gateway-bkash'),
                'type' => 'text',
                'description' => __('This controls the description which the user sees during checkout.', 'woocommerce-payment-gateway-bkash'),
                'default' => 'Pay with bKash PGW.',
                'desc_tip' => true
            ),
            'instructions' => array(
                'title' => __('Instructions', 'woocommerce-payment-gateway-bkash'),
                'type' => 'textarea',
                'description' => __('Instructions that will be added to the thank you page and emails.', 'woocommerce-payment-gateway-bkash'),
                'default' => '',
                'desc_tip' => true,
            ),
            'integration_type' => array(
                'title' => __('Integration Type', 'woocommerce-payment-gateway-bkash'),
                'type' => 'select',
                'description' => __('Payment will be initiated with selected bKash PGW integration type', 'woocommerce-payment-gateway-bkash'),
                'options' => array(
                    'checkout' => 'Checkout',
                    'checkout-url' => 'Checkout URL (Tokenized Non-Agreement)',
                    'tokenized' => 'Tokenized (With Agreement)',
                    'tokenized-both' => 'Tokenized (With and without Agreement)'
                ),
                'default' => 'checkout',
                'desc_tip' => true,
            ),
            'intent' => array(
                'title' => __('Intent', 'woocommerce-payment-gateway-bkash'),
                'type' => 'select',
                'description' => __('Payment will be initiated with selected bKash PGW integration type', 'woocommerce-payment-gateway-bkash'),
                'options' => array(
                    'sale' => 'Sale',
                    'authorization' => 'Authorized'
                ),
                'default' => 'checkout',
                'desc_tip' => true,
            ),
            'bkash_api_version' => array(
                'title' => __('API Version', 'woocommerce-payment-gateway-bkash'),
                'type' => 'text',
                'description' => __('This api version will be used for calling API to bKash', 'woocommerce-payment-gateway-bkash'),
                'default' => 'v1.2.0-beta',
                'desc_tip' => true,
            ),
            'debug' => array(
                'title' => __('Debug Log', 'woocommerce-payment-gateway-bkash'),
                'type' => 'checkbox',
                'label' => __('Enable logging', 'woocommerce-payment-gateway-bkash'),
                'default' => 'no',
                'description' => sprintf(__('Log bKash PGW events inside <code>%s</code>', 'woocommerce-payment-gateway-bkash'), wc_get_log_file_path($this->id))
            ),
            'sandbox' => array(
                'title' => __('Sandbox', 'woocommerce-payment-gateway-bkash'),
                'label' => __('Enable Sandbox Mode', 'woocommerce-payment-gateway-bkash'),
                'type' => 'checkbox',
                'description' => __('Place the payment gateway in sandbox mode using sandbox API keys (real payments will not be taken).', 'woocommerce-payment-gateway-bkash'),
                'default' => 'yes'
            ),
            'sandbox_app_key' => array(
                'title' => __('Sandbox Application Key', 'woocommerce-payment-gateway-bkash'),
                'type' => 'text',
                'description' => __('Get your API keys from your bKash PGW account.', 'woocommerce-payment-gateway-bkash'),
                'default' => '',
                'desc_tip' => true
            ),
            'sandbox_app_secret' => array(
                'title' => __('Sandbox Application Secret', 'woocommerce-payment-gateway-bkash'),
                'type' => 'password',
                'description' => __('Get your API keys from your bKash PGW account.', 'woocommerce-payment-gateway-bkash'),
                'default' => '',
                'desc_tip' => true
            ),
            'sandbox_username' => array(
                'title' => __('Sandbox Username', 'woocommerce-payment-gateway-bkash'),
                'type' => 'text',
                'description' => __('Get your API keys from your bKash PGW account.', 'woocommerce-payment-gateway-bkash'),
                'default' => '',
                'desc_tip' => true
            ),
            'sandbox_password' => array(
                'title' => __('Sandbox Password', 'woocommerce-payment-gateway-bkash'),
                'type' => 'password',
                'description' => __('Get your API keys from your bKash PGW account.', 'woocommerce-payment-gateway-bkash'),
                'default' => '',
                'desc_tip' => true
            ),
            'app_key' => array(
                'title' => __('Production Application Key', 'woocommerce-payment-gateway-bkash'),
                'type' => 'text',
                'description' => __('Get your API keys from your bKash PGW account.', 'woocommerce-payment-gateway-bkash'),
                'default' => '',
                'desc_tip' => true
            ),
            'app_secret' => array(
                'title' => __('Production Application Secret Key', 'woocommerce-payment-gateway-bkash'),
                'type' => 'text',
                'description' => __('Get your API keys from your bKash PGW account.', 'woocommerce-payment-gateway-bkash'),
                'default' => '',
                'desc_tip' => true
            ),
            'username' => array(
                'title' => __('Production Username', 'woocommerce-payment-gateway-bkash'),
                'type' => 'text',
                'description' => __('Get your API keys from your bKash PGW account.', 'woocommerce-payment-gateway-bkash'),
                'default' => '',
                'desc_tip' => true
            ),
            'password' => array(
                'title' => __('Production Password', 'woocommerce-payment-gateway-bkash'),
                'type' => 'text',
                'description' => __('Get your API keys from your bKash PGW account.', 'woocommerce-payment-gateway-bkash'),
                'default' => '',
                'desc_tip' => true
            ),
        );
    }

    /**
     * Init Payment Gateway SDK.
     *
     * @access protected
     * @return void
     */
    protected function init_gateway_sdk()
    {
        // TODO: Insert your gateway sdk script here and call it.
    }

    public function Hooks()
    {

        // Hooks.
        if (is_admin()) {
            // add_action('wp_enqueue_scripts', array($this, 'payment_scripts'));
            add_action('admin_notices', array($this, 'checks'));

            add_action('woocommerce_receipt_' . $this->id, array($this, 'receipt_page'));
            add_action('woocommerce_update_options_payment_gateways_' . $this->id, array($this, 'process_admin_options'));
        }
        add_action('wp_enqueue_scripts', array($this, 'payment_scripts'));
        add_action('woocommerce_thankyou_' . $this->id, array($this, 'thankyou_page'));

        // Customer Emails.
        add_action('woocommerce_email_before_order_table', array($this, 'email_instructions'), 10, 3);

        add_action('woocommerce_api_' . $this->CALLBACK_URL, array($this, 'create_payment_callback_process'));
        add_action('woocommerce_api_' . $this->SUCCESS_CALLBACK_URL, array($this, 'payment_success'));
        add_action('woocommerce_api_' . $this->FAILURE_CALLBACK_URL, array($this, 'payment_failure'));

        add_action('woocommerce_order_status_completed', array(__CLASS__, 'capture_transaction_from_status'), 10, 2);
        add_action('woocommerce_order_status_cancelled', array(__CLASS__, 'void_transaction_from_status'), 10, 2);

        add_action('woocommerce_api_' . $this->EXECUTE_URL, array($this, 'create_payment_callback_process'));
        add_action('woocommerce_api_' . $this->CANCEL_AGREEMENT_URL, array($this, 'cancel_agreement_api'));

        // Webhook
	    add_action( 'woocommerce_api_' . $this->WEBHOOK_URL, array( $this, 'webhook' ));
    }

    /**
     *
     * @param int $order_id
     * @param WC_Order $order
     */
    public static function capture_transaction_from_status($order_id, $order)
    {
        $trx = '';
        $orderDetails = wc_get_order($order_id);
        $id = $orderDetails->get_transaction_id();
        $payment_method = $orderDetails->get_payment_method(); // bkash_pgw

        if ($payment_method === 'bkash_pgw') {
            $trxObj = new Transactions();
            $transaction = $trxObj->getTransaction('', $id);
            if ($transaction) {
                if ($transaction->getStatus() === 'Authorized') {
                    $comm = new ApiComm();
                    $captureCall = $comm->capturePayment($transaction->getPaymentID());

                    if (isset($captureCall['status_code']) && $captureCall['status_code'] === 200) {
                        $captured = isset($captureCall['response']) && is_string($captureCall['response']) ? json_decode($captureCall['response'], true) : [];

                        if ($captured) {
                            // Sample payload - array(3) { ["status_code"]=> int(200) ["header"]=> NULL ["response"]=> string(177) "{"completedTime":"2021-02-21T18:46:18:085 GMT+0000","trxID":"8BM304KJ37","transactionStatus":"Completed","amount":"10","currency":"BDT","transferType":"Collection2Disbursement"}" }

                            // If any error for tokenized
                            if (isset($captured['statusMessage']) && $captured['statusMessage'] !== 'Successful') {
                                $trx = $captured['statusMessage'];
                            } // If any error for checkout
                            else if (isset($captured['errorCode'])) {
                                $trx = isset($captured['errorMessage']) ? $captured['errorMessage'] : '';
                            } else if (isset($captured['transactionStatus']) && $captured['transactionStatus'] === 'Completed') {
                                $trx = $captured;

                                $updated = $trxObj->update(['status' => 'Completed'], ['trx_id' => $transaction->getTrxID()]);
                                if ($updated == 0) {
                                    // on update error
                                    $orderDetails->add_order_note(sprintf(__('bKash PGW: Status update failed in DB, ' . $trxObj->errorMessage, 'woocommerce-payment-gateway-bkash')));
                                }

                                $orderDetails->add_order_note(sprintf(__('bKash PGW: Payment Capture of amount %s - Payment ID: %s', 'woocommerce-payment-gateway-bkash'), $transaction->getAmount(), $captured['trxID']));
                            } else {
                                $trx = "Transfer is not possible right now. try again";
                            }
                        } else {
                            $trx = "Cannot find the transaction in your database, try again";
                        }
                    } else {
                        $trx = "Cannot capture using bKash server right now, try again";
                    }
                } else {
                    // $trx = "Transaction is not in authorized state, thus ignore, try again";
                }
            } else {
                // $trx = "no transaction found with this order, try again";
            }
        } else {
            // $trx = "payment gateway is not bKash, try again";
        }

        if (isset($trx) && !empty($trx) && is_string($trx)) {
            // error occurred, show message
            // $orderDetails->update_status('on-hold', $trx, false);
            self::add_flash_notice(__("Capture Error, " . $trx), "warning", true);
        } else if (isset($trx) && !empty($trx) && is_array($trx)) {
            // Capture Success
            self::add_flash_notice(__("Payment has been captured"), "success", true);
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
     * @return void
     */

    public static function add_flash_notice($notice = "", $type = "warning", $dismissible = true)
    {
        // Here we return the notices saved on our option, if there are not notices, then an empty array is returned
        $notices = get_option("my_flash_notices", array());

        $dismissible_text = ($dismissible) ? "is-dismissible" : "";

        // We add our new notice.
        $notices[] = array(
            "notice" => $notice,
            "type" => $type,
            "dismissible" => $dismissible_text
        );

        // Then we update the option with our notices array
        update_option("my_flash_notices", $notices);
    }

    /**
     *
     * @param int $order_id
     * @param WC_Order $order
     */
    public static function void_transaction_from_status($order_id, $order)
    {
        $trx = '';
        $orderDetails = wc_get_order($order_id);
        $id = $orderDetails->get_transaction_id();
        $payment_method = $orderDetails->get_payment_method(); // bkash_pgw

        if ($payment_method === 'bkash_pgw') {
            $trxObj = new Transactions();
            $transaction = $trxObj->getTransaction('', $id);
            if ($transaction) {
                if ($transaction->getStatus() === 'Authorized') {
                    $comm = new ApiComm();
                    $captureCall = $comm->voidPayment($transaction->getPaymentID());

                    if (isset($captureCall['status_code']) && $captureCall['status_code'] === 200) {
                        $captured = isset($captureCall['response']) && is_string($captureCall['response']) ? json_decode($captureCall['response'], true) : [];

                        if ($captured) {
                            // Sample payload - array(3) { ["status_code"]=> int(200) ["header"]=> NULL ["response"]=> string(177) "{"completedTime":"2021-02-21T18:46:18:085 GMT+0000","trxID":"8BM304KJ37","transactionStatus":"Completed","amount":"10","currency":"BDT","transferType":"Collection2Disbursement"}" }

                            // If any error for tokenized
                            if (isset($captured['statusMessage']) && $captured['statusMessage'] !== 'Successful') {
                                $trx = $captured['statusMessage'];
                            } // If any error for checkout
                            else if (isset($captured['errorCode'])) {
                                $trx = isset($captured['errorMessage']) ? $captured['errorMessage'] : '';
                            } else if (isset($captured['transactionStatus']) && $captured['transactionStatus'] === 'Completed') {
                                $trx = $captured;

                                $updated = $trxObj->update(['status' => 'Void'], ['trx_id' => $transaction->getTrxID()]);
                                if ($updated == 0) {
                                    // on update error
                                    $orderDetails->add_order_note(sprintf(__('bKash PGW: Status update failed in DB, ' . $trxObj->errorMessage, 'woocommerce-payment-gateway-bkash')));
                                }

                                $orderDetails->add_order_note(sprintf(__('bKash PGW: Payment was updated as Void of amount %s - Payment ID: %s', 'woocommerce-payment-gateway-bkash'), $transaction->getAmount(), $captured['trxID']));
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
                    // $trx = "Transaction is not in authorized state, thus ignore, try again";
                }
            } else {
                // $trx = "no transaction found with this order, try again";
            }
        } else {
            // $trx = "payment gateway is not bKash, try again";
        }

        if (isset($trx) && !empty($trx) && is_string($trx)) {
            // error occurred, show message
            // $orderDetails->update_status('on-hold', $trx, false);
            self::add_flash_notice(__("Capture Error, " . $trx), "warning", true);
        } else if (isset($trx) && !empty($trx) && is_array($trx)) {
            // Capture Success
            self::add_flash_notice(__("Payment has been captured"), "success", true);
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
    public function admin_options()
    {
        include_once(WC_Gateway_bKash()->plugin_path() . '/includes/classes/Admin/views/admin-options.php');
    }

    /**
     * Check if SSL is enabled and notify the user.
     *
     * @TODO:  Use only what you need.
     * @access public
     */
    public function checks()
    {
        if ( $this->enabled === 'no' ) {
            return;
        }

        // PHP Version.
        if ( PHP_VERSION_ID < 50300 ) {
            echo '<div class="error"><p>' . sprintf(__('bKash PGW Error: bKash PGW requires PHP 5.3 and above. You are using version %s.', 'woocommerce-payment-gateway-bkash'), phpversion()) . '</p></div>';
        } // Check required fields.
        else if (!$this->app_key || !$this->app_secret) {
            echo '<div class="error"><p>' . __('bKash PGW Error: Please enter your app keys and secrets', 'woocommerce-payment-gateway-bkash') . '</p></div>';
        } else if ('BDT' !== get_woocommerce_currency()) {
            echo '<div class="error"><p>' . __('bKash PGW Error: Only supports BDT as currency', 'woocommerce-payment-gateway-bkash') . '</p></div>';
        } // Show message if enabled and FORCE SSL is disabled and WordPress HTTPS plugin is not detected.
        else if ('no' == get_option('woocommerce_force_ssl_checkout') && !class_exists('WordPressHTTPS')) {
            echo '<div class="error"><p>' . sprintf(__('bKash PGW is enabled, but the <a href="%s">force SSL option</a> is disabled; your checkout may not be secure! Please enable SSL and ensure your server has a valid SSL certificate - bKash PGW will only work in sandbox mode.', 'woocommerce-payment-gateway-bkash'), admin_url('admin.php?page=wc-settings&tab=checkout')) . '</p></div>';
        }

        // APP KEY APP SECRET CHECK
	    if(empty($this->app_key) || empty($this->app_secret) || empty($this->username) || empty($this->password)) {
	    	$this->app_key_missing_notice();
	    }
    }
	/**
	 * WooCommerce Payment Gateway App key missing Notice.
	 *
	 * @access public
	 */
	public function app_key_missing_notice() {
		$notice =  '<div class="error woocommerce-message wc-connect"><p>' . sprintf( __( 'Please set bKash PGW credentials for accepting payments!', 'payment-gateway-bkash' ), "Payment Gateway bKash" ) . '</p></div>';
		add_action( 'admin_notices', $notice );
	}

    /**
     * Payment form on checkout page.
     *
     * @TODO:  Use this function to add credit card
     *         and custom fields on the checkout page.
     * @access public
     */
    public function payment_fields()
    {
        $description = $this->get_description();

        if ($this->sandbox == 'yes') {
            $description .= ' ' . __(' (IN SANDBOX)');
        }

        if (!empty($description)) {
            echo wpautop(wptexturize(trim($description)));
        }

        if (is_user_logged_in()) {
            $user_id = get_current_user_id();
            $agreementModel = new Agreement();
            $agreements = $agreementModel->getAgreements($user_id);

            // This includes your custom payment fields.
            include_once(WC_Gateway_bKash()->plugin_path() . '/includes/classes/views/html-payment-fields.php');
        } else {
            if ($this->integration_type === 'tokenized') {
                echo "<p style='color:red'>Please login to complete the payment</p>";
            }
        }
    }

    /**
     * Outputs scripts used for the payment gateway.
     *
     * @access public
     */
    public function payment_scripts()
    {
        // if our payment gateway is disabled, we do not have to enqueue JS too
        if ('no' === $this->enabled) {
            return;
        }
        // do not work with card detailes without SSL unless your website is in a test mode
        if ($this->sandbox === 'no' && !is_ssl()) {
            return;
        }
        // no reason to enqueue JavaScript if API keys are not set
        if (empty($this->app_key) || empty($this->app_secret)) {
            return;
        }

        if (!is_checkout() || !$this->is_available()) {
            return;
        }

        if ($this->integration_type === 'checkout') {
            // let's suppose it is our payment processor JavaScript that allows to obtain a token
            wp_enqueue_script('bKash_jquery', "https://code.jquery.com/jquery-3.3.1.min.js");
            wp_enqueue_script('bKash_js', Operations::CheckoutScriptURL($this->sandbox === 'yes', $this->api_version));

            // and this is our custom JS in your plugin directory that works with token.js
            wp_register_script('woocommerce-payment-gateway-bkash', plugins_url('../../assets/js/checkout.js?' . time(), __FILE__), array('bKash_jquery', 'bKash_js'));

            // in most payment processors you have to use PUBLIC KEY to obtain a token
            wp_localize_script('woocommerce-payment-gateway-bkash', 'bKash_objects', array(
                'apiVersion' => $this->api_version,
                'sandbox' => $this->sandbox,
                'submit_order' => WC_AJAX::get_endpoint('checkout'),
                'ajaxURL' => admin_url('admin-ajax.php'),
                'wcAjaxURL' => $this->siteUrl . "/wc-api/" . $this->EXECUTE_URL,
                'cancelAgreement' => $this->siteUrl . "/wc-api/" . $this->CANCEL_AGREEMENT_URL
            ));

            wp_enqueue_script('woocommerce-payment-gateway-bkash');

        } else {
            // let's suppose it is our payment processor JavaScript that allows to obtain a token
            wp_enqueue_script('bKash_jquery', "https://code.jquery.com/jquery-3.3.1.min.js");

            // and this is our custom JS in your plugin directory that works with token.js
            wp_register_script('woocommerce-payment-gateway-bkash', plugins_url('../../assets/js/tokenized.js?' . time(), __FILE__), array('bKash_jquery'));

            // in most payment processors you have to use PUBLIC KEY to obtain a token
            wp_localize_script('woocommerce-payment-gateway-bkash', 'bKash_objects', array(
                'apiVersion' => $this->api_version,
                'sandbox' => $this->sandbox,
                'cancelAgreement' => $this->siteUrl . "/wc-api/" . $this->CANCEL_AGREEMENT_URL
            ));

            wp_enqueue_script('woocommerce-payment-gateway-bkash');
        }

    }

    /**
     * Check if this gateway is enabled.
     *
     * @access public
     */
    public function is_available()
    {
        if ($this->enabled == 'no') {
            return false;
        }

        if (!is_ssl() && 'yes' != $this->sandbox) {
            return false;
        }

        if (!$this->app_key || !$this->app_secret) {
            return false;
        }

        if ('BDT' !== get_woocommerce_currency()) {
            return false;
        }

        return true;
    }

    public function process_payment($order_id)
    {
        $cbURL = get_site_url() . "/wc-api/" . $this->CALLBACK_URL . '?orderId=' . $order_id;
        $processPayment = new ProcessPayments($this->integration_type);
        return $processPayment->createPayment($order_id, $this->intent, $cbURL);
    }

    public function executePayment()
    {
        check_ajax_referer('bkash-ajax-nonce', 'security');
        //        array(6) { ["action"]=> string(10) "bk_execute" ["security"]=> string(10) "daa93f77f6" ["paymentID"]=> string(20) "IR5VHPT1613573630602" ["woocommerce-login-nonce"]=> NULL ["_wpnonce"]=> NULL ["woocommerce-reset-password-nonce"]=> NULL } Live

        $order_id = sanitize_text_field($_GET['orderId']);
        $payment_id = sanitize_text_field($_GET['paymentID']);
        $invoice_id = sanitize_text_field($_GET['invoiceID']);

        global $woocommerce;
        //To receive order id
        $order = wc_get_order($order_id);

        $this->bKashObj = new ApiComm();
        $execute = $this->bKashObj->executePayment($payment_id);
        if (($execute['success'] ?? false)) {
            $response = $execute['response'] ?? null;
            if ($response) {
                $updated = $transaction->update([
                    'status' => $response['transactionStatus'] ?? 'NO_STATUS_EXECUTE',
                    'trx_id' => $response['trxID'] ?? 'NO_STATUS_EXECUTE'
                ]);

                if ($updated && isset($response['trxID']) && !empty($response['trxID'])) {

                    // Payment complete.
                    if ($response['transactionStatus'] === 'Authorized') {
                        $order->update_status('on-hold');
                    } elseif ($response['transactionStatus'] === 'Completed') {
                        $order->payment_complete();
                    } else {
                        $order->update_status('pending');
                    }

                    // Store the transaction ID for WC 2.2 or later.
                    add_post_meta($order->get_id(), '_transaction_id', $response['trxID'], true);

                    // Add order note.
                    $order->add_order_note(sprintf(__('bKash PGW payment approved (ID: %s)', 'woocommerce-payment-gateway-bkash'), $response['trxID']));

                    if ($this->debug == 'yes') {
                        $this->log->add($this->id, 'bKash PGW payment approved (ID: ' . $response['trxID'] . ')');
                    }

                    // Reduce stock levels.
                    wc_reduce_stock_levels($order_id);

                    if ($this->debug == 'yes') {
                        $this->log->add($this->id, 'Stocked reduced.');
                    }

                    // Remove items from cart.
                    WC()->cart->empty_cart();

                    if ($this->debug == 'yes') {
                        $this->log->add($this->id, 'Cart emptied.');
                    }
                    die();
                } else {
                    // could not update
                    $message = "Could not update transaction status";
                }
            } else {
                // not a valid response
                $message = "Response cannot be processed";
            }
        } else {
            // fail response from bKash.
            $message = "Failed, " . $execute['message'];
        }


        die("Live");
    }

    public function create_payment_callback_process()
    {
        $order_id = sanitize_text_field($_REQUEST['orderId']);

        global $woocommerce;
        //To receive order id
        $order = wc_get_order($order_id);
        if ($order) {

            $cbURL = get_site_url() . "/wc-api/" . $this->CALLBACK_URL . '?orderId=' . $order_id;

            $process = new ProcessPayments($this->integration_type);
            $process->executePayment($this->get_return_url($order), $cbURL);
        } else {
            echo json_encode(array(
                'result' => 'failure',
                'message' => 'Order not found'
            ));
        }
        die();
    }

    public function cancel_agreement_api()
    {
        $message = "";
        $agreement_id = sanitize_text_field($_REQUEST['id']);

        $agreementModel = new Agreement();
        $agreement = $agreementModel->getAgreement($agreement_id);
        $isSameUser = $agreement && ((int)$agreement->getUserID()) === get_current_user_id();
        if ($isSameUser) {
            $api = new ApiComm();
            $cancelUsingAPI = $api->agreementCancel($agreement_id);

            $decoded_response = isset($cancelUsingAPI['response']) && is_string($cancelUsingAPI['response']) ?
                json_decode($cancelUsingAPI['response'], true) : [];
            if (isset($decoded_response['agreementStatus']) && $decoded_response['agreementStatus'] === 'Cancelled') {
                // CANCELED

                $agreementModel->delete($agreement_id);

                echo json_encode(array(
                    'result' => 'success',
                    'message' => 'Token for that agreement has been deleted'
                ));
                die();
            } else if (isset($decoded_response['errorCode'])) {
                $message = $decoded_response['errorMessage'] ?? "Please try later";
            } else {
                $message = "Cannot cancel right now. Please try later";
            }
        } else {
            $message = "Agreement not found";
        }

        echo json_encode(array(
            'result' => 'failure',
            'message' => $message
        ));
        // Return message to customer.
        die();
    }

    public function payment_success()
    {

    }

    public function payment_failure()
    {

    }

    /**
     * Process refunds.
     * WooCommerce 2.2 or later
     *
     * @access public
     * @param int $order_id
     * @param float $amount
     * @param string $reason
     * @return bool|WP_Error
     */
    public function process_refund($order_id, $amount = null, $reason = '')
    {

        $order = wc_get_order($order_id);
        $id = $order->get_transaction_id();

        $response = ''; // TODO: Use this variable to fetch a response from your payment gateway, if any.

        if (is_wp_error($response)) {
            return $response;
        }

        $trxObject = new Transactions();
        $transaction = $trxObject->getTransaction("", $id);
        if ($transaction) {
            if (empty($transaction->getRefundID())) {
                $refundAmount = $amount ?? $transaction->getAmount();


                $comm = new ApiComm();
                $call = $comm->refund(
                    $refundAmount, $transaction->getPaymentID(), $transaction->getTrxID(), $transaction->getOrderID(), $reason ?? 'Refund Purpose'
                );

                if (isset($call['status_code']) && $call['status_code'] === 200) {
                    // response sample
                    // array(7) { ["completedTime"]=> string(32) "2021-02-21T15:40:17:162 GMT+0000" ["transactionStatus"]=> string(9) "Completed" ["originalTrxID"]=> string(10) "8BI704KGJX" ["refundTrxID"]=> string(10) "8BL204KJ0E" ["amount"]=> string(5) "10.00" ["currency"]=> string(3) "BDT" ["charge"]=> string(4) "0.00" }

                    $trx = isset($call['response']) && is_string($call['response']) ? json_decode($call['response'], true) : [];


                    // If any error for tokenized
                    if (isset($trx['statusMessage']) && $trx['statusMessage'] !== 'Successful') {
                        $trx = $trx['statusMessage'];
                    } // If any error for checkout
                    else if (isset($trx['errorCode'])) {
                        $trx = $trx['errorMessage'] ?? '';
                    } else if (isset($trx['transactionStatus']) && $trx['transactionStatus'] === 'Completed') {
                        if (isset($trx['refundTrxID']) && !empty($trx['refundTrxID'])) {
                            $this->refundObj = $trx; // so that another class can get the information

                            $order->update_status('refunded', __('Payment refunded via bKash PGW.', 'woocommerce-payment-gateway-bkash'));
                            $order->add_order_note(sprintf(__('bKash PGW: Refunded %s - Refund ID: %s', 'woocommerce-payment-gateway-bkash'), $refundAmount, $trx['refundTrxID']));


                            $transaction->update([
                                'refund_id' => $trx['refundTrxID'] ?? '',
                                'refund_amount' => $trx['amount'] ?? 0
                            ], ['invoice_id' => $transaction->getInvoiceID()]);

                            if ($this->debug == 'yes') {
                                $this->log->add($this->id, 'bKash PGW order #' . $order_id . ' refunded successfully!');
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
            $trx = "Cannot find the transaction in your database, try again";
        }

        if (is_string($trx)) {
            $this->refundError = $trx;
            $order->add_order_note(__('Error in refunding the order. ' . $trx, 'woocommerce-payment-gateway-bkash'));

            if ($this->debug == 'yes') {
                $this->log->add($this->id, 'Error in refunding the order #' . $order_id . '. bKash PGW response: ' . print_r($response, true));
            }
        }

        return false;
    }

    /**
     * Capture the provided amount.
     *
     * @param float $amount
     * @param WC_Order $order
     *
     * @return bool|WP_Error
     */
    public function capture_charge($amount, $order)
    {
        return new WP_Error('capture-error', sprintf(__('There was an error capturing the charge. Reason: %1$s', 'woocommerce-payment-gateway-bkash')));
    }

    /**
     *
     * @param WC_Order $order
     *
     * @return bool|WP_Error
     */
    public function void_charge($order)
    {
        $id = $order->get_transaction_id();
        try {
            $response = $this->gateway->transaction()->void($id);
            if ($response->success) {
                $this->save_order_meta($response->transaction, $order);
                $order->update_status('cancelled');
                $order->add_order_note(sprintf(__('Transaction %1$s has been voided in Braintree.', 'woo-payment-gateway'), $id));

                return true;
            } else {
                return new WP_Error('capture-error', sprintf(__('There was an error voiding the transaction. Reason: %1$s', 'woo-payment-gateway'), wc_braintree_errors_from_object($response)));
            }
        } catch (Exception $e) {
            return new WP_Error('capture-error', sprintf(__('There was an error voiding the transaction. Reason: %1$s', 'woo-payment-gateway'), wc_braintree_errors_from_object($e)));
        }
    }


	/**
	 * Webhook Integration
	 *
	 * @return void
	 */
	public function webhook() {

		$webhook = new Webhook(wc_get_logger(), true);
		$webhook->processRequest();

		$payload  = (array) json_decode( file_get_contents( 'php://input' ), true );
		$this->log->add($this->id, 'WEBHOOK => BODY: ' . print_r($payload, true));
	}

    /**
     * Output for the order received page.
     *
     * @access public
     * @return void
     */
    public function receipt_page($order)
    {
        echo '<p>' . __('Thank you - your order is now pending payment.', 'woocommerce-payment-gateway-bkash') . '</p>';
    }

    /**
     * Output for the order received page.
     *
     * @access public
     */
    public function thankyou_page($order_id)
    {
        if (!empty($this->instructions)) {
            echo wpautop(wptexturize(wp_kses_post($this->instructions)));
        }

        $this->extra_details($order_id);
    }


    /**
     * Gets the extra details you set here to be
     * displayed on the 'Thank you' page.
     *
     * @access private
     */
    private function extra_details($order_id = '')
    {
        $order = wc_get_order($order_id);
        $id = $order->get_transaction_id();

        echo '<h2>' . __('Payment Details', 'woocommerce-payment-gateway-bkash') . '</h2>' . PHP_EOL;

        // TODO: Place what ever instructions or details the payment gateway needs to display here.
        $trxObj = new Transactions();
        $trx = $trxObj->getTransaction('', $id);
        if ($trx) {
            include_once "Admin/pages/extra_details.php";
        }
    }

    /**
     * Add content to the WC emails.
     *
     * @access public
     * @param WC_Order $order
     * @param bool $sent_to_admin
     * @param bool $plain_text
     */
    public function email_instructions($order, $sent_to_admin, $plain_text = false)
    {
        if (!$sent_to_admin && $this->id === $order->get_payment_method() && $order->has_status('on-hold')) {
            if (!empty($this->instructions)) {
                echo wpautop(wptexturize($this->instructions)) . PHP_EOL;
            }

            $this->extra_details($order->get_id());
        }
    }

    /**
     * Function executed when the 'admin_notices' action is called, here we check if there are notices on
     * our database and display them, after that, we remove the option to prevent notices being displayed forever.
     * @return void
     */

    public function display_flash_notices()
    {
        $notices = get_option("my_flash_notices", array());

        // Iterate through our notices to be displayed and print them.
        foreach ($notices as $notice) {
            printf('<div class="notice notice-%1$s %2$s"><p>%3$s</p></div>',
                $notice['type'],
                $notice['dismissible'],
                $notice['notice']
            );
        }

        // Now we reset our options to prevent notices being displayed forever.
        if (!empty($notices)) {
            delete_option("my_flash_notices");
        }
    }

    /**
     * Get the transaction URL.
     *
     * @TODO   Replace both 'view_transaction_url'\'s.
     *         One for sandbox/testmode and one for live.
     * @param WC_Order $order
     * @return string
     */
    public function get_transaction_url($order)
    {
        // will be used later
        if ($this->sandbox == 'yes') {
            // $this->view_transaction_url = 'https://www.sandbox.payment-gateway.com/?trans_id=%s';
        } else {
            // $this->view_transaction_url = 'https://www.payment-gateway.com/?trans_id=%s';
        }

        return parent::get_transaction_url($order);
    }

} // end class.