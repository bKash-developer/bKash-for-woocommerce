<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
} // Exit if accessed directly.

if ( isset( $agreements ) ) {
	if ( $this->integration_type === "tokenized" || $this->integration_type === "tokenized-both" ) {
		?>
        <table id='payment-fields-table'>
			<?php
			foreach ( $agreements as $i => $agreement ) {
				?>
                <tr>
                    <td>
                        <label for="<?php esc_html_e( $agreement->agreement_token ?? '', BKASH_FW_TEXT_DOMAIN ); ?>">
                            <input
                                    id="<?php esc_html_e( $agreement->agreement_token ?? '', BKASH_FW_TEXT_DOMAIN ); ?>"
                                    type="radio"
                                    name="agreement_id"
                                    value="<?php esc_html_e( $agreement->agreement_token ?? '', BKASH_FW_TEXT_DOMAIN ); ?>"
								<?php echo $i === 0 ? 'checked' : ''; ?>
                            />
							<?php esc_html_e( $agreement->phone ?? '', BKASH_FW_TEXT_DOMAIN ); ?>
                        </label>
                    </td>
                    <td>
                        <a
                                class="cancelAgreementButton"
                                href="javascript:void(0)"
                                data-agreement="<?php esc_html_e( $agreement->agreement_token ?? '', BKASH_FW_TEXT_DOMAIN ); ?>"
                        >Remove</a>
                    </td>
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
                            <input id="non-agreement" type="radio" name="agreement_id" value="no"/>
                            Pay without remembering
                        </label>
                    </td>
                </tr>

				<?php
			} ?>
        </table>
		<?php
	} else if ( count( (array) $agreements ) === 0 && $this->integration_type === "tokenized" ) {
		?>
        <table id="tokenized-login-table" aria-describedby="tokenized login table">
            <tr>
                <th scope="col">Login Required</th>
            </tr>
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

?>

<input type="hidden" name="bkash-ajax-nonce" id="bkash-ajax-nonce"
       value="<?php echo wp_kses_post( wp_create_nonce( 'bkash-ajax-nonce' ) ); ?>"/>
