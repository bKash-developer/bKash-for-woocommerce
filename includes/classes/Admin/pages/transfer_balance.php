<?php
/**
 * Transfer Balance
 *
 * @category    Page
 * @package     bkash-for-woocommerce
 * @author      bKash Developer <developer@bkash.com>
 * @copyright   Copyright 2023 bKash Limited. All rights reserved.
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

	<table id="transfer-balance-table" aria-describedby="transfer balance">
		<tr>
			<td>
				<label for="amount" class="form-label">Amount</label>
			</td>
			<td>
				<input name="amount" type="text" id="amount" placeholder="Amount" class="form-text-input" value="<?php esc_html_e( $amount ?? '', 'bkash-for-woocommerce' ); ?>"/>
			</td>
		</tr>
		<tr>
			<td>
				<label for="transfer_type" class="form-label">Transfer Type</label>
			</td>
			<td>
				<select name="transfer_type" id="transfer_type" class="form-select">
					<option value="Collection2Disbursement">From collection account to disbursement account</option>
					<option value="Disbursement2Collection">From disbursement account to collection account</option>
				</select>
			</td>
		</tr>
	</table>

	<button class="button button-primary" type="submit">Transfer</button>
</form>
<br>

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
} elseif ( isset( $trx['trxID'] ) && is_array( $trx ) ) {
	// GOT TRANSACTION
	?>
	<div class="gateway-banner bKash-hero-div bKash-success">
		<img style="max-width: 90px; margin: 10px 5px" alt="bkash logo" src="<?php echo esc_url( WooCommerceBkashPgw()->pluginUrl() . '/assets/images/logo.png' ); ?>"/>
		<p class="main">
			<strong>Transaction ID: 
			<?php
				esc_html_e( $trx['trxID'] ?? '', 'bkash-for-woocommerce' );
			?>
				</strong>
		</p>
		<hr>
		<p>Transfer Type: <b>
		<?php
				esc_html_e( $trx['transferType'] ?? '', 'bkash-for-woocommerce' );
		?>
				</b></p>
		<p>Amount: <b>
		<?php
				esc_html_e( $trx['amount'] ?? '', 'bkash-for-woocommerce' );
		?>
				</b></p>
		<hr>
		<ul>
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
			esc_attr_e( $btn_class, 'bkash-for-woocommerce' );
			?>
			">
				Transfer Status -
				<?php
				esc_html_e( $trx['transactionStatus'] ?? '', 'bkash-for-woocommerce' );
				?>
			</button>
		</p>
	</div>
	<?php
}
?>
