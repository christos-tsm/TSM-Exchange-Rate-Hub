<?php

/**
 * Transient-based caching layer for exchange rates.
 *
 * Wraps WordPress Transients API to prevent unnecessary API/DB reads.
 * TTL is derived from the configured update frequency so cached data
 * naturally expires just before the next scheduled fetch.
 *
 * If an object cache backend (Redis, Memcached) is available,
 * WordPress transparently upgrades transients to it.
 *
 * @since      1.0.0
 * @package    Tsm_Exchange_Rate_Hub
 * @subpackage Tsm_Exchange_Rate_Hub/includes
 */

if ( ! defined( 'WPINC' ) ) {
	die;
}

class Tsm_Exchange_Rate_Hub_Cache {

	private static $cache_key_prefix = 'tsm_erh_rates_';

	/**
	 * Retrieve cached rates for a given base currency.
	 *
	 * @return array|null Associative array of currency => rate, or null on miss.
	 */
	public static function get_rates( $base_currency = null ) {
		if ( $base_currency === null ) {
			$base_currency = get_option( 'tsm_erh_base_currency', 'EUR' );
		}

		$cached = get_transient( self::$cache_key_prefix . strtoupper( $base_currency ) );

		return ( $cached !== false ) ? $cached : null;
	}

	public static function set_rates( $base_currency, $rates ) {
		$frequency = (int) get_option( 'tsm_erh_update_frequency', 60 );
		$ttl       = $frequency * MINUTE_IN_SECONDS;

		set_transient(
			self::$cache_key_prefix . strtoupper( $base_currency ),
			$rates,
			$ttl
		);
	}

	/**
	 * Purge every transient belonging to this plugin.
	 *
	 * Runs a direct query because WP has no wildcard delete_transient().
	 */
	public static function clear() {
		global $wpdb;

		$wpdb->query(
			"DELETE FROM {$wpdb->options}
			 WHERE option_name LIKE '_transient_tsm_erh_rates_%'
			    OR option_name LIKE '_transient_timeout_tsm_erh_rates_%'"
		);
	}

	public static function is_cached( $base_currency = null ) {
		return self::get_rates( $base_currency ) !== null;
	}
}
