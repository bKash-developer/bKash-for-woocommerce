<?php
/**
 * Admin Utility
 *
 * @category    Utility
 * @package     bkash-for-woocommerce
 * @author      Md. Shahnawaz Ahmed <shahnawaz.ahmed@bkash.com>
 * @copyright   Copyright 2022 bKash Limited. All rights reserved.
 * @license     GNU General Public License version 2 or later; see LICENSE
 * @link        https://bkash.com
 */

namespace bKash\PGW\Admin;

use bKash\PGW\Utils;

class AdminUtility {
	private static $instance;

	public static function getInstance(): AdminUtility {
		if ( ! isset( self::$instance ) ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	public static function loadTable(
		string $title,
		string $tbl_name,
		array $columns = array(),
		array $filters = array(),
		array $actions = array()
	) {
		global $wpdb;
		$primaryColumn = 'ID';
		$table_name    = Utils::safeSqlString( $wpdb->prefix . $tbl_name );
		$pageNumber    = Utils::hasGetField( "pagenum" ) ? absint( Utils::safeGetValue( "pagenum" ) ) : 1;
		$limit         = BKASH_FW_TABLE_LIMIT;
		$offset        = ( $pageNumber - 1 ) * $limit;

		$queryValue[] = 0; // primary columnn value for %d
		$whereQuery   = "$primaryColumn > %d ";

		$filterColumns    = [];
		$countColumnValue = [];

		if ( count( $filters ) > 0 ) {
			foreach ( $filters as $key => $filter ) {
				$input = Utils::safeGetValue( $key );
				if ( $input ) {
					$filterColumns[] = Utils::safeSqlString( $key );
					$queryValue[]    = Utils::safeSqlString( $input );
				}
			}

			if ( count( $filterColumns ) > 0 ) {
				$whereQuery .= 'AND ' . implode( ' = %s AND ', $filterColumns ) . ' = %s ';
			}

			$countColumnValue = $queryValue; // for counting purpose keeping where info only
			$queryValue[]     = $offset; // value of offset as %s
			$queryValue[]     = $limit; // value of limit as %s
		}

		$sqlQuery   = "SELECT * from $table_name where $whereQuery ORDER BY `ID` DESC limit %d, %d";
		$countQuery = "SELECT count(*) as total from $table_name where $whereQuery";

		$rows     = $wpdb->get_results(
			$wpdb->prepare( $sqlQuery, $queryValue )
		);
		$rowcount = $wpdb->num_rows ?? 0;

		$total        = $wpdb->get_var(
			$wpdb->prepare( $countQuery, $countColumnValue )
		);
		$num_of_pages = ceil( $total / $limit );

		$page_links = paginate_links(
			array(
				'base'      => add_query_arg( 'pagenum', '%#%' ),
				'format'    => '',
				'prev_text' => __( '&laquo;', 'bkash-for-woocommerce' ),
				'next_text' => __( '&raquo;', 'bkash-for-woocommerce' ),
				'total'     => $num_of_pages,
				'current'   => $pageNumber
			)
		);

		include_once "pages/table.php";
	}

	public static function getBKashOptions( string $plugin_id, string $key ) {
		$option_value = false;
		$options      = get_option( 'woocommerce_' . $plugin_id . '_settings' );

		if ( ! is_null( $options ) && isset( $options[ $key ] ) ) {
			if ( $options[ $key ] === 'yes' || $options[ $key ] === 'no' ) {
				$option_value = $options[ $key ] === 'yes';
			} else {
				$option_value = $options[ $key ];
			}
		}

		return $option_value;
	}

	public static function validateResponse( array $apiResp = array(), array $specificField = array() ): array {
		$feedback = array(
			'valid'    => false,
			'message'  => '',
			'response' => []
		);


		if ( isset( $apiResp['status_code'], $apiResp['response'] ) && $apiResp['status_code'] === 200 ) {
			$response = $apiResp['response'];
			if ( is_string( $response ) ) {
				$response = json_decode( $response, true );
			}

			if ( isset( $response['errorMessage'] ) ) {
				$feedback['message'] = $response['errorMessage'];
			} elseif ( isset( $response['statusMessage'] ) && $response['statusMessage'] !== 'Successful' ) {
				$feedback['message'] = $response['statusMessage'];
			} else {
				if ( count( $specificField ) > 0 ) {
					if ( $response[ key( $specificField ) ] === $specificField[ key( $specificField ) ] ) {
						$feedback['valid'] = true;
					} else {
						$feedback['message'] = key( $specificField ) . " is not present or not matching with the value " . $specificField[ key( $specificField ) ];
					}
				} else {
					$feedback['valid'] = true;
				}

				$feedback['response'] = $response;
			}
		} else {
			$feedback['message'] = "Action cannot be performed at bKash server right now, try again";
		}

		return $feedback;
	}


	public static function redirectToPage( string $url = "" ) {
		wp_safe_redirect( esc_url( $url ) );
	}

	public static function addFlashNotice( string $notice = "", string $type = "warning", bool $dismissible = true ) {
		$notices = get_option( "bKash_flash_notices", array() );

		$dismissible_text = ( $dismissible ) ? "is-dismissible" : "";

		$notices[] = array(
			"notice"      => $notice,
			"type"        => $type,
			"dismissible" => $dismissible_text
		);

		update_option( "bKash_flash_notices", $notices );
	}


	/**
	 * @param string $str
	 * @param string $separator
	 *
	 * @return string
	 */
	public static function keyToLabel( string $str, string $separator = "_" ): string {
		$str = str_replace( $separator, " ", $str );

		return ucwords( $str );
	}

	/**
	 * @param $row
	 * @param array $column
	 *
	 * @return bool
	 */
	public static function ifRefundValueIsPresent( $row, array $column ): bool {
		return isset( $column[0] ) && str_contains( strtolower( $column[0] ), "refund" ) && ! empty( $row->{$column[0]} );
	}

	public static function setStatusColor( string $status ): string {
		$color = "#909090";

		if ( stripos( $status, "cancel" ) !== false ) {
			$color = "#f4a938";
		} elseif ( stripos( $status, "complete" ) !== false ) {
			$color = "#1dae5b";
		} elseif ( stripos( $status, "fail" ) !== false ) {
			$color = "#ff4136";
		} elseif ( stripos( $status, "auth" ) !== false ) {
			$color = "#0b608a";
		}

		return $color;
	}
}
