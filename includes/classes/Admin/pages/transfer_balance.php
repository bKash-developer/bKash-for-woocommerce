<style>
    .wocommerce-message.error {
        border-left-color: #e23e3e !important;
    }
</style>
<h1><?php echo esc_html(get_admin_page_title()); ?></h1>
<br>
<form action="#" method="post">

    <table id="transfer-balance-table" aria-describedby="transfer balance">
        <tr>
            <td>
                <label for="amount" class="form-label">Amount</label>
            </td>
            <td>
                <input name="amount" type="text" id="amount" placeholder="Amount" class="form-text-input"
                       value="<?php echo esc_html($amount) ?? ''; ?>"/>
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
if (isset($trx) && is_string($trx) && !empty($trx)) {
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
             alt="bkash logo"
             src="<?php echo \bKash\PGW\WC_Gateway_bKash()->plugin_url() . '/assets/images/logo.png'; ?>"/>
        <p class="main">
            <strong>Transaction ID: <?php _e($trx['trxID'] ?? '', 'woocommerce-payment-gateway-bkash'); ?></strong></p>
        <hr>
        <p><?php _e('Transfer Type: <b>' . ($trx['transferType'] ?? '') . '</b>', 'woocommerce-payment-gateway-bkash'); ?></p>
        <p><?php _e('Amount: <b>' . ($trx['amount'] ?? '') . ' ' . ($trx['currency'] ?? '') . '</b>', 'woocommerce-payment-gateway-bkash'); ?></p>
        <hr>
        <ul>
            <li><?php echo __('Completed At', 'woocommerce-payment-gateway-bkash') . ' <strong>' . ($trx['completedTime'] ?? '') . '</strong>'; ?></li>
        </ul>
        <p>
            <button
                    class="button button-small <?php echo ($trx['transactionStatus'] ?? '') === 'Completed' ? 'button-primary' : 'button'; ?>">
                <?php _e('Transfer Status - ' . ($trx['transactionStatus'] ?? ''), 'woocommerce-payment-gateway-bkash'); ?>
            </button>
        </p>
    </div>
    <?php
}
?>
