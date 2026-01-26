<?php
/**
 * Plugin Name:       bKash for WooCommerce
 * Plugin URI:        https://developer.bka.sh
 * Description:       A bKash payment gateway plugin for WooCommerce.
 * Version:           2.0.0
 * Author:            bKash Limited
 * Author URI:        http://developer.bka.sh
 * Requires at least: 6.0
 * Tested up to:      6.9
 * Requires PHP:      8.0
 * WC requires at least: 7.0
 * WC tested up to:   10.4
 * Text Domain:       bkash-for-woocommerce
 * Domain Path:       /languages
 * Network:           false
 * License:           GPL v2 or later
 * License URI:       http://www.gnu.org/licenses/gpl-2.0.txt
 * GitHub Plugin URI: https://github.com/bKash-developer/bKash-for-woocommerce
 *
 * WooCommerce Payment Gateway (bKash for WooCommerce) is distributed under the terms of the
 * GNU General Public License as published by the Free Software Foundation,
 * either version 2 of the License, or any later version.
 *
 * WooCommerce Payment Gateway (bKash for WooCommerce) is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with WooCommerce Payment Gateway (bKash PGW). If not, see <http://www.gnu.org/licenses/>.
 *
 * @package  bkash-for-woocommerce
 * @author   bKash Limited
 * @category Payment
 */


// PHP version check
if ( version_compare( PHP_VERSION, '8.0', '<' ) ) {
    add_action( 'admin_notices', function() {
        echo '<div class="error"><p><strong>bKash for WooCommerce</strong> requires PHP 8.0 or higher. You are running PHP ' . esc_html( PHP_VERSION ) . '. Please upgrade PHP.</p></div>';
    } );
    return;
}

define( 'BKASH_FW_BASE_PATH', plugin_dir_path( __FILE__ ) );
define( 'BKASH_FW_BASE_URL', plugin_dir_url( __FILE__ ) );
define( 'BKASH_FW_PLUGIN_SLUG', 'bkash-for-woocommerce' );
define( 'BKASH_FW_PLUGIN_VERSION', '2.0.0' );
define( 'BKASH_FW_PLUGIN_BASEPATH', plugin_basename( __FILE__ ) );

define( 'BKASH_FW_WC_API', '/wc-api/' );
define( 'BKASH_FW_COMPLETED_STATUS', 'Completed' );
define( 'BKASH_FW_CANCELLED_STATUS', 'Cancelled' );

require BKASH_FW_BASE_PATH . 'vendor/autoload.php';
// Load admin helpers
if ( is_admin() ) {
	require_once BKASH_FW_BASE_PATH . 'includes/classes/Admin/OrderActions.php';
}

// Load migration utilities for WP-CLI only.
if ( defined( 'WP_CLI' ) && WP_CLI ) {
	require_once BKASH_FW_BASE_PATH . 'includes/classes/Migration.php';
}

use bKash\PGW\Admin\AdminDashboard;
use bKash\PGW\WooCommerceBkashPgw;

/**
 * Initiating tables on plugin activation
 */
register_activation_hook( __FILE__, array( AdminDashboard::getInstance(), 'beginInstall' ) );


if ( ! class_exists( 'WooCommerceBkashPgw' ) ) {
	/**
	 * WooCommerce bKash Payment Gateway main class.
	 *
	 * @class WooCommerceBkashPgw
	 */

	add_action( 'plugins_loaded', array( WooCommerceBkashPgw::class, 'getInstance' ), 0 );
} // end if class exists.


if ( ! function_exists( 'WooCommerceBkashPgw' ) ) {
	/**
	 * Returns the main instance of WooCommerceBkashPgw to prevent the need to use globals.
	 *
	 * @return WooCommerceBkashPgw
	 */
	function WooCommerceBkashPgw(): WooCommerceBkashPgw {
		return WooCommerceBkashPgw::getInstance();
	}
}

/**
 * Adding menus to wp admin menu and generating tables for this plugin
 */
$dashboard = new AdminDashboard();
$dashboard->initiate();

/**
 * Declare bKash compatibility with WooCommerce HPOS
 */
add_action( 'before_woocommerce_init', function() {
	if ( class_exists( 'Automattic\\WooCommerce\\Utilities\\FeaturesUtil' ) ) {
		\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'custom_order_tables', plugin_basename( __FILE__ ), true );
	}
} );
