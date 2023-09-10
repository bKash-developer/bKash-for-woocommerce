<?php
/**
 * Check Balance
 *
 * @category    Page
 * @package     bkash-for-woocommerce
 * @author      bKash Developer <developer@bkash.com>
 * @copyright   Copyright 2023 bKash Limited. All rights reserved.
 * @license     GNU General Public License version 2 or later; see LICENSE
 * @link        https://bkash.com
 */

use function bKash\PGW\WooCommerceBkashPgw;

if ( isset( $balances ) && is_string( $balances ) ) { ?>
	<div id="message" class="woocommerce-message bKash-hero-div bKash-error-div"><p>
			<?php esc_html_e( ( $balances ?? '' ), 'bkash-for-woocommerce' ); ?>
			</p></div>
	<?php
} elseif ( isset( $balances['organizationBalance'] ) && is_array( $balances['organizationBalance'] ) ) {
	// GOT BALANCES.
	foreach ( $balances['organizationBalance'] as $balance ) {
		?>
		<div class="gateway-banner bKash-hero-div bKash-success">
		<img style="max-width: 90px; margin: 10px 5px" alt="bkash logo check balance" src="
		<?php
		echo esc_url( WooCommerceBkashPgw()->pluginUrl() . '/assets/images/logo.png' );
		?>
		"/>
		<p class="main"><strong>
		<?php
				esc_html_e( $balance['accountTypeName'] ?? '', 'bkash-for-woocommerce' );
		?>
				</strong></p>
		<hr>
		<p>Current Balance:<b>
		<?php
				esc_html_e(
					( $balance['currentBalance'] ?? '' ) . ' ' . ( $balance['currency'] ?? '' ),
					'bkash-for-woocommerce'
				);
		?>
					</b></p>
		<p>Available Balance:<b>
		<?php
				esc_html_e(
					( $balance['availableBalance'] ?? '' ) . ' ' . ( $balance['currency'] ?? '' ),
					'bkash-for-woocommerce'
				);
		?>
					</b></p>
		<hr>
		<ul>
			<li>Account Enabled?<strong>
			<?php
					esc_html_e( $balance['accountStatus'] ?? '', 'bkash-for-woocommerce' );
			?>
					</strong></li>
			<li>Account Name<strong>
			<?php
					esc_html_e( $balance['accountHolderName'] ?? '', 'bkash-for-woocommerce' );
			?>
					</strong></li>
			<li>Last updated<strong>
			<?php
					esc_html_e( $balance['updateTime'] ?? '', 'bkash-for-woocommerce' );
			?>
					</strong></li>
		</ul>
		<p>
		<?php
			$active = ( $balance['accountStatus'] ?? '' ) === 'Active' ? 'button-primary' : 'button';
		?>
			<button class="button button-small 
			<?php
			echo esc_attr( $active );
			?>
			">
			<?php
				esc_html_e( $balance['accountStatus'] ?? '', 'bkash-for-woocommerce' );
			?>
				</button>
		</p></div><?php
	}
}
?>
