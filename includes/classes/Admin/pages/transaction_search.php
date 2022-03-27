<style>
    .wocommerce-message.error {
        border-left-color: #e23e3e !important;
    }
</style>
<h1><?php esc_attr_e( get_admin_page_title(), BKASH_FW_TEXT_DOMAIN ); ?></h1>
<br>
<form action="#" method="post">
    <label for="trxid" class="form-label">Transaction ID</label>
    <input name="trxid" type="text" id="trxid" placeholder="Transaction ID" class="form-text-input"
           value="<?php esc_attr_e( $trx_id ?? '' , BKASH_FW_TEXT_DOMAIN); ?>">

    <button class="button button-primary" type="submit">Search</button>
</form>
<br>

<?php

if ( isset( $trx ) && is_string( $trx ) ) {
	// FAILED TO GET BALANCES
	?>
    <div id="message" class="bKash-hero-div woocommerce-message bKash-error">
        <p><?php esc_html_e( $trx ?? '' , BKASH_FW_TEXT_DOMAIN); ?></p>
    </div>
	<?php

} else if ( isset( $trx['trxID'] ) && is_array( $trx ) ) {
	// GOT TRANSACTION
	?>
    <div class="gateway-banner bKash-hero-div bKash-success">
        <img style="max-width: 90px; margin: 10px 5px"
             alt="bKash logo transaction search"
             src="<?php echo \bKash\PGW\WC_Gateway_bKash()->plugin_url() . '/assets/images/logo.png'; ?>"/>
        <p class="main">
            <strong>Transaction ID: <?php esc_html_e( $trx['trxID'] ?? '' , BKASH_FW_TEXT_DOMAIN); ?></strong>
        </p>
        <hr>
        <p>Sender: <b><?php esc_html_e( $trx['customerMsisdn'] ?? '' , BKASH_FW_TEXT_DOMAIN); ?></b></p>
        <p>Amount: <b><?php esc_html_e( ( $trx['amount'] ?? '' ) . ' ' . ( $trx['currency'] ?? '' ) , BKASH_FW_TEXT_DOMAIN); ?></b></p>
        <hr>
        <ul>
            <li>Transaction Type: <strong><?php esc_html_e( $trx['transactionType'] ?? '' , BKASH_FW_TEXT_DOMAIN); ?></strong></li>
            <li>Merchant Account: <strong><?php esc_html_e( $trx['organizationShortCode'] ?? '' , BKASH_FW_TEXT_DOMAIN); ?></strong></li>
            <li>Initiated At: <strong><?php esc_html_e( $trx['initiationTime'] ?? '' , BKASH_FW_TEXT_DOMAIN); ?></strong></li>
            <li>Completed At: <strong><?php esc_html_e( $trx['completedTime'] ?? '' , BKASH_FW_TEXT_DOMAIN); ?></strong></li>
        </ul>
        <p>
			<?php $btn_class = isset( $trx['transactionStatus'] ) && $trx['transactionStatus'] === 'Completed' ? 'button-primary' : 'button'; ?>
            <button class="button button-small <?php esc_attr_e( $btn_class , BKASH_FW_TEXT_DOMAIN); ?>">
                Transaction Status -
				<?php esc_html_e( $trx['transactionStatus'] ?? '' , BKASH_FW_TEXT_DOMAIN); ?>
            </button>
        </p>
    </div>
	<?php
}
?>
