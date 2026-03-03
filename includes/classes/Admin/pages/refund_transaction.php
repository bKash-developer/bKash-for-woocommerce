<?php
/**
 * Refund Transaction
 *
 * @category    Page
 * @package     bkash-for-woocommerce
 * @author      bKash Developer <developer@bkash.com>
 * @copyright   Copyright 2023 bKash Limited. All rights reserved.
 * @license     GNU General Public License version 2 or later; see LICENSE
 * @link        https://bkash.com
 */


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
} elseif ( isset( $trx ) && is_array( $trx ) && ! empty( $trx['refundTrxId'] ?? $trx['refundTrxID'] ?? '' ) ) {
	$refundTrxId   = $trx['refundTrxId'] ?? $trx['refundTrxID'] ?? '';
	$originalTrxId = $trx['originalTrxId'] ?? $trx['originalTrxID'] ?? '';
	$refundAmount  = $trx['refundAmount'] ?? $trx['amount'] ?? '';
	$refundStatus  = $trx['refundTransactionStatus'] ?? $trx['transactionStatus'] ?? '';
	// GOT TRANSACTION
	?>
	<div class="gateway-banner bKash-hero-div bKash-success">
		<img style="max-width: 90px; margin: 10px 5px" alt="bKash logo" src="<?php echo esc_url( \WooCommerceBkashPgw()->pluginUrl() . '/assets/images/logo.png' ); ?>"/>
		<p class="main">
			<strong>Transaction ID: <?php esc_html_e( $originalTrxId, 'bkash-for-woocommerce' ); ?></strong>
		</p>
		<hr>
		<p>Refund ID: <b><?php esc_html_e( $refundTrxId, 'bkash-for-woocommerce' ); ?></b></p>
		<p>Amount:
			<b>
				<?php
				esc_html_e(
					$refundAmount . ' ' . ( $trx['currency'] ?? '' ),
					'bkash-for-woocommerce'
				);
				?>
			</b>
		</p>
		<hr>
		<ul>
			<?php if ( ! empty( $trx['charge'] ?? $trx['serviceFee'] ?? '' ) ) { ?>
			<li>Charge: <strong><?php esc_html_e( $trx['charge'] ?? $trx['serviceFee'] ?? '', 'bkash-for-woocommerce' ); ?></strong></li>
			<?php } ?>
			<?php if ( ! empty( $trx['sku'] ) ) { ?>
			<li>SKU: <strong><?php esc_html_e( $trx['sku'], 'bkash-for-woocommerce' ); ?></strong></li>
			<?php } ?>
			<?php if ( ! empty( $trx['reason'] ) ) { ?>
			<li>Reason: <strong><?php esc_html_e( $trx['reason'], 'bkash-for-woocommerce' ); ?></strong></li>
			<?php } ?>
			<li>
				Completed At:
				<strong><?php esc_html_e( $trx['completedTime'] ?? '', 'bkash-for-woocommerce' ); ?></strong>
			</li>
		</ul>
		<p>
			<?php
			$btn_class = $refundStatus === 'Completed' ? 'button-primary' : 'button';
			?>
			<button class="button button-small <?php esc_attr_e( $btn_class, 'bkash-for-woocommerce' ); ?>">
				Refund Status - <?php esc_html_e( $refundStatus, 'bkash-for-woocommerce' ); ?>
			</button>
		</p>
	</div>
	<?php
}
?>
