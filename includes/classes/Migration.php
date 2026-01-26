<?php
/**
 * Migration utilities for bKash plugin
 */

namespace bKash\PGW;

if ( ! class_exists( 'WC_Payment_Token' ) ) {
	// WC may not be loaded in CLI context; we'll check later.
}

class Migration {
	/**
	 * Register WP-CLI commands if WP_CLI is available.
	 */
	public static function register_commands() {
		if ( defined( 'WP_CLI' ) && class_exists( '\WP_CLI' ) ) {
		\WP_CLI::add_command( 'bkash migrate_tokens', array( __CLASS__, 'migrate_tokens' ) );
		}
	}

	/**
	 * Migrate legacy agreement mappings into WC_Payment_Token records.
	 *
	 * Usage: wp bkash migrate_tokens [--dry-run]
	 *
	 * @param array $args
	 * @param array $assoc_args
	 */
	public static function migrate_tokens( $args, $assoc_args ) {
		global $wpdb;

		$dry_run = isset( $assoc_args['dry-run'] );

		$table = $wpdb->prefix . 'bkash_agreement_mapping';
		if ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) ) !== $table ) {
		\WP_CLI::warning( "Legacy table {$table} does not exist, nothing to migrate." );
			return;
		}

		$rows = $wpdb->get_results( "SELECT * FROM {$table} ORDER BY ID ASC" );
		if ( empty( $rows ) ) {
		\WP_CLI::success( "No legacy agreement records found in {$table}." );
			return;
		}

		$count = 0;
		$skipped = 0;
		foreach ( $rows as $r ) {
			$token_value = $r->agreement_token ?? null;
			$user_id = (int) ( $r->user_id ?? 0 );
			$phone = $r->phone ?? '';
			if ( empty( $token_value ) || $user_id <= 0 ) {
				$skipped++;
				continue;
			}

			// Check for existing token with same token value for this gateway
			$exists = false;
			if ( class_exists( '\WC_Payment_Tokens' ) ) {
				$tokens = \WC_Payment_Tokens::get_tokens( array( 'gateway_id' => BKASH_FW_PLUGIN_SLUG, 'user_id' => $user_id ) );
				if ( is_array( $tokens ) ) {
					foreach ( $tokens as $t ) {
						if ( method_exists( $t, 'get_token' ) && $t->get_token() === $token_value ) {
							$exists = true;
							break;
						}
					}
				}
			}

			if ( $exists ) {
				$skipped++;
				continue;
			}

			if ( $dry_run ) {
				$count++;
				continue;
			}

			if ( ! class_exists( '\WC_Payment_Token' ) ) {
			\WP_CLI::warning( 'WC_Payment_Token not available; ensure WooCommerce is active.' );
				break;
			}

			try {
				$token = new \WC_Payment_Token();
				$token->set_token( $token_value );
				$token->set_gateway_id( BKASH_FW_PLUGIN_SLUG );
				$token->set_user_id( $user_id );
				$token->set_type( 'bKash' );
				$token->add_meta_data( 'phone', $phone );
				$token->save();
				$count++;
			} catch ( \Exception $e ) {
			\WP_CLI::warning( 'Failed to create token for row ID ' . ( $r->ID ?? 'n/a' ) . ': ' . $e->getMessage() );
			}
		}

	\WP_CLI::success( "Migration completed. Tokens created: {$count}. Skipped: {$skipped}." );
	}
}

// Register when file is loaded.
Migration::register_commands();
