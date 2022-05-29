<?php
/**
 * Refund Transaction
 *
 * @category    Page
 * @package     bkash-for-woocommerce
 * @author      Md. Shahnawaz Ahmed <shahnawaz.ahmed@bkash.com>
 * @copyright   Copyright 2022 bKash Limited. All rights reserved.
 * @license     GNU General Public License version 2 or later; see LICENSE
 * @link        https://bkash.com
 */

use function bKash\PGW\WooCommerceBkashPgw;

?>

<style>
	.wocommerce-message.error {
		border-left-color: #e23e3e !important;
	}
</style>
<h1>
<?php
	esc_html_e( get_admin_page_title(), 'bkash-for-woocommerce' );
?>
	</h1>
<br>
<form action="#" method="post">

	<table id="refund-table" aria-describedby="refund table">
		<tr>
			<td>
				<label for="trxid" class="form-label">Transaction ID *</label>
			</td>
			<td>
				<?php
				$current_trx_id = '';
				if ( ! empty( $fill_trx_id ) ) {
					$current_trx_id = $fill_trx_id;
				} elseif ( ! empty( $trx_id ) ) {
					$current_trx_id = $trx_id;
				}
				?>
				<input name="trxid" type="text" id="trxid" placeholder="Transaction ID" class="form-text-input" value="<?php esc_attr_e( $current_trx_id, 'bkash-for-woocommerce' ); ?> "/>
			</td>
		</tr>
		<tr>
			<td>
				<label for="amount" class="form-label">Amount</label>
			</td>
			<td>
				<input name="amount" type="text" id="amount" placeholder="Amount" class="form-text-input" value="<?php esc_attr_e( $amount ?? '', 'bkash-for-woocommerce' ); ?>"/>
			</td>
		</tr>
		<tr>
			<td>
				<label for="reason" class="form-label">Reason</label>
			</td>
			<td>
				<input name="reason" type="text" id="reason" placeholder="Reason of refund" class="form-text-input">
			</td>
		</tr>
	</table>

	<button class="button button-primary" name="refund" type="submit">Refund</button>
</form>
<br>

<h1>Get Refund Status</h1>
<form action="#" method="post">

	<table id="refund-status-table" aria-describedby="Refund Status Table">
		<tr>
			<td>
				<label for="trxid" class="form-label">Transaction ID *</label>
			</td>
			<td>
				<input name="trxid" type="text" id="trxid" placeholder="Transaction ID" class="form-text-input" value="<?php esc_html_e( $current_trx_id, 'bkash-for-woocommerce' ); ?> "/>
			</td>
		</tr>
	</table>

	<button class="button button-primary" name="check" type="submit">Check</button>
</form>
<br/>

<?php
if ( isset( $trx ) && is_string( $trx ) && ! empty( $trx ) ) {
	// FAILED TO GET BALANCES
	?>
	<div id="message" class="bKash-hero-div woocommerce-message bKash-error">
		<p>
		<?php
			esc_html_e( $trx, 'bkash-for-woocommerce' );
		?>
			</p>
	</div>
	<?php
} elseif ( isset( $trx['refundTrxID'] ) && is_array( $trx ) ) {
	// GOT TRANSACTION
	?>
	<div class="gateway-banner bKash-hero-div bKash-success">
		<img style="max-width: 90px; margin: 10px 5px" alt="bKash logo" src="<?php echo esc_url( WooCommerceBkashPgw()->pluginUrl() . '/assets/images/logo.png' ); ?>"/>
		<p class="main">
			<strong>
				Transaction ID:
				<?php
				esc_html_e( $trx['originalTrxID'] ?? '', 'bkash-for-woocommerce' );
				?>
			</strong>
		</p>
		<hr>
		<p>
			Refund ID:
			<b>
			<?php
				esc_html_e( $trx['refundTrxID'] ?? '', 'bkash-for-woocommerce' );
			?>
				</b>
		</p>
		<p>Amount:
			<b>
				<?php
				esc_html_e(
					( $trx['amount'] ?? '' ) . ' ' . ( $trx['currency'] ?? '' ),
					'bkash-for-woocommerce'
				);
				?>
			</b>
		</p>
		<hr>
		<ul>
			<li>Charge: <strong>
			<?php
					esc_html_e( $trx['charge'] ?? '', 'bkash-for-woocommerce' );
			?>
					</strong></li>
			<li>
				Completed At:
				<strong>
				<?php
					esc_html_e( $trx['completedTime'] ?? '', 'bkash-for-woocommerce' );
				?>
					</strong>
			</li>
		</ul>
		<p>
			<?php
			$btn_class = isset( $trx['transactionStatus'] ) && $trx['transactionStatus'] === 'Completed'
				? 'button-primary' : 'button';
			?>
			<button class="button button-small 
			<?php
			esc_attr_e( $btn_class, 'bkash-for-woocommerce' )
			?>
			">
				Refund Status -
				<?php
				esc_html_e( $trx['transactionStatus'] ?? '', 'bkash-for-woocommerce' );
				?>
			</button>
		</p>
	</div>
	<?php
}
?>
