<?php

if (isset($balances) && is_string($balances)) {
    // FAILED TO GET BALANCES
    ?>
    <div id="message" class="woocommerce-message bKash-hero-div bKash-error-div">
        <p><?php echo $balances ?? '' ?></p>
    </div>
    <?php

} else if (isset($balances['organizationBalance']) && is_array($balances['organizationBalance'])) {
    // GOT BALANCES
    foreach ($balances['organizationBalance'] as $balance) {
        ?>
        <div class="gateway-banner bKash-hero-div bKash-success">
            <img style="max-width: 90px; margin: 10px 5px"
                 alt="bkash logo check balance"
                 src="<?php echo \bKash\PGW\WC_Gateway_bKash()->plugin_url() . '/assets/images/logo.png'; ?>"/>
            <p class="main">
                <strong><?php _e($balance['accountTypeName'] ?? '', 'woocommerce-payment-gateway-bkash'); ?></strong>
            </p>
            <hr>
            <p><?php _e('Current Balance: <b>' . ($balance['currentBalance'] ?? '') . ' ' . ($balance['currency'] ?? '') . '</b>', 'woocommerce-payment-gateway-bkash'); ?></p>
            <p><?php _e('Available Balance: <b>' . ($balance['availableBalance'] ?? '') . ' ' . ($balance['currency'] ?? '') . '</b>', 'woocommerce-payment-gateway-bkash'); ?></p>
            <hr>
            <ul>
                <li><?php echo __('Account Enabled?', 'woocommerce-payment-gateway-bkash') . ' <strong>' . ($balance['accountStatus'] ?? '') . '</strong>'; ?></li>
                <li><?php echo __('Account Name', 'woocommerce-payment-gateway-bkash') . ' <strong>' . ($balance['accountHolderName'] ?? '') . '</strong>'; ?></li>
                <li><?php echo __('Last updated', 'woocommerce-payment-gateway-bkash') . ' <strong>' . ($balance['updateTime'] ?? '') . '</strong>'; ?></li>
            </ul>

            <?php if (empty($this->public_key)) { ?>
                <p>
                    <button
                            class="button button-small <?php echo ($balance['accountStatus'] ?? '') === 'Active' ? 'button-primary' : 'button'; ?>">
                        <?php _e('Status - ' . ($balance['accountStatus'] ?? ''), 'woocommerce-payment-gateway-bkash'); ?>
                    </button>
                </p>
            <?php } ?>
        </div>
        <?php
    }
} else {

}
?>
