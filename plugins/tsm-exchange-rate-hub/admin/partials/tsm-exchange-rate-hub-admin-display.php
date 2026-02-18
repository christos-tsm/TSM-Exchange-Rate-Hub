<?php

/**
 * Dashboard page — shows current rates, status cards, and a manual refresh button.
 *
 * @since      1.0.0
 * @package    Tsm_Exchange_Rate_Hub
 * @subpackage Tsm_Exchange_Rate_Hub/admin/partials
 */

if ( ! defined( 'WPINC' ) ) {
	die;
}

$base_currency  = get_option( 'tsm_erh_base_currency', 'EUR' );
$last_updated   = get_option( 'tsm_erh_last_updated', '' );
$rates          = Tsm_Exchange_Rate_Hub_DB::get_latest_rates( $base_currency );
$supported      = Tsm_Exchange_Rate_Hub_API::get_supported_currencies();
$next_scheduled = wp_next_scheduled( Tsm_Exchange_Rate_Hub_Cron::HOOK );
$frequency      = (int) get_option( 'tsm_erh_update_frequency', 60 );
?>

<div class="wrap tsm-erh-admin">
	<h1><?php echo esc_html( get_admin_page_title() ); ?></h1>

	<!-- Status cards -->
	<div class="tsm-erh-info-cards">
		<div class="tsm-erh-card">
			<h3><?php esc_html_e( 'Base Currency', 'tsm-exchange-rate-hub' ); ?></h3>
			<p class="tsm-erh-value"><?php echo esc_html( $base_currency ); ?></p>
		</div>
		<div class="tsm-erh-card">
			<h3><?php esc_html_e( 'Last Updated', 'tsm-exchange-rate-hub' ); ?></h3>
			<p class="tsm-erh-value" id="tsm-erh-last-updated">
				<?php echo $last_updated ? esc_html( $last_updated ) : esc_html__( 'Never', 'tsm-exchange-rate-hub' ); ?>
			</p>
		</div>
		<div class="tsm-erh-card">
			<h3><?php esc_html_e( 'Next Update', 'tsm-exchange-rate-hub' ); ?></h3>
			<p class="tsm-erh-value">
				<?php
				if ( $next_scheduled ) {
					$local_time = $next_scheduled + ( (float) get_option( 'gmt_offset' ) * HOUR_IN_SECONDS );
					echo esc_html( date_i18n( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $local_time ) );
				} else {
					esc_html_e( 'Not scheduled', 'tsm-exchange-rate-hub' );
				}
				?>
			</p>
		</div>
		<div class="tsm-erh-card">
			<h3><?php esc_html_e( 'Active Currencies', 'tsm-exchange-rate-hub' ); ?></h3>
			<p class="tsm-erh-value"><?php echo esc_html( count( $rates ) ); ?></p>
		</div>
		<div class="tsm-erh-card">
			<h3><?php esc_html_e( 'Refresh Interval', 'tsm-exchange-rate-hub' ); ?></h3>
			<p class="tsm-erh-value">
				<?php
				printf(
					/* translators: %d: number of minutes */
					esc_html__( '%d min', 'tsm-exchange-rate-hub' ),
					$frequency
				);
				?>
			</p>
		</div>
	</div>

	<!-- Actions -->
	<div class="tsm-erh-actions">
		<button type="button" id="tsm-erh-refresh-btn" class="button button-primary">
			<span class="dashicons dashicons-update"></span>
			<?php esc_html_e( 'Refresh Rates Now', 'tsm-exchange-rate-hub' ); ?>
		</button>
		<span id="tsm-erh-refresh-status"></span>
	</div>

	<!-- Rates table -->
	<div class="tsm-erh-rates-table-wrap">
		<h2><?php esc_html_e( 'Current Exchange Rates', 'tsm-exchange-rate-hub' ); ?></h2>

		<?php if ( ! empty( $rates ) ) : ?>
			<table class="wp-list-table widefat fixed striped" id="tsm-erh-rates-table">
				<thead>
					<tr>
						<th scope="col"><?php esc_html_e( 'Currency Code', 'tsm-exchange-rate-hub' ); ?></th>
						<th scope="col"><?php esc_html_e( 'Currency Name', 'tsm-exchange-rate-hub' ); ?></th>
						<th scope="col"><?php esc_html_e( 'Rate', 'tsm-exchange-rate-hub' ); ?></th>
						<th scope="col"><?php esc_html_e( 'Last Updated', 'tsm-exchange-rate-hub' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php foreach ( $rates as $rate ) : ?>
						<tr>
							<td><strong><?php echo esc_html( $rate['target_currency'] ); ?></strong></td>
							<td><?php echo esc_html( $supported[ $rate['target_currency'] ] ?? $rate['target_currency'] ); ?></td>
							<td><?php echo esc_html( number_format( (float) $rate['rate'], 6 ) ); ?></td>
							<td><?php echo esc_html( $rate['last_updated'] ); ?></td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
		<?php else : ?>
			<div class="tsm-erh-notice">
				<p>
					<?php esc_html_e( 'No exchange rates available yet. Click "Refresh Rates Now" to fetch the latest data.', 'tsm-exchange-rate-hub' ); ?>
				</p>
			</div>
		<?php endif; ?>
	</div>

	<!-- Shortcode hint -->
	<div class="tsm-erh-shortcode-hint">
		<h3><?php esc_html_e( 'Shortcode Usage', 'tsm-exchange-rate-hub' ); ?></h3>
		<p><?php esc_html_e( 'Use the following shortcode to display exchange rates on any page or post:', 'tsm-exchange-rate-hub' ); ?></p>
		<code>[tsm_exchange_rates]</code>
		<p class="description">
			<?php esc_html_e( 'Optional attributes: base="EUR" currencies="USD,GBP,JPY" title="Exchange Rates" show_updated="yes"', 'tsm-exchange-rate-hub' ); ?>
		</p>
	</div>
</div>
