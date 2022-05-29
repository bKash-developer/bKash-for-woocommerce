<?php
/**
 * Admin Options
 *
 * @category    Page
 * @package     bkash-for-woocommerce
 * @author      Md. Shahnawaz Ahmed <shahnawaz.ahmed@bkash.com>
 * @copyright   Copyright 2022 bKash Limited. All rights reserved.
 * @license     GNU General Public License version 2 or later; see LICENSE
 * @link        https://bkash.com
 */

use function bKash\PGW\WooCommerceBkashPgw;

$logoUrl = esc_url( WooCommerceBkashPgw()->pluginUrl() . '/assets/images/logo.png' );
?>

<h3>bKash Payment Gateway</h3>

<div class="gateway-banner bKash-hero-div bKash-success">
	<img
		alt="bKash logo"
		src="
		<?php
		echo esc_attr( $logoUrl );
		?>
		"
	/>
	<p class="main">
		<strong>Getting started</strong>
	</p>
	<p>bKash payment gateway for WooCommerce. Collect PGW credentials from bKash team and set here.</p>

	<p class="main">
		<strong>Gateway Status</strong>
	</p>
	<ul>
		<li>Debug Enabled? : <strong>
		<?php
				esc_html_e( $this->debug, 'bkash-for-woocommerce' );
		?>
				</strong></li>
		<li>Sandbox Enabled? : <strong>
		<?php
				esc_html_e( $this->sandbox, 'bkash-for-woocommerce' );
		?>
				</strong></li>
	</ul>

	<?php
	if ( empty( $this->app_key ) ) {
		?>
		<p>
			<a href="https://www.bkash.com" target="_blank" rel="noopener" class="button button-primary">
				Sign up for bKash Payment Gateway
			</a>
		</p>
		<?php
	}
	?>
	<a href="https://developer.bka.sh" target="_blank" rel="noopener" class="button">Developer page</a>
</div>

<table class="form-table" id="admin-option-table" aria-describedby="admin option Table">
	<?php
	$this->generate_settings_html();
	?>
	<script type="text/javascript">
		let bKash_slug = "<?php echo esc_html( BKASH_FW_PLUGIN_SLUG ); ?>";

		jQuery('#woocommerce_' + bKash_slug + '_sandbox').change(function () {
			let inputs = ["app_key", "app_secret", "username", "password"];

			let sandbox_inputs = inputs.map(e => "#woocommerce_" + bKash_slug + "_sandbox_" + e).join(",");
			let prod_inputs = inputs.map(e => "#woocommerce_" + bKash_slug + '_' + e).join(",");


			let sandbox = jQuery(sandbox_inputs).closest('tr'),
				production = jQuery(prod_inputs).closest('tr');

			if (jQuery(this).is(':checked')) {
				sandbox.show();
				production.hide();
			} else {
				sandbox.hide();
				production.show();
			}
		}).change();

		jQuery('#woocommerce_' + bKash_slug + '_integration_type').change(function () {
			let integration_type = jQuery(this).find(":selected").val();
			let b2cSetting = jQuery("#woocommerce_" + bKash_slug + "_enable_b2c");

			if (integration_type && integration_type.toLowerCase() === 'checkout') {
				b2cSetting.closest("tr").show();
			} else {
				b2cSetting.closest("tr").hide();
			}
		}).change();
	</script>
</table>
