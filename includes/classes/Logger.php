<?php

namespace bKash\PGW;

use WC_Logger;

class Logger {
	private $woo_log;
	private $wp_log;
	private $wc_log;

	public function __construct( $debug = true ) {

		if (class_exists('\\WC_Logger')) {
			$this->log = new WC_Logger();
		} else {
			$this->log = isset($woocommerce) ? $woocommerce->logger() : null;
		}
	}

	public static function debug( $str ) {
		self::write_log( "bKash PGW (DEBUG): " );
		self::write_log( $str );
	}

	private static function write_log( $log ) {
		if ( true === WP_DEBUG ) {
			if ( is_array( $log ) || is_object( $log ) ) {
				error_log( print_r( $log, true ) );
			} else {
				error_log( $log );
			}
		}
	}

	public static function info( $str ) {
		self::write_log( "bKash PGW (INFO): " );
		self::write_log( $str );
	}

	public static function error( $str ) {
		self::write_log( "bKash PGW (ERROR): " );
		self::write_log( $str );
	}

	public static function warning( $str ) {
		self::write_log( "bKash PGW (WARNING): " );
		self::write_log( $str );
	}
}