<?php

/**
 * Database operations for exchange rates.
 *
 * Handles custom table creation, CRUD operations for latest
 * and historical exchange rate data.
 *
 * @since      1.0.0
 * @package    Tsm_Exchange_Rate_Hub
 * @subpackage Tsm_Exchange_Rate_Hub/includes
 */

if ( ! defined( 'WPINC' ) ) {
	die;
}

class Tsm_Exchange_Rate_Hub_DB {

	private static $rates_table   = 'tsm_exchange_rates';
	private static $history_table = 'tsm_exchange_rates_history';

	public static function get_rates_table() {
		global $wpdb;
		return $wpdb->prefix . self::$rates_table;
	}

	public static function get_history_table() {
		global $wpdb;
		return $wpdb->prefix . self::$history_table;
	}

	/**
	 * Create custom database tables.
	 *
	 * Uses dbDelta for safe, idempotent table creation/updates.
	 * Latest rates use a UNIQUE composite key so REPLACE works atomically.
	 * History table is append-only with an index on (base, target, date) for efficient range queries.
	 */
	public static function create_tables() {
		global $wpdb;

		$charset_collate = $wpdb->get_charset_collate();
		$rates_table     = self::get_rates_table();
		$history_table   = self::get_history_table();

		$sql = "CREATE TABLE {$rates_table} (
			id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
			base_currency VARCHAR(3) NOT NULL,
			target_currency VARCHAR(3) NOT NULL,
			rate DECIMAL(20,10) NOT NULL,
			last_updated DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY  (id),
			UNIQUE KEY base_target (base_currency, target_currency)
		) {$charset_collate};

		CREATE TABLE {$history_table} (
			id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
			base_currency VARCHAR(3) NOT NULL,
			target_currency VARCHAR(3) NOT NULL,
			rate DECIMAL(20,10) NOT NULL,
			recorded_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY  (id),
			KEY idx_currencies_date (base_currency, target_currency, recorded_at)
		) {$charset_collate};";

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		dbDelta( $sql );

		update_option( 'tsm_erh_db_version', TSM_EXCHANGE_RATE_HUB_VERSION );
	}

	public static function drop_tables() {
		global $wpdb;
		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- table names are hardcoded constants
		$wpdb->query( "DROP TABLE IF EXISTS " . self::get_rates_table() );
		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		$wpdb->query( "DROP TABLE IF EXISTS " . self::get_history_table() );
		delete_option( 'tsm_erh_db_version' );
	}

	/**
	 * Persist fetched rates into both the latest and history tables.
	 *
	 * REPLACE INTO upserts the latest table (one row per currency pair).
	 * INSERT appends to history for time-series tracking.
	 */
	public static function save_rates( $base_currency, $rates ) {
		global $wpdb;

		$table         = self::get_rates_table();
		$history_table = self::get_history_table();
		$now           = current_time( 'mysql' );

		foreach ( $rates as $currency => $rate ) {
			$wpdb->replace(
				$table,
				array(
					'base_currency'   => $base_currency,
					'target_currency' => $currency,
					'rate'            => $rate,
					'last_updated'    => $now,
				),
				array( '%s', '%s', '%f', '%s' )
			);

			$wpdb->insert(
				$history_table,
				array(
					'base_currency'   => $base_currency,
					'target_currency' => $currency,
					'rate'            => $rate,
					'recorded_at'     => $now,
				),
				array( '%s', '%s', '%f', '%s' )
			);
		}

		update_option( 'tsm_erh_last_updated', $now );
	}

	public static function get_latest_rates( $base_currency = null ) {
		global $wpdb;

		$table = self::get_rates_table();

		if ( $base_currency === null ) {
			$base_currency = get_option( 'tsm_erh_base_currency', 'EUR' );
		}

		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$results = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT target_currency, rate, last_updated FROM {$table} WHERE base_currency = %s ORDER BY target_currency ASC",
				$base_currency
			),
			ARRAY_A
		);

		return $results ? $results : array();
	}

	public static function get_rate( $base_currency, $target_currency ) {
		global $wpdb;

		$table = self::get_rates_table();

		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		return $wpdb->get_row(
			$wpdb->prepare(
				"SELECT rate, last_updated FROM {$table} WHERE base_currency = %s AND target_currency = %s",
				$base_currency,
				$target_currency
			),
			ARRAY_A
		);
	}

	public static function get_history( $base_currency, $target_currency, $limit = 30 ) {
		global $wpdb;

		$table = self::get_history_table();

		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		return $wpdb->get_results(
			$wpdb->prepare(
				"SELECT rate, recorded_at FROM {$table} WHERE base_currency = %s AND target_currency = %s ORDER BY recorded_at DESC LIMIT %d",
				$base_currency,
				$target_currency,
				$limit
			),
			ARRAY_A
		);
	}
}
