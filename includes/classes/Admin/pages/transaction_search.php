<?php
/**
 * Transaction Search
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
	esc_attr_e( get_admin_page_title(), 'bkash-for-woocommerce' );
?>
	</h1>
<br>
<form action="#" method="post">
	<label for="trxid" class="form-label">Transaction ID</label>
	<input name="trxid" type="text" id="trxid" placeholder="Transaction ID" class="form-text-input" value="<?php esc_attr_e( $trx_id ?? '', 'bkash-for-woocommerce' ); ?>">

	<button class="button button-primary" type="submit">Search</button>
</form>
<br>

<?php

if ( isset( $trx ) && is_string( $trx ) ) {
	// FAILED TO GET BALANCES
	?>
	<div id="message" class="bKash-hero-div woocommerce-message bKash-error">
		<p>
		<?php
			esc_html_e( $trx ?? '', 'bkash-for-woocommerce' );
		?>
			</p>
	</div>
	<?php
} elseif ( isset( $trx ) && is_array( $trx ) && ! empty( $trx['trxId'] ?? $trx['trxID'] ?? '' ) ) {
	$trxId = $trx['trxId'] ?? $trx['trxID'] ?? '';
	$sender = $trx['payerAccount'] ?? $trx['customerMsisdn'] ?? '';
	$merchantAccount = $trx['organizationAccount'] ?? $trx['organizationShortCode'] ?? '';
	// GOT TRANSACTION
	?>
	<div class="gateway-banner bKash-hero-div bKash-success">
		<img style="max-width: 90px; margin: 10px 5px" alt="bKash logo transaction search" src="<?php echo esc_url( \WooCommerceBkashPgw()->pluginUrl() . '/assets/images/logo.png' ); ?>"/>
		<p class="main">
			<strong>Transaction ID: <?php esc_html_e( $trxId, 'bkash-for-woocommerce' ); ?></strong>
		</p>
		<hr>
		<p>Sender: <b><?php esc_html_e( $sender, 'bkash-for-woocommerce' ); ?></b></p>
		<p>
			Amount:
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
			<li>
				Transaction Type:
				<strong><?php esc_html_e( $trx['transactionType'] ?? '', 'bkash-for-woocommerce' ); ?></strong>
			</li>
			<li>
				Merchant Account:
				<strong><?php esc_html_e( $merchantAccount, 'bkash-for-woocommerce' ); ?></strong>
			</li>
			<?php if ( ! empty( $trx['payerType'] ) ) { ?>
			<li>
				Payer Type:
				<strong><?php esc_html_e( $trx['payerType'], 'bkash-for-woocommerce' ); ?></strong>
			</li>
			<?php } ?>
			<?php if ( ! empty( $trx['serviceFee'] ) ) { ?>
			<li>
				Service Fee:
				<strong><?php esc_html_e( $trx['serviceFee'] . ' ' . ( $trx['currency'] ?? '' ), 'bkash-for-woocommerce' ); ?></strong>
			</li>
			<?php } ?>
			<?php if ( ! empty( $trx['creditedAmount'] ) ) { ?>
			<li>
				Credited Amount:
				<strong><?php esc_html_e( $trx['creditedAmount'] . ' ' . ( $trx['currency'] ?? '' ), 'bkash-for-woocommerce' ); ?></strong>
			</li>
			<?php } ?>
			<?php if ( ! empty( $trx['maxRefundableAmount'] ) ) { ?>
			<li>
				Max Refundable:
				<strong><?php esc_html_e( $trx['maxRefundableAmount'] . ' ' . ( $trx['currency'] ?? '' ), 'bkash-for-woocommerce' ); ?></strong>
			</li>
			<?php } ?>
			<li>
				Initiated At:
				<strong><?php esc_html_e( $trx['initiationTime'] ?? '', 'bkash-for-woocommerce' ); ?></strong>
			</li>
			<li>
				Completed At:
				<strong><?php esc_html_e( $trx['completedTime'] ?? '', 'bkash-for-woocommerce' ); ?></strong>
			</li>
		</ul>
		<p>
			<?php
			$btn_class = isset( $trx['transactionStatus'] ) && $trx['transactionStatus'] === 'Completed' ? 'button-primary' : 'button';
			?>
			<button class="button button-small <?php esc_attr_e( $btn_class, 'bkash-for-woocommerce' ); ?>">
				Transaction Status - <?php esc_html_e( $trx['transactionStatus'] ?? '', 'bkash-for-woocommerce' ); ?>
			</button>
		</p>
	</div>
	<?php
}
?>
