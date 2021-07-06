<style>
    .wocommerce-message.error {
        border-left-color: #e23e3e !important;
    }
</style>
<h1><?php echo get_admin_page_title(); ?></h1>
<br>
<form action="#" method="post">

    <table>
        <tr>
            <td>
                <label for="trxid" class="form-label">Transaction ID *</label>
            </td>
            <td>
                <input name="trxid" type="text" id="trxid" placeholder="Transaction ID" class="form-text-input"
                       value="<?php echo !empty($fill_trx_id) ? $fill_trx_id : (!empty($trx_id) ? $trx_id : '') ?> "/>
            </td>
        </tr>
        <tr>
            <td>
                <label for="amount" class="form-label">Amount</label>
            </td>
            <td>
                <input name="amount" type="text" id="amount" placeholder="Amount" class="form-text-input"
                       value="<?php echo $amount ?? ''; ?>"/>
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

    <table>
        <tr>
            <td>
                <label for="trxid" class="form-label">Transaction ID *</label>
            </td>
            <td>
                <input name="trxid" type="text" id="trxid" placeholder="Transaction ID" class="form-text-input"
                       value="<?php echo !empty($fill_trx_id) ? $fill_trx_id : (!empty($trx_id) ? $trx_id : '') ?> "/>
            </td>
        </tr>
    </table>

    <button class="button button-primary" name="check" type="submit">Check</button>
</form>
<br/>

<?php
if (isset($trx) && is_string($trx) && !empty($trx)) {
    // FAILED TO GET BALANCES
    ?>
    <div id="message" class="bKash-hero-div woocommerce-message bKash-error">
        <p><?php echo $trx ?? '' ?></p>
    </div>
    <?php

} else if (isset($trx['refundTrxID']) && is_array($trx)) {
    // GOT TRANSACTION
    ?>
    <div class="gateway-banner bKash-hero-div bKash-success">
        <img style="max-width: 90px; margin: 10px 5px"
             src="<?php echo \bKash\PGW\WC_Gateway_bKash()->plugin_url() . '/assets/images/logo.png'; ?>"/>
        <p class="main">
            <strong>Transaction ID: <?php _e($trx['originalTrxID'] ?? '', 'woocommerce-payment-gateway-bkash'); ?></strong></p>
        <hr>
        <p><?php _e('Refund ID: <b>' . ($trx['refundTrxID'] ?? '') . '</b>', 'woocommerce-payment-gateway-bkash'); ?></p>
        <p><?php _e('Amount: <b>' . ($trx['amount'] ?? '') . ' ' . ($trx['currency'] ?? '') . '</b>', 'woocommerce-payment-gateway-bkash'); ?></p>
        <hr>
        <ul>
            <li><?php echo __('Charge', 'woocommerce-payment-gateway-bkash') . ' <strong>' . ($trx['charge'] ?? '') . '</strong>'; ?></li>
            <li><?php echo __('Completed At', 'woocommerce-payment-gateway-bkash') . ' <strong>' . ($trx['completedTime'] ?? '') . '</strong>'; ?></li>
        </ul>
        <p>
            <button
                    class="button button-small <?php echo ($trx['transactionStatus'] ?? '') === 'Completed' ? 'button-primary' : 'button'; ?>">
                <?php _e('Refund Status - ' . ($trx['transactionStatus'] ?? ''), 'woocommerce-payment-gateway-bkash'); ?>
            </button>
        </p>
    </div>
    <?php
}
?>
