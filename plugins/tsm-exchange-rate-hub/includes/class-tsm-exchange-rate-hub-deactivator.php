<?php

/**
 * Fired during plugin deactivation.
 *
 * Removes the scheduled WP-Cron event so background fetches
 * stop while the plugin is inactive. Data and options are preserved
 * so reactivation is non-destructive. Full cleanup happens on uninstall.
 *
 * @since      1.0.0
 * @package    Tsm_Exchange_Rate_Hub
 * @subpackage Tsm_Exchange_Rate_Hub/includes
 */

if ( ! defined( 'WPINC' ) ) {
	die;
}

class Tsm_Exchange_Rate_Hub_Deactivator {

	public static function deactivate() {
		require_once plugin_dir_path( dirname( __FILE__ ) )
			. 'includes/class-tsm-exchange-rate-hub-cron.php';

		Tsm_Exchange_Rate_Hub_Cron::unschedule();
	}
}
