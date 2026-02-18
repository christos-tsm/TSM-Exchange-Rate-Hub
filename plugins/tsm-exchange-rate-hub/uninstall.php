<?php

/**
 * Fired when the plugin is uninstalled (deleted).
 *
 * Drops custom database tables, purges transient cache,
 * removes all plugin options, and clears any remaining cron events.
 *
 * @since      1.0.0
 * @package    Tsm_Exchange_Rate_Hub
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

require_once plugin_dir_path( __FILE__ ) . 'includes/class-tsm-exchange-rate-hub-db.php';
require_once plugin_dir_path( __FILE__ ) . 'includes/class-tsm-exchange-rate-hub-cache.php';
require_once plugin_dir_path( __FILE__ ) . 'includes/class-tsm-exchange-rate-hub-cron.php';

Tsm_Exchange_Rate_Hub_Cron::unschedule();
Tsm_Exchange_Rate_Hub_Cache::clear();
Tsm_Exchange_Rate_Hub_DB::drop_tables();

delete_option( 'tsm_erh_base_currency' );
delete_option( 'tsm_erh_enabled_currencies' );
delete_option( 'tsm_erh_update_frequency' );
delete_option( 'tsm_erh_last_updated' );
delete_option( 'tsm_erh_db_version' );
