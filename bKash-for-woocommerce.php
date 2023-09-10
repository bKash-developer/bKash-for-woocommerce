<?php
/**
 * Plugin Name:       bKash for WooCommerce
 * Plugin URI:        https://developer.bka.sh
 * Description:       A bKash payment gateway plugin for WooCommerce.
 * Version:           1.0.9
 * Author:            bKash Limited
 * Author URI:        http://developer.bka.sh
 * Requires at least: 5.1
 * Tested up to:      6.3.1
 * Text Domain:       bkash-for-woocommerce
 * Domain Path:       languages
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

namespace bKash\PGW;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'BKASH_FW_BASE_PATH', plugin_dir_path( __FILE__ ) );
define( 'BKASH_FW_BASE_URL', plugin_dir_url( __FILE__ ) );
define( 'BKASH_FW_PLUGIN_SLUG', 'bkash-for-woocommerce' );
define( 'BKASH_FW_PLUGIN_VERSION', '1.0.9' );
define( 'BKASH_FW_PLUGIN_BASEPATH', plugin_basename( __FILE__ ) );

define( 'BKASH_FW_WC_API', '/wc-api/' );
define( 'BKASH_FW_COMPLETED_STATUS', 'Completed' );
define( 'BKASH_FW_CANCELLED_STATUS', 'Cancelled' );

require BKASH_FW_BASE_PATH . 'vendor/autoload.php';

use bKash\PGW\Admin\AdminDashboard;

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
