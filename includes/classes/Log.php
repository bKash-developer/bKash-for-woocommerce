<?php
/**
 * Log Module
 *
 * @category    Log
 * @package     bkash-for-woocommerce
 * @author      Md. Shahnawaz Ahmed <shahnawaz.ahmed@bkash.com>
 * @copyright   Copyright 2022 bKash Limited. All rights reserved.
 * @license     GNU General Public License version 2 or later; see LICENSE
 * @link        https://bkash.com
 */

namespace bKash\PGW;

use WC_Logger;

class Log {
	public static function debug( $str ) {
		self::writeLog( 'DEBUG: ' );
		self::writeLog( $str );
	}

	public static function writeLog( $str ) {
		if ( self::isDebug() === 'yes' ) {
			global $woocommerce;

			$logger = null;
			if ( class_exists( WC_Logger::class ) ) {
				$logger = new WC_Logger();
			} elseif ( ! empty( $woocommerce ) ) {
				$logger = $woocommerce->logger();
			}

			if ( $logger ) {
				$logger->add( 'bKash_PGW_API_LOG', print_r( $str, true ) );
			} elseif ( true === WP_DEBUG ) {
				if ( is_array( $str ) || is_object( $str ) ) {
					error_log( print_r( $str, true ) );
				} else {
					error_log( $str );
				}
			}
		}
	}

	public static function isDebug() {
		$is_debug  = 'no';
		$plugin_id = BKASH_FW_PLUGIN_SLUG;
		$settings  = get_option( 'woocommerce_' . $plugin_id . '_settings' );
		if ( ! is_null( $settings ) ) {
			$is_debug = $settings['debug'] ?? 'no';
		}

		return $is_debug;
	}

	public static function info( $str ) {
		self::writeLog( 'INFO: ' );
		self::writeLog( $str );
	}

	public static function error( $str ) {
		self::writeLog( 'ERROR: ' );
		self::writeLog( $str );
	}
}
