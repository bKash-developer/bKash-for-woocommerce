<style>
    .wocommerce-message.error {
        border-left-color: #e23e3e !important;
    }
</style>
<h1><?php esc_html_e( get_admin_page_title(), BKASH_FW_TEXT_DOMAIN ); ?></h1>
<br>
<form action="#" method="post">

    <table id="transfer-balance-table" aria-describedby="transfer balance">
        <tr>
            <td>
                <label for="amount" class="form-label">Amount</label>
            </td>
            <td>
                <input name="amount" type="text" id="amount" placeholder="Amount" class="form-text-input"
                       value="<?php esc_html_e( $amount ?? '', BKASH_FW_TEXT_DOMAIN ); ?>"/>
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
        <p><?php esc_html_e( $trx ?? '', BKASH_FW_TEXT_DOMAIN ); ?></p>
    </div>
	<?php

} else if ( isset( $trx['trxID'] ) && is_array( $trx ) ) {
	// GOT TRANSACTION
	?>
    <div class="gateway-banner bKash-hero-div bKash-success">
        <img style="max-width: 90px; margin: 10px 5px"
             alt="bkash logo"
             src="<?php echo \bKash\PGW\WC_Gateway_bKash()->plugin_url() . '/assets/images/logo.png'; ?>"/>
        <p class="main">
            <strong>Transaction ID: <?php esc_html_e( $trx['trxID'] ?? '', BKASH_FW_TEXT_DOMAIN ); ?></strong>
        </p>
        <hr>
        <p>Transfer Type: <b><?php esc_html_e( $trx['transferType'] ?? '', BKASH_FW_TEXT_DOMAIN ); ?></b></p>
        <p>Amount: <b><?php esc_html_e( $trx['amount'] ?? '', BKASH_FW_TEXT_DOMAIN ); ?></b></p>
        <hr>
        <ul>
            <li>Completed At: <strong><?php esc_html_e( $trx['completedTime'] ?? '', BKASH_FW_TEXT_DOMAIN ); ?></strong>
            </li>
        </ul>
        <p>
			<?php $btn_class = isset( $trx['transactionStatus'] ) && $trx['transactionStatus'] === 'Completed' ? 'button-primary' : 'button'; ?>
            <button class="button button-small <?php esc_attr_e( $btn_class, BKASH_FW_TEXT_DOMAIN ); ?>">
                Transfer Status -
				<?php esc_html_e( $trx['transactionStatus'] ?? '', BKASH_FW_TEXT_DOMAIN ); ?>
            </button>
        </p>
    </div>
	<?php
}
?>
