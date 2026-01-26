<?php
/**
 * Log Module - WC_Logger Wrapper
 *
 * @category    Log
 * @package     bkash-for-woocommerce
 * @author      bKash Developer <developer@bkash.com>
 * @copyright   Copyright 2023 bKash Limited. All rights reserved.
 * @license     GNU General Public License version 2 or later; see LICENSE
 * @link        https://bkash.com
 */

namespace bKash\PGW;

class Log {
    /**
     * Log source name - appears in WooCommerce → Status → Logs
     */
    const LOG_SOURCE = 'bkash-pgw';

    /**
     * Get WC_Logger instance
     *
     * @return \WC_Logger|\WC_Logger_Interface|null
     */
    private static function get_logger() {
        if ( function_exists( 'wc_get_logger' ) ) {
            return wc_get_logger();
        }
        return null;
    }

    /**
     * Check if debug mode is enabled
     *
     * @return bool
     */
    private static function is_debug_enabled(): bool {
        $settings = get_option( 'woocommerce_' . BKASH_FW_PLUGIN_SLUG . '_settings' );
        return isset( $settings['debug'] ) && $settings['debug'] === 'yes';
    }

    /**
     * Get log context
     *
     * @return array
     */
    private static function get_context(): array {
        return array( 'source' => self::LOG_SOURCE );
    }

    /**
     * Log debug message
     *
     * @param string|array|object $message
     */
    public static function debug( $message ) {
        if ( ! self::is_debug_enabled() ) {
            return;
        }

        $logger = self::get_logger();
        if ( $logger ) {
            $formatted = self::format_message( $message );
            $logger->debug( $formatted, self::get_context() );
        }
    }

    /**
     * Log info message
     *
     * @param string|array|object $message
     */
    public static function info( $message ) {
        if ( ! self::is_debug_enabled() ) {
            return;
        }

        $logger = self::get_logger();
        if ( $logger ) {
            $formatted = self::format_message( $message );
            $logger->info( $formatted, self::get_context() );
        }
    }

    /**
     * Log error message (always logged, even if debug is off)
     *
     * @param string|array|object $message
     */
    public static function error( $message ) {
        $logger = self::get_logger();
        if ( $logger ) {
            $formatted = self::format_message( $message );
            $logger->error( $formatted, self::get_context() );
        } elseif ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
            // Fallback to error_log if WC_Logger not available
            error_log( '[bKash PGW Error] ' . self::format_message( $message ) );
        }
    }

    /**
     * Log warning message
     *
     * @param string|array|object $message
     */
    public static function warning( $message ) {
        if ( ! self::is_debug_enabled() ) {
            return;
        }

        $logger = self::get_logger();
        if ( $logger ) {
            $formatted = self::format_message( $message );
            $logger->warning( $formatted, self::get_context() );
        }
    }

    /**
     * Format message for logging
     *
     * @param mixed $message
     * @return string
     */
    private static function format_message( $message ): string {
        if ( is_string( $message ) ) {
            return $message;
        }

        if ( is_wp_error( $message ) ) {
            return sprintf(
                'WP_Error: %s (Code: %s)',
                $message->get_error_message(),
                $message->get_error_code()
            );
        }

        if ( $message instanceof \Exception || $message instanceof \Throwable ) {
            return sprintf(
                'Exception: %s in %s:%d',
                $message->getMessage(),
                $message->getFile(),
                $message->getLine()
            );
        }

        // For arrays/objects, use print_r for readability
        return print_r( $message, true );
    }

	/**
     * Redact sensitive data before logging
     *
     * @param mixed $data
     * @return mixed
     */
    public static function redact_sensitive( $data ) {
        if ( ! is_array( $data ) ) {
            return $data;
        }

        $sensitive_keys = array(
            'password',
            'username',
            'app_secret',
            'authorization',
            'id_token',
            'refresh_token',
            'access_token',
            'pin',
            'otp',
        );

        foreach ( $data as $key => $value ) {
            $lower_key = strtolower( (string) $key );
            if ( in_array( $lower_key, $sensitive_keys, true ) ) {
                $data[ $key ] = '***REDACTED***';
            } elseif ( is_array( $value ) ) {
                $data[ $key ] = self::redact_sensitive( $value );
            }
        }

        return $data;
    }

    /**
     * Legacy method - kept for backward compatibility
     * @deprecated Use specific methods (debug, info, error) instead
     */
    public static function writeLog( $str ) {
        self::debug( $str );
    }

    /**
     * Legacy method - kept for backward compatibility
     * @deprecated Use is_debug_enabled() instead
     */
    public static function isDebug() {
        return self::is_debug_enabled() ? 'yes' : 'no';
    }
}
