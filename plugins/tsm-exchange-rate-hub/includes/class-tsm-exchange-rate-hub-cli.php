<?php

/**
 * WP-CLI commands for the Exchange Rate Hub.
 *
 * Provides command-line access to rates, refresh, cache management,
 * and plugin status. Only loaded when WP_CLI is available.
 *
 * Usage:
 *   wp tsm-erh rates [--base=<code>] [--format=<table|json|csv>]
 *   wp tsm-erh refresh [--base=<code>]
 *   wp tsm-erh status
 *   wp tsm-erh cache clear
 *   wp tsm-erh history <base> <target> [--limit=<n>]
 *
 * @since      1.0.0
 * @package    Tsm_Exchange_Rate_Hub
 * @subpackage Tsm_Exchange_Rate_Hub/includes
 */

if ( ! defined( 'WPINC' ) ) {
	die;
}

class Tsm_Exchange_Rate_Hub_CLI {

	/**
	 * Display the latest exchange rates.
	 *
	 * ## OPTIONS
	 *
	 * [--base=<code>]
	 * : Base currency code (defaults to plugin setting).
	 *
	 * [--format=<format>]
	 * : Output format.
	 * ---
	 * default: table
	 * options:
	 *   - table
	 *   - json
	 *   - csv
	 *   - yaml
	 * ---
	 *
	 * ## EXAMPLES
	 *
	 *     wp tsm-erh rates
	 *     wp tsm-erh rates --base=USD
	 *     wp tsm-erh rates --format=json
	 *
	 * @subcommand rates
	 */
	public function rates( $args, $assoc_args ) {
		$base = strtoupper( $assoc_args['base'] ?? get_option( 'tsm_erh_base_currency', 'EUR' ) );

		$cached = Tsm_Exchange_Rate_Hub_Cache::get_rates( $base );

		if ( $cached !== null ) {
			$rows = $this->cached_to_rows( $cached );
		} else {
			$db_rates = Tsm_Exchange_Rate_Hub_DB::get_latest_rates( $base );
			if ( empty( $db_rates ) ) {
				WP_CLI::warning( "No rates found for base currency {$base}. Run 'wp tsm-erh refresh' first." );
				return;
			}
			$rows = $this->db_to_rows( $db_rates );
		}

		$supported = Tsm_Exchange_Rate_Hub_API::get_supported_currencies();
		foreach ( $rows as &$row ) {
			$row['name'] = $supported[ $row['currency'] ] ?? '';
		}
		unset( $row );

		WP_CLI::success( sprintf( 'Base: %s — %d currencies', $base, count( $rows ) ) );

		WP_CLI\Utils\format_items(
			$assoc_args['format'] ?? 'table',
			$rows,
			array( 'currency', 'name', 'rate' )
		);
	}

	/**
	 * Refresh rates from the external API.
	 *
	 * ## OPTIONS
	 *
	 * [--base=<code>]
	 * : Base currency code (defaults to plugin setting).
	 *
	 * ## EXAMPLES
	 *
	 *     wp tsm-erh refresh
	 *     wp tsm-erh refresh --base=USD
	 *
	 * @subcommand refresh
	 */
	public function refresh( $args, $assoc_args ) {
		$base = isset( $assoc_args['base'] )
			? strtoupper( $assoc_args['base'] )
			: null;

		WP_CLI::log( 'Fetching rates from API…' );

		$api    = new Tsm_Exchange_Rate_Hub_API();
		$result = $api->fetch_and_store( $base );

		if ( is_wp_error( $result ) ) {
			WP_CLI::error( $result->get_error_message() );
		}

		$actual_base = $base ?? get_option( 'tsm_erh_base_currency', 'EUR' );
		WP_CLI::success( sprintf(
			'Fetched %d rates for base %s. Last updated: %s',
			count( $result ),
			$actual_base,
			get_option( 'tsm_erh_last_updated', 'N/A' )
		) );
	}

	/**
	 * Show plugin status information.
	 *
	 * ## EXAMPLES
	 *
	 *     wp tsm-erh status
	 *
	 * @subcommand status
	 */
	public function status( $args, $assoc_args ) {
		$base         = get_option( 'tsm_erh_base_currency', 'EUR' );
		$frequency    = get_option( 'tsm_erh_update_frequency', 60 );
		$last_updated = get_option( 'tsm_erh_last_updated', 'Never' );
		$db_version   = get_option( 'tsm_erh_db_version', 'N/A' );
		$enabled      = get_option( 'tsm_erh_enabled_currencies', array() );
		$next_cron    = wp_next_scheduled( Tsm_Exchange_Rate_Hub_Cron::HOOK );
		$rates_count  = count( Tsm_Exchange_Rate_Hub_DB::get_latest_rates( $base ) );
		$cache_hit    = Tsm_Exchange_Rate_Hub_Cache::is_cached( $base ) ? 'Yes' : 'No';

		$info = array(
			array( 'key' => 'Plugin Version',      'value' => TSM_EXCHANGE_RATE_HUB_VERSION ),
			array( 'key' => 'DB Version',           'value' => $db_version ),
			array( 'key' => 'Base Currency',        'value' => $base ),
			array( 'key' => 'Enabled Currencies',   'value' => implode( ', ', $enabled ) ),
			array( 'key' => 'Update Frequency',     'value' => $frequency . ' min' ),
			array( 'key' => 'Last Updated',         'value' => $last_updated ),
			array( 'key' => 'Next Scheduled',       'value' => $next_cron
				? date_i18n( 'Y-m-d H:i:s', $next_cron + ( (float) get_option( 'gmt_offset' ) * HOUR_IN_SECONDS ) )
				: 'Not scheduled'
			),
			array( 'key' => 'Stored Rates',         'value' => (string) $rates_count ),
			array( 'key' => 'Cache Active',         'value' => $cache_hit ),
		);

		WP_CLI\Utils\format_items( 'table', $info, array( 'key', 'value' ) );
	}

	/**
	 * Manage the transient cache.
	 *
	 * ## OPTIONS
	 *
	 * <action>
	 * : Action to perform.
	 * ---
	 * options:
	 *   - clear
	 * ---
	 *
	 * ## EXAMPLES
	 *
	 *     wp tsm-erh cache clear
	 *
	 * @subcommand cache
	 */
	public function cache( $args, $assoc_args ) {
		$action = $args[0] ?? '';

		if ( $action !== 'clear' ) {
			WP_CLI::error( "Unknown action '{$action}'. Available: clear" );
		}

		Tsm_Exchange_Rate_Hub_Cache::clear();
		WP_CLI::success( 'Transient cache cleared.' );
	}

	/**
	 * Display historical rates for a currency pair.
	 *
	 * ## OPTIONS
	 *
	 * <base>
	 * : Base currency code (e.g. EUR).
	 *
	 * <target>
	 * : Target currency code (e.g. USD).
	 *
	 * [--limit=<n>]
	 * : Number of records to show.
	 * ---
	 * default: 20
	 * ---
	 *
	 * [--format=<format>]
	 * : Output format.
	 * ---
	 * default: table
	 * options:
	 *   - table
	 *   - json
	 *   - csv
	 *   - yaml
	 * ---
	 *
	 * ## EXAMPLES
	 *
	 *     wp tsm-erh history EUR USD
	 *     wp tsm-erh history EUR GBP --limit=50 --format=json
	 *
	 * @subcommand history
	 */
	public function history( $args, $assoc_args ) {
		$base   = strtoupper( $args[0] ?? '' );
		$target = strtoupper( $args[1] ?? '' );
		$limit  = (int) ( $assoc_args['limit'] ?? 20 );

		if ( empty( $base ) || empty( $target ) ) {
			WP_CLI::error( 'Usage: wp tsm-erh history <base> <target>' );
		}

		$rows = Tsm_Exchange_Rate_Hub_DB::get_history( $base, $target, $limit );

		if ( empty( $rows ) ) {
			WP_CLI::warning( "No history found for {$base}/{$target}." );
			return;
		}

		WP_CLI::success( sprintf( '%s/%s — %d records', $base, $target, count( $rows ) ) );

		WP_CLI\Utils\format_items(
			$assoc_args['format'] ?? 'table',
			$rows,
			array( 'rate', 'recorded_at' )
		);
	}

	/* ── Helpers ── */

	private function cached_to_rows( $cached ) {
		$rows = array();
		foreach ( $cached as $currency => $rate ) {
			$rows[] = array(
				'currency' => $currency,
				'rate'     => number_format( (float) $rate, 6, '.', '' ),
			);
		}
		return $rows;
	}

	private function db_to_rows( $db_rates ) {
		$rows = array();
		foreach ( $db_rates as $row ) {
			$rows[] = array(
				'currency' => $row['target_currency'],
				'rate'     => number_format( (float) $row['rate'], 6, '.', '' ),
			);
		}
		return $rows;
	}
}
