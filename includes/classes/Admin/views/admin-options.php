<h3><?php _e('bKash Payment Gateway', 'woocommerce-payment-gateway-bkash'); ?></h3>

<div class="gateway-banner bKash-hero-div bKash-success">
    <img alt="bKash logo" src="<?php echo \bKash\PGW\WC_Gateway_bKash()->plugin_url() . '/assets/images/logo.png'; ?>"/>
    <p class="main"><strong><?php _e('Getting started', 'woocommerce-payment-gateway-bkash'); ?></strong></p>
    <p><?php _e('A payment gateway description can be placed here.', 'woocommerce-payment-gateway-bkash'); ?></p>

    <p class="main"><strong><?php _e('Gateway Status', 'woocommerce-payment-gateway-bkash'); ?></strong></p>
    <ul>
        <li><?php echo __('Debug Enabled?', 'woocommerce-payment-gateway-bkash') . ' <strong>' . $this->debug . '</strong>'; ?></li>
        <li><?php echo __('Sandbox Enabled?', 'woocommerce-payment-gateway-bkash') . ' <strong>' . $this->sandbox . '</strong>'; ?></li>
    </ul>

    <?php if (empty($this->public_key)) { ?>
        <p><a href="https://www.bkash.com" target="_blank" rel="noopener"
              class="button button-primary"><?php _e('Sign up for bKash Payment Gateway', 'woocommerce-payment-gateway-bkash'); ?></a>
            <a href="https://developer.bka.sh" target="_blank" rel="noopener"
               class="button"><?php _e('Developer page', 'woocommerce-payment-gateway-bkash'); ?></a></p>
    <?php } ?>
</div>

<table class="form-table" id="admin-option-table" aria-describedby="admin option Table">
    <?php $this->generate_settings_html(); ?>
    <script type="text/javascript">
        jQuery('#woocommerce_bkash_pgw_sandbox').change(function () {
            var sandbox = jQuery('#woocommerce_bkash_pgw_sandbox_app_key, #woocommerce_bkash_pgw_sandbox_app_secret, #woocommerce_bkash_pgw_sandbox_username, #woocommerce_bkash_pgw_sandbox_password').closest('tr'),
                production = jQuery('#woocommerce_bkash_pgw_app_key, #woocommerce_bkash_pgw_app_secret, #woocommerce_bkash_pgw_username, #woocommerce_bkash_pgw_password').closest('tr');

            if (jQuery(this).is(':checked')) {
                sandbox.show();
                production.hide();
            } else {
                sandbox.hide();
                production.show();
            }
        }).change();
    </script>
</table>
