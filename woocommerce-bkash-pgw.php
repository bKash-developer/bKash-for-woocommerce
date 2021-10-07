<?php
namespace bKash\PGW;

/**
 * Plugin Name:       bKash for WooCommerce
 * Plugin URI:        https://developer.bka.sh
 * Description:       A bKash payment gateway plugin for WooCommerce.
 * Version:           1.0.3
 * Author:            bKash Limited
 * Author URI:        http://developer.bka.sh
 * Requires at least: 4.0
 * Tested up to:      4.0
 * Text Domain:       woocommerce-payment-gateway-bkash
 * Domain Path:       languages
 * Network:           false
 * GitHub Plugin URI: https://github.com/bKash-developer/bKash-for-woocommerce
 *
 * WooCommerce Payment Gateway (bKash PGW) is distributed under the terms of the
 * GNU General Public License as published by the Free Software Foundation,
 * either version 2 of the License, or any later version.
 *
 * WooCommerce Payment Gateway (bKash PGW) is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with WooCommerce Payment Gateway (bKash PGW). If not, see <http://www.gnu.org/licenses/>.
 *
 * @package  woocommerce-bkash-pgw
 * @author   bKash Limited
 * @category Core
 */

if (!defined('ABSPATH')) {
    exit;
}

define('BKASH_BASE_PATH', plugin_dir_path(__FILE__));
define('BKASH_BASE_URL', plugin_dir_url(__FILE__));
require BKASH_BASE_PATH . 'vendor/autoload.php';

use bKash\PGW\Admin\AdminDashboard;


/**
 * Initiating tables on plugin activation
*/
register_activation_hook(__FILE__, array(AdminDashboard::GetInstance(), 'BeginInstall'));


/**
 * Adding menus to wp admin menu and generating tables for this plugin
 */
$dashboard = new AdminDashboard();
$dashboard->Initiate();


/**
 * WC Detection
 */
if (!function_exists('bKash_is_woocommerce_active')) {
    function bKash_is_woocommerce_active()
    {
        return WC_Dependencies::bKash_woocommerce_active_check();
    }
}


if (!class_exists('WC_Gateway_bKash')) {

    /**
     * WooCommerce {%Gateway Name%} main class.
     *
     * @class   PGW_BKASH
     * @version 1.0.0
     */

    add_action('plugins_loaded', array(WC_Gateway_bKash::class, 'get_instance'), 0);

} // end if class exists.

/**
 * Returns the main instance of WC_Gateway_bKash to prevent the need to use globals.
 *
 * @return WC_Gateway_bKash
 */
function WC_Gateway_bKash(): WC_Gateway_bKash
{
    return WC_Gateway_bKash::get_instance();
}

