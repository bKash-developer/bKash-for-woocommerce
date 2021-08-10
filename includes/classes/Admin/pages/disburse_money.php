<style>
    .wocommerce-message.error {
        border-left-color: #e23e3e !important;
    }
</style>
<h1><?php echo get_admin_page_title(); ?></h1>
<br>
<form action="#" method="post">

    <table id="disburse-money-table" aria-describedby="disburse money">
        <tr>
            <th scope="col">Field</th>
            <th scope="col">Value</th>
        </tr>
        <tr>
            <td>
                <label for="amount" class="form-label">Amount *</label>
            </td>
            <td>
                <input name="amount" type="text" id="amount" placeholder="Amount" class="form-text-input"/>
            </td>
        </tr>
        <tr>
            <td>
                <label for="receiver" class="form-label">Receiver (bKash Personal Account Holder) *</label>
            </td>
            <td>
                <input name="receiver" type="tel" id="receiver" placeholder="Mobile number" class="form-text-input"
                       value="<?php echo $receiver ?? ''; ?>" pattern="^(?:\+88|01)?\d{11}$"/>
            </td>
        </tr>
        <tr>
            <td>
                <label for="invoice" class="form-label">Invoice Number</label>
            </td>
            <td>
                <input name="invoice_no" type="text" id="invoice" placeholder="Invoice Number" class="form-text-input"/>
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
        <p><?php echo $trx ?? '' ?></p>
    </div>
	<?php

} else if ( isset( $trx['trxID'] ) && is_array( $trx ) ) {
	// GOT TRANSACTION
	?>
    <div class="gateway-banner bKash-hero-div bKash-success">
        <img style="max-width: 90px; margin: 10px 5px"
             alt="bKash logo"
             src="<?php echo \bKash\PGW\WC_Gateway_bKash()->plugin_url() . '/assets/images/logo.png'; ?>"/>
        <p class="main">
            <strong>Transaction ID: <?php _e( $trx['trxID'] ?? '', 'woocommerce-payment-gateway-bkash' ); ?></strong>
        </p>
        <hr>
        <p><?php _e( 'Disbursed To (bKash Customer Account): <b>' . ( $trx['receiverMSISDN'] ?? '' ) . '</b>', 'woocommerce-payment-gateway-bkash' ); ?></p>
        <p><?php _e( 'Amount: <b>' . ( $trx['amount'] ?? '' ) . ' ' . ( $trx['currency'] ?? '' ) . '</b>', 'woocommerce-payment-gateway-bkash' ); ?></p>
        <hr>
        <ul>
            <li><?php echo __( 'Invoice Number', 'woocommerce-payment-gateway-bkash' ) . ' <strong>' . ( $trx['merchantInvoiceNumber'] ?? '' ) . '</strong>'; ?></li>
            <li><?php echo __( 'Completed At', 'woocommerce-payment-gateway-bkash' ) . ' <strong>' . ( $trx['completedTime'] ?? '' ) . '</strong>'; ?></li>
        </ul>
        <p>
            <button
                    class="button button-small <?php echo ( $trx['transactionStatus'] ?? '' ) === 'Completed' ? 'button-primary' : 'button'; ?>">
				<?php _e( 'Transfer Status - ' . ( $trx['transactionStatus'] ?? '' ), 'woocommerce-payment-gateway-bkash' ); ?>
            </button>
        </p>
    </div>
	<?php
}
?>
