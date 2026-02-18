<?php

/**
 * Settings page — base currency, enabled currencies, update frequency.
 *
 * @since      1.0.0
 * @package    Tsm_Exchange_Rate_Hub
 * @subpackage Tsm_Exchange_Rate_Hub/admin/partials
 */

if ( ! defined( 'WPINC' ) ) {
	die;
}

$base_currency      = get_option( 'tsm_erh_base_currency', 'EUR' );
$enabled_currencies = get_option( 'tsm_erh_enabled_currencies', array( 'USD', 'GBP', 'JPY', 'CHF', 'CAD', 'AUD' ) );
$update_frequency   = get_option( 'tsm_erh_update_frequency', 60 );
$supported          = Tsm_Exchange_Rate_Hub_API::get_supported_currencies();
?>

<div class="wrap tsm-erh-admin">
	<h1><?php echo esc_html( get_admin_page_title() ); ?></h1>

	<?php settings_errors(); ?>

	<form method="post" action="options.php" class="tsm-erh-settings-form">
		<?php settings_fields( 'tsm_erh_settings_group' ); ?>

		<!-- General settings -->
		<div class="tsm-erh-settings-section">
			<h2><?php esc_html_e( 'General Settings', 'tsm-exchange-rate-hub' ); ?></h2>

			<table class="form-table" role="presentation">
				<tr>
					<th scope="row">
						<label for="tsm_erh_base_currency">
							<?php esc_html_e( 'Base Currency', 'tsm-exchange-rate-hub' ); ?>
						</label>
					</th>
					<td>
						<select name="tsm_erh_base_currency" id="tsm_erh_base_currency">
							<?php foreach ( $supported as $code => $name ) : ?>
								<option value="<?php echo esc_attr( $code ); ?>" <?php selected( $base_currency, $code ); ?>>
									<?php echo esc_html( $code . ' — ' . $name ); ?>
								</option>
							<?php endforeach; ?>
						</select>
						<p class="description">
							<?php esc_html_e( 'All exchange rates will be relative to this currency.', 'tsm-exchange-rate-hub' ); ?>
						</p>
					</td>
				</tr>
				<tr>
					<th scope="row">
						<label for="tsm_erh_update_frequency">
							<?php esc_html_e( 'Update Frequency', 'tsm-exchange-rate-hub' ); ?>
						</label>
					</th>
					<td>
						<input type="number"
						       name="tsm_erh_update_frequency"
						       id="tsm_erh_update_frequency"
						       value="<?php echo esc_attr( $update_frequency ); ?>"
						       min="5" max="1440" step="5"
						       class="small-text">
						<span><?php esc_html_e( 'minutes', 'tsm-exchange-rate-hub' ); ?></span>
						<p class="description">
							<?php esc_html_e( 'How often rates are fetched from the API (5 – 1440 minutes).', 'tsm-exchange-rate-hub' ); ?>
						</p>
					</td>
				</tr>
			</table>
		</div>

		<!-- Enabled currencies -->
		<div class="tsm-erh-settings-section">
			<h2><?php esc_html_e( 'Enabled Currencies', 'tsm-exchange-rate-hub' ); ?></h2>
			<p class="description">
				<?php esc_html_e( 'Select which currencies to track. Only selected currencies will be fetched and stored.', 'tsm-exchange-rate-hub' ); ?>
			</p>

			<div class="tsm-erh-currency-actions">
				<button type="button" class="button" id="tsm-erh-select-all">
					<?php esc_html_e( 'Select All', 'tsm-exchange-rate-hub' ); ?>
				</button>
				<button type="button" class="button" id="tsm-erh-deselect-all">
					<?php esc_html_e( 'Deselect All', 'tsm-exchange-rate-hub' ); ?>
				</button>
			</div>

			<div class="tsm-erh-currency-grid">
				<?php foreach ( $supported as $code => $name ) : ?>
					<label class="tsm-erh-currency-checkbox">
						<input type="checkbox"
						       name="tsm_erh_enabled_currencies[]"
						       value="<?php echo esc_attr( $code ); ?>"
						       <?php checked( in_array( $code, $enabled_currencies, true ) ); ?>>
						<span class="tsm-erh-currency-code"><?php echo esc_html( $code ); ?></span>
						<span class="tsm-erh-currency-name"><?php echo esc_html( $name ); ?></span>
					</label>
				<?php endforeach; ?>
			</div>
		</div>

		<?php submit_button( __( 'Save Settings', 'tsm-exchange-rate-hub' ) ); ?>
	</form>
</div>
