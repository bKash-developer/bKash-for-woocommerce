<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
} // Exit if accessed directly.


if ( isset( $agreements ) && ( $this->integration_type === "tokenized" || $this->integration_type === "tokenized-both" ) ) {
	echo "<table>";
	foreach ( $agreements as $i => $agreement ) {
		?>
        <tr>
            <td>
                <label for="<?php echo $agreement->agreement_token ?? ""; ?>">
                    <input id="<?php echo $agreement->agreement_token ?? ""; ?>" type="radio" name="agreement_id"
                           value="<?php echo $agreement->agreement_token ?? ""; ?>"
						<?php if ( $i === 0 ) {
							echo 'checked';
						} ?>
                    />
					<?php echo $agreement->phone ?? '' ?>
                </label>
            </td>
            <td><a class="cancelAgreementButton" href="javascript:void(0)"
                   data-agreement="<?php echo $agreement->agreement_token ?? ""; ?>">Remove</a></td>
        </tr>
		<?php
	}

	?>
    <tr>
        <td colspan="2">
            <label for="new-agreement">
                <input id="new-agreement" type="radio" name="agreement_id"
                       value="new"
                />
                Pay and remember a new bKash account
            </label>
        </td>
    </tr>
	<?php

	if ( $this->integration_type === "tokenized-both" ) {
		?>
        <tr>
            <td colspan="2">
                <label for="non-agreement">
                    <input id="non-agreement" type="radio" name="agreement_id"
                           value="no"
                    />
                    Pay without remembering
                </label>
            </td>
        </tr>

		<?php
	}
	echo "</table>";
} else if ( isset( $agreements ) && count( (array) $agreements ) === 0 ) {
	if ( $this->integration_type === "tokenized" ) {
		?>
        <table>
            <tr>
                <td>Please login to complete the payment</td>
            </tr>
        </table>
		<?php
	}
}

if ( get_current_user_id() === 0 ) {
	echo "To remember your bKash account number, please login and check remember";
}

echo '<input type="hidden" name="bkash-ajax-nonce" id="bkash-ajax-nonce" value="' . wp_create_nonce( 'bkash-ajax-nonce' ) . '" />';

?>
