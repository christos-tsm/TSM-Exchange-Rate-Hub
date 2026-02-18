<?php

/**
 * Shortcode output template.
 *
 * Variables available from the calling scope:
 *   $base           string   Base currency code.
 *   $title          string   Heading text.
 *   $show_updated   bool     Whether to display the "last updated" line.
 *   $rates_data     array    Each element: [ 'target_currency' => 'USD', 'rate' => 1.08 ].
 *   $last_updated   string   Datetime string from the DB.
 *   $supported      array    Lookup of currency code => human name.
 *
 * @since      1.0.0
 * @package    Tsm_Exchange_Rate_Hub
 * @subpackage Tsm_Exchange_Rate_Hub/public/partials
 */

if ( ! defined( 'WPINC' ) ) {
	die;
}
?>

<div class="tsm-erh-exchange-rates">

	<?php if ( ! empty( $title ) ) : ?>
		<h3 class="tsm-erh-title"><?php echo esc_html( $title ); ?></h3>
	<?php endif; ?>

	<div class="tsm-erh-base-info">
		<span class="tsm-erh-base-label"><?php esc_html_e( 'Base Currency:', 'tsm-exchange-rate-hub' ); ?></span>
		<span class="tsm-erh-base-value"><?php echo esc_html( $base ); ?>
			<?php
			if ( isset( $supported[ $base ] ) ) {
				echo ' — ' . esc_html( $supported[ $base ] );
			}
			?>
		</span>
	</div>

	<?php if ( ! empty( $rates_data ) ) : ?>
		<div class="tsm-erh-rates-grid">
			<?php foreach ( $rates_data as $rate_row ) :
				$currency      = $rate_row['target_currency'];
				$rate_val      = (float) $rate_row['rate'];
				$currency_name = $supported[ $currency ] ?? $currency;
			?>
				<div class="tsm-erh-rate-card">
					<div class="tsm-erh-rate-currency">
						<span class="tsm-erh-rate-code"><?php echo esc_html( $currency ); ?></span>
						<span class="tsm-erh-rate-name"><?php echo esc_html( $currency_name ); ?></span>
					</div>
					<div class="tsm-erh-rate-value">
						<?php echo esc_html( number_format( $rate_val, 4 ) ); ?>
					</div>
				</div>
			<?php endforeach; ?>
		</div>
	<?php else : ?>
		<p class="tsm-erh-no-rates">
			<?php esc_html_e( 'Exchange rates are currently unavailable. Please try again later.', 'tsm-exchange-rate-hub' ); ?>
		</p>
	<?php endif; ?>

	<?php if ( $show_updated && ! empty( $last_updated ) ) : ?>
		<div class="tsm-erh-updated">
			<small>
				<?php
				printf(
					/* translators: %s: date/time of last update */
					esc_html__( 'Last updated: %s', 'tsm-exchange-rate-hub' ),
					esc_html( $last_updated )
				);
				?>
			</small>
		</div>
	<?php endif; ?>

</div>
