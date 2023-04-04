<?php
/**
 * WooCommerce Dependencies Checker
 *
 * @category    Payment
 * @package     bkash-for-woocommerce
 * @author      bKash Developer <developer@bkash.com>
 * @copyright   Copyright 2023 bKash Limited. All rights reserved.
 * @license     GNU General Public License version 2 or later; see LICENSE
 * @link        https://bkash.com
 */

namespace bKash\PGW;

class WooCommerceDependencies {
	private static $active_plugins;

	public static function checkWooCommerceIsActive(): bool {
		if ( ! self::$active_plugins ) {
			self::init();
		}

		return in_array( 'woocommerce/woocommerce.php', self::$active_plugins, true ) || array_key_exists(
			'woocommerce/woocommerce.php',
			self::$active_plugins
		);
	}

	/**
	 * @return void
	 */
	public static function init() {
		self::$active_plugins = (array) get_option( 'active_plugins', array() );

		if ( is_multisite() ) {
			$siteOptions          = get_site_option( 'active_sitewide_plugins', array() );
			self::$active_plugins = array_merge( self::$active_plugins, $siteOptions );
		}
	}
}
