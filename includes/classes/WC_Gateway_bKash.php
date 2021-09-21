<?php
namespace bKash\PGW;

use bKash\PGW\Admin\AdminDashboard;

final class WC_Gateway_bKash {

    /**
     * Instance of this class.
     *
     * @access protected
     * @access static
     * @var object
     */
    protected static $instance = null;

    /**
     * Slug
     *
     * @access public
     * @var    string
     */
    public $gateway_slug = 'payment_gateway_bkash';

    /**
     * Text Domain
     *
     * @access public
     * @var    string
     */
    public $text_domain = 'woocommerce-payment-gateway-bkash';

    /**
     * The Gateway Name.
     *
     * @NOTE   Do not put WooCommerce in front of the name. It is already applied.
     * @access public
     * @var    string
     */
    public $name = "Payment Gateway bKash";

    /**
     * Gateway version.
     *
     * @access public
     * @var    string
     */
    public $version = '0.0.1-dev';

    /**
     * The Gateway URL.
     *
     * @access public
     * @var    string
     */
    public $web_url = "https://developer.bka.sh";

    /**
     * The Gateway documentation URL.
     *
     * @access public
     * @var    string
     */
    public $doc_url = "https://developer.bka.sh";

    /**
     * Return an instance of this class.
     *
     * @return object A single instance of this class.
     */
    public static function get_instance() {
        // If the single instance hasn't been set, set it now.
        if( null === self::$instance ) {
            self::$instance = new self;
        }

        return self::$instance;
    }

    /**
     * Throw error on object clone
     *
     * The whole idea of the singleton design pattern is that there is a single
     * object therefore, we don't want the object to be cloned.
     *
     * @since  1.0.0
     * @access public
     * @return void
     */
    public function __clone() {
        // Cloning instances of the class is forbidden
        _doing_it_wrong( __FUNCTION__, __( 'Cheatin&#8217; huh?', 'woocommerce-payment-gateway-bkash' ), $this->version );
    }

    /**
     * Disable unserializing of the class
     *
     * @since  1.0.0
     * @access public
     * @return void
     */
    public function __wakeup() {
        // Un-serializing instances of the class is forbidden
        _doing_it_wrong( __FUNCTION__, __( 'Cheatin&#8217; huh?', 'woocommerce-payment-gateway-bkash' ), $this->version );
    }

    /**
     * Initialize the plugin public actions.
     *
     * @access private
     */
    private function __construct() {
        // Hooks.
        add_filter( 'plugin_action_links_' . plugin_basename( __FILE__ ), array( $this, 'action_links' ) );
        add_filter( 'plugin_row_meta', array( $this, 'plugin_row_meta' ), 10, 2 );
        add_action( 'init', array( $this, 'load_plugin_textdomain' ) );

        // Is WooCommerce activated?
        if( ! in_array( 'woocommerce/woocommerce.php', apply_filters( 'active_plugins', get_option( 'active_plugins' ) ), true ) ) {
            add_action('admin_notices', array( $this, 'woocommerce_missing_notice' ) );
            return false;
        }

		// Check we have the minimum version of WooCommerce required before loading the gateway.
	    if( defined( 'WC_VERSION' ) && version_compare( WC_VERSION, '2.2', '>=' ) ) {
	        if( class_exists( 'WC_Payment_Gateway' ) ) {
	             $this->includes();

	            add_filter( 'woocommerce_payment_gateways', array( $this, 'add_gateway' ) );
	            add_filter( 'woocommerce_currencies', array( $this, 'add_currency' ) );
	            add_filter( 'woocommerce_currency_symbol', array( $this, 'add_currency_symbol' ), 10, 2 );
	        }
	    }
	    else {
	        add_action( 'admin_notices', array( $this, 'upgrade_notice' ) );
	        return false;
	    }
    }


    public function installer() {
        $dashboard = AdminDashboard::GetInstance();
        $dashboard->BeginInstall();
    }

    /**
     * Plugin admin menu in wp
     *
     * @access public
     * */
    public function bKash_dashboard()
    {
        $dashboard = AdminDashboard::GetInstance();
        $dashboard->Initiate();
    }

    function plugin_options_wpse_wpse_91377()
    {
        echo '<h1>OK</h1>';
    }

    /**
     * Plugin action links.
     *
     * @access public
     * @param  mixed $links
     * @return mixed
     */
    public function action_links( $links ) {
        if( current_user_can( 'manage_woocommerce' ) ) {
            $plugin_links = array(
                '<a href="' . admin_url( 'admin.php?page=wc-settings&tab=checkout&section=wc_gateway_' . $this->gateway_slug ) . '">' . __( 'Payment Settings', 'woocommerce-payment-gateway-bkash' ) . '</a>',
            );
            return array_merge( $plugin_links, $links );
        }

        return $links;
    }

    /**
     * Plugin row meta links
     *
     * @access public
     * @param  array $input already defined meta links
     * @param  string $file plugin file path and name being processed
     * @return array $input
     */
    public function plugin_row_meta( $input, $file ) {
        if( plugin_basename( __FILE__ ) !== $file ) {
            return $input;
        }

        $links = array(
            '<a href="' . esc_url( $this->doc_url ) . '">' . __( 'Documentation', 'woocommerce-payment-gateway-bkash' ) . '</a>',
        );

        $input = array_merge( $input, $links );

        return $input;
    }

    /**
     * Load Localisation files.
     *
     * Note: the first-loaded translation file overrides any
     * following ones if the same translation is present.
     *
     * @access public
     * @return void
     */
    public function load_plugin_textdomain() {
        // Set filter for plugin's languages directory
        $lang_dir = dirname( plugin_basename( __FILE__ ) ) . '/languages/';
        $lang_dir = apply_filters( 'woocommerce_' . $this->gateway_slug . '_languages_directory', $lang_dir );

        // Traditional WordPress plugin locale filter
        $locale = apply_filters( 'plugin_locale',  get_locale(), $this->text_domain );
        $mofile = sprintf( '%1$s-%2$s.mo', $this->text_domain, $locale );

        // Setup paths to current locale file
        $mofile_local  = $lang_dir . $mofile;
        $mofile_global = WP_LANG_DIR . '/' . $this->text_domain . '/' . $mofile;

        if( file_exists( $mofile_global ) ) {
            // Look in global /wp-content/languages/plugin-name/ folder
            load_textdomain( $this->text_domain, $mofile_global );
        }
        else if( file_exists( $mofile_local ) ) {
            // Look in local /wp-content/plugins/plugin-name/languages/ folder
            load_textdomain( $this->text_domain, $mofile_local );
        }
        else {
            // Load the default language files
            load_plugin_textdomain( $this->text_domain, false, $lang_dir );
        }
    }

    /**
     * Include files.
     *
     * @access private
     * @return void
     */
    private function includes() {
        // will use if required
    }

    /**
     * This filters the gateway to only supported countries.
     *
     * @access public
     */
    public function gateway_country_base() {
        return apply_filters( 'woocommerce_gateway_country_base', array( 'BD' ) );
    }

    /**
     * Add the gateway.
     *
     * @access public
     * @param  array $methods WooCommerce payment methods.
     * @return array WooCommerce {%Gateway Name%} gateway.
     */
    public function add_gateway( $methods ) {
        $methods[] = PaymentGatewaybKash::class;
        return $methods;
    }

    /**
     * Add the currency.
     *
     * @access public
     * @return array
     */
    public function add_currency( $currencies ) {
        $currencies['BDT'] = __( 'Taka', 'woocommerce-payment-gateway-bkash' );
        return $currencies;
    }

	/**
	 * Add the currency symbol.
	 *
	 * @access public
	 *
	 * @param $currency_symbol
	 * @param $currency
	 *
	 * @return string
	 */
    public function add_currency_symbol( $currency_symbol, $currency ) {
        if($currency === 'BDT') {
        	$currency_symbol = '৳';
        }
        return $currency_symbol;
    }

    /**
     * WooCommerce Fallback Notice.
     *
     * @access public
     * @return string
     */
    public function woocommerce_missing_notice() {
        echo '<div class="error woocommerce-message wc-connect"><p>' . sprintf( __( 'Sorry, <strong>WooCommerce %s</strong> requires WooCommerce to be installed and activated first. Please install <a href="%s">WooCommerce</a> first.', $this->text_domain), $this->name, admin_url('plugin-install.php?tab=search&type=term&s=WooCommerce' ) ) . '</p></div>';
    }

    /**
     * WooCommerce Payment Gateway Upgrade Notice.
     *
     * @access public
     * @return string
     */
    public function upgrade_notice() {
        echo '<div class="updated woocommerce-message wc-connect"><p>' . sprintf( __( 'WooCommerce %s depends on version 2.2 and up of WooCommerce for this gateway to work! Please upgrade before activating.', 'payment-gateway-bkash' ), $this->name ) . '</p></div>';
    }

    /** Helper functions ******************************************************/

    /**
     * Get the plugin url.
     *
     * @access public
     * @return string
     */
    public function plugin_url() {
        return untrailingslashit( BKASH_BASE_URL );
    }

    /**
     * Get the plugin path.
     *
     * @access public
     * @return string
     */
    public function plugin_path() {
        return untrailingslashit(BKASH_BASE_PATH);
    }

} // end if class