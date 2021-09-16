<style>
    .wocommerce-message.error {
        border-left-color: #e23e3e !important;
    }
</style>
<h1><?php echo get_admin_page_title(); ?></h1>
<br>
<form action="#" method="post">
    <label for="trxid" class="form-label">Transaction ID</label>
    <input name="trxid" type="text" id="trxid" placeholder="Transaction ID" class="form-text-input"
           value="<?php echo esc_attr($trx_id) ?? ''; ?>">

    <button class="button button-primary" type="submit">Search</button>
</form>
<br>

<?php
define("BK_STRONG_START", " <strong>");
define("BK_STRONG_END", "</strong> ");

if (isset($trx) && is_string($trx)) {
    // FAILED TO GET BALANCES
    ?>
    <div id="message" class="bKash-hero-div woocommerce-message bKash-error">
        <p><?php echo esc_html($trx) ?? '' ?></p>
    </div>
    <?php

} else if (isset($trx['trxID']) && is_array($trx)) {
    // GOT TRANSACTION
    ?>
    <div class="gateway-banner bKash-hero-div bKash-success">
        <img style="max-width: 90px; margin: 10px 5px"
             alt="bKash logo transaction search"
             src="<?php echo \bKash\PGW\WC_Gateway_bKash()->plugin_url() . '/assets/images/logo.png'; ?>"/>
        <p class="main">
            <strong>Transaction ID: <?php _e($trx['trxID'] ?? '', 'woocommerce-payment-gateway-bkash'); ?></strong></p>
        <hr>
        <p><?php _e('Sender: <b>' . ($trx['customerMsisdn'] ?? '') . '</b>', 'woocommerce-payment-gateway-bkash'); ?></p>
        <p><?php _e('Amount: <b>' . ($trx['amount'] ?? '') . ' ' . ($trx['currency'] ?? '') . '</b>', 'woocommerce-payment-gateway-bkash'); ?></p>
        <hr>
        <ul>
            <li><?php echo __('Transaction Type: ', 'woocommerce-payment-gateway-bkash') . BK_STRONG_START . ($trx['transactionType'] ?? '') . BK_STRONG_END; ?></li>
            <li><?php echo __('Merchant Account: ', 'woocommerce-payment-gateway-bkash') . BK_STRONG_START . ($trx['organizationShortCode'] ?? '') . BK_STRONG_END; ?></li>
            <li><?php echo __('Initiated At: ', 'woocommerce-payment-gateway-bkash') . BK_STRONG_START . ($trx['initiationTime'] ?? '') . BK_STRONG_END; ?></li>
            <li><?php echo __('Completed At: ', 'woocommerce-payment-gateway-bkash') . BK_STRONG_START . ($trx['completedTime'] ?? '') . BK_STRONG_END; ?></li>
        </ul>
        <p>
            <button
                    class="button button-small <?php echo ($trx['transactionStatus'] ?? '') === 'Completed' ? 'button-primary' : 'button'; ?>">
                <?php _e('Transaction Status - ' . ($trx['transactionStatus'] ?? ''), 'woocommerce-payment-gateway-bkash'); ?>
            </button>
        </p>
    </div>
    <?php
}
?>
