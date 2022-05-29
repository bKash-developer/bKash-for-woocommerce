<?php
/**
 * Extra Details
 *
 * @category    Page
 * @package     bkash-for-woocommerce
 * @author      Md. Shahnawaz Ahmed <shahnawaz.ahmed@bkash.com>
 * @copyright   Copyright 2022 bKash Limited. All rights reserved.
 * @license     GNU General Public License version 2 or later; see LICENSE
 * @link        https://bkash.com
 */

if ( isset( $trx ) && $trx ) {
	?>

	<p>Thank you for your payment using bKash online payment gateway. Here is your payment details</p>

	<table id="extra-detail-table" class="woocommerce-table order_details" aria-describedby="extra details">
		<tr>
			<td>Payment Method</td>
			<td>bKash Online payment Gateway</td>
		</tr>
		<tr>
			<td>Transaction ID</td>
			<td><?php
				esc_html_e( $trx->getTrxID() ?? '', "bkash-for-woocommerce" ); ?></td>
		</tr>
		<tr>
			<td>Payment Status</td>
			<td><?php
				esc_html_e( $trx->getStatus() ?? '', "bkash-for-woocommerce" ); ?></td>
		</tr>
	</table>

	<?php
}
?>
