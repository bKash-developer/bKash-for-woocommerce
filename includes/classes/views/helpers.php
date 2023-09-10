<?php
/**
 * Page Helper
 *
 * @category    Utility
 * @package     bkash-for-woocommerce
 * @author      bKash Developer <developer@bkash.com>
 * @copyright   Copyright 2023 bKash Limited. All rights reserved.
 * @license     GNU General Public License version 2 or later; see LICENSE
 * @link        https://bkash.com
 */

if ( ! function_exists( 'bkShowLoginAction' ) ) {
	function bkShowLoginAction() {
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
if ( ! function_exists( 'bkHasTokenizedWordInIntegrationType' ) ) {
	function bkHasTokenizedWordInIntegrationType( $integrationType ): bool {
		return strpos( $integrationType, 'tokenized' ) !== false;
	}
}
if ( ! function_exists( 'bkShowAgreementTable' ) ) {
	function bkShowAgreementTable( $agreements, $isLoggedIn = false, $showWithoutRemembering = false ) {
		?>
		<table id='payment-fields-table'>
			<?php
			foreach ( $agreements as $i => $agreement ) {
				$agreementToken = $agreement->agreement_token ?? '';
				?>
				<tr>
					<td>
						<label for="
						<?php
						esc_html_e( $agreement->agreement_token ?? '', 'bkash-for-woocommerce' );
						?>
						">
							<input
								id="
								<?php
								esc_html_e( $agreementToken, 'bkash-for-woocommerce' );
								?>
								"
								type="radio"
								name="agreement_id"
								value="
								<?php
								esc_html_e( $agreementToken, 'bkash-for-woocommerce' );
								?>
								"
								<?php
								echo $i === 0 ? esc_html( 'checked' ) : '';
								?>
							/>
							<?php
							esc_html_e( $agreement->phone ?? '', 'bkash-for-woocommerce' );
							?>
						</label>
					</td>
					<td>
						<a
							class="cancelAgreementButton"
							href="javascript:void(0)"
							data-agreement="
							<?php
							esc_html_e( $agreementToken, 'bkash-for-woocommerce' );
							?>
							"
						>Remove</a>
					</td>
				</tr>
				<?php
			}

			if ( $isLoggedIn ) {
				?>
				<tr>
					<td colspan="2">
						<label for="new-agreement">
							<input id="new-agreement" type="radio" name="agreement_id" value="new"/>
							Pay and remember a new bKash account
						</label>
					</td>
				</tr>
				<?php
			}

			if ( $showWithoutRemembering ) {
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
			}
			?>
		</table>
		<?php
	}
}
