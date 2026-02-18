<?php

/**
 * Fired during plugin activation.
 *
 * Creates custom database tables, sets default option values,
 * and schedules the WP-Cron event for periodic rate fetching.
 *
 * @since      1.0.0
 * @package    Tsm_Exchange_Rate_Hub
 * @subpackage Tsm_Exchange_Rate_Hub/includes
 */

if ( ! defined( 'WPINC' ) ) {
	die;
}

class Tsm_Exchange_Rate_Hub_Activator {

	public static function activate() {
		self::load_dependencies();

		Tsm_Exchange_Rate_Hub_DB::create_tables();

		add_option( 'tsm_erh_base_currency', 'EUR' );
		add_option( 'tsm_erh_enabled_currencies', array(
			'USD', 'GBP', 'JPY', 'CHF', 'CAD', 'AUD',
		) );
		add_option( 'tsm_erh_update_frequency', 60 );

		add_filter( 'cron_schedules', array( 'Tsm_Exchange_Rate_Hub_Cron', 'add_custom_schedule' ) );
		Tsm_Exchange_Rate_Hub_Cron::schedule();
	}

	private static function load_dependencies() {
		$path = plugin_dir_path( dirname( __FILE__ ) );

		require_once $path . 'includes/class-tsm-exchange-rate-hub-db.php';
		require_once $path . 'includes/class-tsm-exchange-rate-hub-api.php';
		require_once $path . 'includes/class-tsm-exchange-rate-hub-cache.php';
		require_once $path . 'includes/class-tsm-exchange-rate-hub-cron.php';
	}
}
