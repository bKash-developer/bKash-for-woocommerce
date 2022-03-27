<?php

if ( isset( $balances ) && is_string( $balances ) ) {
	// FAILED TO GET BALANCES
	?>
    <div id="message" class="woocommerce-message bKash-hero-div bKash-error-div">
        <p><?php esc_html_e( $balances ?? '', BKASH_FW_TEXT_DOMAIN ); ?></p>
    </div>
	<?php

} else if ( isset( $balances['organizationBalance'] ) && is_array( $balances['organizationBalance'] ) ) {
	// GOT BALANCES
	foreach ( $balances['organizationBalance'] as $balance ) {
		?>
        <div class="gateway-banner bKash-hero-div bKash-success">
            <img style="max-width: 90px; margin: 10px 5px"
                 alt="bkash logo check balance"
                 src="<?php echo \bKash\PGW\WC_Gateway_bKash()->plugin_url() . '/assets/images/logo.png'; ?>"/>
            <p class="main">
                <strong>
					<?php esc_html_e( $balance['accountTypeName'] ?? '', BKASH_FW_TEXT_DOMAIN ); ?>
                </strong>
            </p>
            <hr>
            <p>
                Current Balance:
                <b>
					<?php esc_html_e( ( $balance['currentBalance'] ?? '' ) . ' ' . ( $balance['currency'] ?? '' ), BKASH_FW_TEXT_DOMAIN ); ?>
                </b>
            </p>
            <p>
                Available Balance:
                <b>
					<?php esc_html_e( ( $balance['availableBalance'] ?? '' ) . ' ' . ( $balance['currency'] ?? '' ), BKASH_FW_TEXT_DOMAIN ); ?>
                </b>
            </p>
            <hr>
            <ul>
                <li>Account Enabled?
                    <strong><?php esc_html_e( $balance['accountStatus'] ?? '', BKASH_FW_TEXT_DOMAIN ); ?></strong></li>
                <li>Account Name
                    <strong><?php esc_html_e( $balance['accountHolderName'] ?? '', BKASH_FW_TEXT_DOMAIN ); ?></strong>
                </li>
                <li>Last updated
                    <strong><?php esc_html_e( $balance['updateTime'] ?? '', BKASH_FW_TEXT_DOMAIN ); ?></strong></li>
            </ul>

            <p>
                <button
                        class="button button-small <?php echo ( $balance['accountStatus'] ?? '' ) === 'Active' ? 'button-primary' : 'button'; ?>">
					<?php esc_html_e( $balance['accountStatus'] ?? '', BKASH_FW_TEXT_DOMAIN ); ?>
                </button>
            </p>
        </div>
		<?php
	}
}
?>
