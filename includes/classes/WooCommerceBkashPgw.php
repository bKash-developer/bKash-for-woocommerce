<?php
/**
 * WooCommerce bKash PGW
 *
 * @category    Payment
 * @package     bkash-for-woocommerce
 * @author      Md. Shahnawaz Ahmed <shahnawaz.ahmed@bkash.com>
 * @copyright   Copyright 2022 bKash Limited. All rights reserved.
 * @license     GNU General Public License version 2 or later; see LICENSE
 * @link        https://bkash.com
 */

namespace bKash\PGW;

use bKash\PGW\Admin\AdminDashboard;
use bKash\PGW\Admin\AdminUtility;

class WooCommerceBkashPgw {
	/**
	 * Instance of this class.
	 *
	 * @access protected
	 * @access static
	 * @var object | null
	 */
	private static $instance;


	/**
	 * The Gateway Name.
	 *
	 * @NOTE   Do not put WooCommerce in front of the name. It is already applied.
	 * @access public
	 * @var    string
	 */
	public $name = 'Payment Gateway (bKash for WooCommerce)';

	/**
	 * Gateway version.
	 *
	 * @access public
	 * @var    string
	 */
	public $version = BKASH_FW_PLUGIN_VERSION;

	/**
	 * The Gateway URL.
	 *
	 * @access public
	 * @var    string
	 */
	public $web_url = 'https://developer.bka.sh';

	/**
	 * The Gateway documentation URL.
	 *
	 * @access public
	 * @var    string
	 */
	public $doc_url = 'https://developer.bka.sh/docs';

	/**
	 * Initialize the plugin public actions.
	 *
	 * @access private
	 */
	private function __construct() {
		// Hooks.
		add_filter( 'plugin_action_links_' . BKASH_FW_PLUGIN_BASEPATH, array( $this, 'actionLinks' ), 10, 5 );
		add_filter( 'plugin_row_meta', array( $this, 'pluginRowMeta' ), 10, 2 );
		add_action( 'init', array( $this, 'loadPluginTextDomain' ) );

		// Is WooCommerce activated?
		if ( ! WooCommerceDependencies::checkWooCommerceIsActive() ) {
			add_action( 'admin_notices', array( $this, 'woocommerceMissingNotice' ) );
			AdminUtility::addFlashNotice( 'Woocommerce is not active, bKash Payment plugin requires it' );
		}

		// Check we have the minimum version of WooCommerce required before loading the gateway.
		if ( defined( 'WC_VERSION' ) && version_compare( WC_VERSION, '2.2', '>=' ) ) {
			if ( class_exists( 'WC_Payment_Gateway' ) ) {
				add_filter( 'woocommerce_payment_gateways', array( $this, 'addGateway' ) );
				add_filter( 'woocommerce_currencies', array( $this, 'addCurrency' ) );
				add_filter( 'woocommerce_currency_symbol', array( $this, 'addCurrencySymbol' ), 10, 2 );
			}
		} else {
			add_action( 'admin_notices', array( $this, 'upgradeNotice' ) );
			AdminUtility::addFlashNotice( 'An upgraded version of WooCommerce is required' );
		}
	}

	/**
	 * Throw error on object clone
	 *
	 * The whole idea of the singleton design pattern is that there is a single
	 * object therefore, we don't want the object to be cloned.
	 *
	 * @return void
	 * @since  1.0.0
	 * @access public
	 */
	public function __clone() {
		// Cloning instances of the class is forbidden
		_doing_it_wrong( __FUNCTION__, 'Cheating&#8217; huh?', $this->version );
	}

	/**
	 * Disable un-serializing of the class
	 *
	 * @return void
	 * @since  1.0.0
	 * @access public
	 */
	public function __wakeup() {
		// Un-serializing instances of the class is forbidden
		_doing_it_wrong( __FUNCTION__, 'Cheating&#8217; huh?', $this->version );
	}

	final public function installer() {
		$dashboard = AdminDashboard::GetInstance();
		$dashboard->BeginInstall();
	}

	/**
	 * Return an instance of this class.
	 *
	 * @return object A single instance of this class.
	 */
	public static function getInstance(): WooCommerceBkashPgw {
		// If the single instance hasn't been set, set it now.
		if ( null === self::$instance ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	/**
	 * Plugin action links.
	 *
	 * @access public
	 *
	 * @param mixed $links
	 *
	 * @return mixed
	 */
	final public function actionLinks( array $links ): array {
		if ( current_user_can( 'manage_woocommerce' ) ) {
			$admin_setting_path = 'admin.php?page=wc-settings&tab=checkout&section=';
			$config_url         = esc_url( admin_url( $admin_setting_path . BKASH_FW_PLUGIN_SLUG ) );
			$plugin_links       = array(
				'<a href="' . esc_attr( $config_url ) . '">Payment Settings</a>',
			);

			return array_merge( $plugin_links, $links );
		}

		return $links;
	}

	/**
	 * Plugin row meta links
	 *
	 * @access public
	 *
	 * @param array  $input already defined meta links
	 * @param string $file plugin file path and name being processed
	 *
	 * @return array $input
	 */
	final public function pluginRowMeta( array $input, string $file ): array {
		if ( BKASH_FW_PLUGIN_BASEPATH !== $file ) {
			return $input;
		}

		$links = array(
			'<a href="' . esc_url( $this->web_url ) . '">Developer Page</a>',
			'<a href="' . esc_url( $this->doc_url ) . '">Documentation</a>',
		);

		return array_merge( $input, $links );
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
	final public function loadPluginTextDomain() {
		// Set filter for plugin's languages directory
		$lang_dir = dirname( plugin_basename( __FILE__ ) ) . '/languages/';
		$lang_dir = apply_filters( 'woocommerce_' . BKASH_FW_PLUGIN_SLUG . '_languages_directory', $lang_dir );

		// Traditional WordPress plugin locale filter
		$locale = apply_filters( 'plugin_locale', get_locale(), 'bkash-for-woocommerce' );
		$moFile = sprintf( '%1$s-%2$s.mo', 'bkash-for-woocommerce', $locale );

		// Setup paths to current locale file
		$moFileLocal  = $lang_dir . $moFile;
		$moFileGlobal = WP_LANG_DIR . '/' . 'bkash-for-woocommerce' . '/' . $moFile;

		if ( file_exists( $moFileGlobal ) ) {
			// Look in global /wp-content/languages/plugin-name/ folder
			load_textdomain( 'bkash-for-woocommerce', $moFileGlobal );
		} elseif ( file_exists( $moFileLocal ) ) {
			// Look in local /wp-content/plugins/plugin-name/languages/ folder
			load_textdomain( 'bkash-for-woocommerce', $moFileLocal );
		} else {
			// Load the default language files
			load_plugin_textdomain( 'bkash-for-woocommerce', false, $lang_dir );
		}
	}


	/**
	 * Add the gateway.
	 *
	 * @access public
	 *
	 * @param array $methods WooCommerce's payment methods.
	 *
	 * @return array
	 */
	final public function addGateway( array $methods ): array {
		$methods[] = PaymentGatewayBkash::class;

		return $methods;
	}

	/**
	 * Add the currency.
	 *
	 * @access public
	 *
	 * @param array $currencies
	 *
	 * @return array
	 */
	final public function addCurrency( array $currencies ): array {
		$currencies['BDT'] = 'Taka';

		return $currencies;
	}

	/**
	 * Add the currency symbol.
	 *
	 * @access public
	 *
	 * @param string $currency_symbol
	 * @param string $currency
	 *
	 * @return string
	 */
	final public function addCurrencySymbol( string $currency_symbol, string $currency ): string {
		if ( $currency === 'BDT' ) {
			$currency_symbol = '৳';
		}

		return $currency_symbol;
	}

	/**
	 * WooCommerce Fallback Notice.
	 *
	 * @access public
	 */
	final public function woocommerceMissingNotice() {
		$pluginSearchUrl = esc_url( admin_url( 'plugin-install.php?tab=search&type=term&s=WooCommerce' ) );
		$linkOfSearchUrl = "<a href='$pluginSearchUrl'>WooCommerce</a>";

		echo wp_kses_post(
			'<div class="error woocommerce-message wc-connect"><p>' . sprintf(
				'Sorry, <strong>WooCommerce %s</strong> requires WooCommerce to be installed and activated first.
 						Please install %s first.',
				esc_html( $this->name ),
				$linkOfSearchUrl
			) . '</p></div>'
		);
	}

	/**
	 * WooCommerce Payment Gateway Upgrade Notice.
	 *
	 * @access public
	 */
	final public function upgradeNotice() {
		echo wp_kses_post(
			'<div class="updated woocommerce-message wc-connect"><p>' . sprintf(
				'WooCommerce %s depends on version 2.2 and up of WooCommerce for this gateway to work! 
                Please upgrade before activating.',
				esc_html( $this->name )
			) . '</p></div>'
		);
	}

	/** Helper functions ******************************************************/

	/**
	 * Get the plugin url.
	 *
	 * @access public
	 * @return string
	 */
	final public function pluginUrl(): string {
		return untrailingslashit( BKASH_FW_BASE_URL );
	}

	/**
	 * Get the plugin path.
	 *
	 * @access public
	 * @return string
	 */
	final public function pluginPath(): string {
		return untrailingslashit( BKASH_FW_BASE_PATH );
	}
} // end if class
