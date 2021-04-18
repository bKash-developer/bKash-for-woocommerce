<?php
if (isset($trx) && $trx) {
    ?>

        <p>Thank you for your payment using bKash online payment gateway. Here is your payment details</p>

    <table class="woocommerce-table order_details">
        <tr>
            <td>Payment Method</td>
            <td>bKash Online payment Gateway</td>
        </tr>
        <tr>
            <td>Transaction ID</td>
            <td><?php echo $trx->getTrxID() ?? ''; ?></td>
        </tr>
        <tr>
            <td>Payment Status</td>
            <td><?php echo $trx->getStatus() ?? ''; ?></td>
        </tr>
    </table>

    <?php
}
?>
