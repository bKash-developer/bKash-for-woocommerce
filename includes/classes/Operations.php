<?php
/**
 * Operation Module
 *
 * @category    Operation
 * @package     bkash-for-woocommerce
 * @author      bKash Developer <developer@bkash.com>
 * @copyright   Copyright 2023 bKash Limited. All rights reserved.
 * @license     GNU General Public License version 2 or later; see LICENSE
 * @link        https://bkash.com
 */

namespace bKash\PGW;

class Operations {
	public static function checkoutScriptURL( bool $sandbox = false, string $version = '1.2.0-beta' ): string {
		$version = str_replace( 'v', '', $version );

		return 'https://scripts.' . ( $sandbox ? 'sandbox' : 'pay' ) . ".bka.sh/versions/$version/checkout/bKash-checkout" . ( $sandbox ? '-sandbox' : '' ) . '.js';
	}

	/**
	 * @param string $integration_type
	 * @param bool   $isAgreement
	 * @param string $agreementID
	 *
	 * @return string
	 */
	public static function getTokenizedPaymentMode(
		string $integration_type,
		bool $isAgreement = false,
		string $agreementID = ''
	): string {
		// agreement = 0000, paymentWithAgreementID = 0001, paymentWithoutAgreementID = 0011
		$mode = '';
		switch ( $integration_type ) {
			case 'checkout-url':
				$mode = '0011';
				break;
			case 'tokenized':
				$mode = ! empty( $agreementID ) ? '0001' : '0000';
				break;
			case 'tokenized-both':
				// agreement checking required
				$mode = $isAgreement ? '0000' : '0001';
				break;
			default:
				break;
		}

		return $mode;
	}

	/**
	 * @param array  $response
	 * @param string $expectation
	 *
	 * @return array|mixed|string
	 */
	public static function processResponse( array $response, string $expectation = '' ) {
		$resp = '';

		if ( isset( $response['response'] ) ) {
			$response = is_string( $response['response'] ) ?
				json_decode( $response['response'], true ) : array();

			// If any error for tokenized
			if ( isset( $response['statusMessage'] ) && $response['statusMessage'] !== 'Successful' ) {
				$resp = $response['statusMessage'];
			} elseif ( isset( $response['errorCode'] ) ) { // If any error for checkout
				$resp = $response['errorMessage'] ?? '';
			} elseif ( ! empty( $expectation ) ) {
				if ( isset( $response[ $expectation ] ) && ! empty( $response[ $expectation ] ) ) {
					$resp = $response;
				} elseif ( isset( $response['paymentID'] ) && ! empty( $response['paymentID'] ) ) {
					$resp = $response;
				} else {
					$resp = 'expected parameter is not exists in response';
				}
			} else {
				$resp = $response;
			}
		}

		return $resp;
	}
}
