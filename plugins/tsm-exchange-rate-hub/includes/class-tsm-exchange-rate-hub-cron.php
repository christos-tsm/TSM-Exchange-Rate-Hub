<?php

/**
 * WP-Cron scheduling for periodic rate updates.
 *
 * Registers a custom cron interval derived from the plugin settings
 * and triggers the API fetch+store cycle on each tick.
 *
 * @since      1.0.0
 * @package    Tsm_Exchange_Rate_Hub
 * @subpackage Tsm_Exchange_Rate_Hub/includes
 */

if ( ! defined( 'WPINC' ) ) {
	die;
}

class Tsm_Exchange_Rate_Hub_Cron {

	const HOOK          = 'tsm_erh_update_rates_event';
	const SCHEDULE_NAME = 'tsm_erh_custom_interval';

	public static function schedule() {
		if ( ! wp_next_scheduled( self::HOOK ) ) {
			wp_schedule_event( time(), self::SCHEDULE_NAME, self::HOOK );
		}
	}

	public static function unschedule() {
		$timestamp = wp_next_scheduled( self::HOOK );
		if ( $timestamp ) {
			wp_unschedule_event( $timestamp, self::HOOK );
		}
		wp_clear_scheduled_hook( self::HOOK );
	}

	public static function reschedule() {
		self::unschedule();
		self::schedule();
	}

	/**
	 * Filter callback for `cron_schedules` — adds our custom interval.
	 */
	public static function add_custom_schedule( $schedules ) {
		$frequency = (int) get_option( 'tsm_erh_update_frequency', 60 );

		$schedules[ self::SCHEDULE_NAME ] = array(
			'interval' => $frequency * MINUTE_IN_SECONDS,
			'display'  => sprintf(
				/* translators: %d: number of minutes */
				__( 'Every %d minutes (TSM Exchange Rate Hub)', 'tsm-exchange-rate-hub' ),
				$frequency
			),
		);

		return $schedules;
	}

	/**
	 * Cron callback — runs the full fetch-and-store cycle.
	 */
	public static function execute_update() {
		$api    = new Tsm_Exchange_Rate_Hub_API();
		$result = $api->fetch_and_store();

		if ( is_wp_error( $result ) ) {
			error_log( '[TSM Exchange Rate Hub] Cron update failed: ' . $result->get_error_message() );
		}
	}
}
