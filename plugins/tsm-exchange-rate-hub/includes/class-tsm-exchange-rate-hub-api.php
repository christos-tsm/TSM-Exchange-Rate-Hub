<?php

/**
 * External API integration for exchange rates.
 *
 * Encapsulates all communication with the ExchangeRate-API free tier.
 * Fetches rates, filters by enabled currencies, and persists via DB + Cache layers.
 *
 * @since      1.0.0
 * @package    Tsm_Exchange_Rate_Hub
 * @subpackage Tsm_Exchange_Rate_Hub/includes
 */

if ( ! defined( 'WPINC' ) ) {
	die;
}

class Tsm_Exchange_Rate_Hub_API {

	/**
	 * Free tier endpoint — no API key required.
	 * @see https://www.exchangerate-api.com/docs/free
	 */
	private $api_url = 'https://open.er-api.com/v6/latest/';

	/**
	 * Fetch rates from the remote API and return a filtered associative array.
	 *
	 * @param string|null $base_currency Three-letter currency code. Falls back to saved option.
	 * @return array|WP_Error Associative array of currency => rate, or WP_Error on failure.
	 */
	public function fetch_rates( $base_currency = null ) {
		if ( $base_currency === null ) {
			$base_currency = get_option( 'tsm_erh_base_currency', 'EUR' );
		}

		$base_currency = strtoupper( sanitize_text_field( $base_currency ) );
		$url           = $this->api_url . $base_currency;

		$response = wp_remote_get( $url, array(
			'timeout' => 15,
			'headers' => array( 'Accept' => 'application/json' ),
		) );

		if ( is_wp_error( $response ) ) {
			return new WP_Error(
				'api_error',
				sprintf( 'API request failed: %s', $response->get_error_message() )
			);
		}

		$status_code = wp_remote_retrieve_response_code( $response );
		if ( $status_code !== 200 ) {
			return new WP_Error(
				'api_http_error',
				sprintf( 'API returned HTTP %d', $status_code )
			);
		}

		$body = wp_remote_retrieve_body( $response );
		$data = json_decode( $body, true );

		if ( json_last_error() !== JSON_ERROR_NONE ) {
			return new WP_Error( 'api_json_error', 'Failed to parse API response' );
		}

		if ( ! isset( $data['result'] ) || $data['result'] !== 'success' ) {
			return new WP_Error( 'api_response_error', 'API returned unsuccessful response' );
		}

		if ( ! isset( $data['rates'] ) || ! is_array( $data['rates'] ) ) {
			return new WP_Error( 'api_data_error', 'No rates found in API response' );
		}

		$enabled_currencies = get_option( 'tsm_erh_enabled_currencies', array() );
		$filtered_rates     = array();

		if ( ! empty( $enabled_currencies ) ) {
			foreach ( $data['rates'] as $currency => $rate ) {
				if ( in_array( $currency, $enabled_currencies, true ) ) {
					$filtered_rates[ $currency ] = (float) $rate;
				}
			}
		} else {
			$filtered_rates = array_map( 'floatval', $data['rates'] );
		}

		return $filtered_rates;
	}

	/**
	 * Fetch from the API and persist to DB + Cache in one step.
	 *
	 * @param string|null $base_currency
	 * @return array|WP_Error
	 */
	public function fetch_and_store( $base_currency = null ) {
		$rates = $this->fetch_rates( $base_currency );

		if ( is_wp_error( $rates ) ) {
			return $rates;
		}

		if ( $base_currency === null ) {
			$base_currency = get_option( 'tsm_erh_base_currency', 'EUR' );
		}

		Tsm_Exchange_Rate_Hub_DB::save_rates( $base_currency, $rates );
		Tsm_Exchange_Rate_Hub_Cache::clear();
		Tsm_Exchange_Rate_Hub_Cache::set_rates( $base_currency, $rates );

		return $rates;
	}

	/**
	 * Static lookup of supported currencies with human-readable names.
	 */
	public static function get_supported_currencies() {
		return array(
			'AED' => 'UAE Dirham',
			'ARS' => 'Argentine Peso',
			'AUD' => 'Australian Dollar',
			'BGN' => 'Bulgarian Lev',
			'BRL' => 'Brazilian Real',
			'CAD' => 'Canadian Dollar',
			'CHF' => 'Swiss Franc',
			'CLP' => 'Chilean Peso',
			'CNY' => 'Chinese Yuan',
			'COP' => 'Colombian Peso',
			'CZK' => 'Czech Koruna',
			'DKK' => 'Danish Krone',
			'EGP' => 'Egyptian Pound',
			'EUR' => 'Euro',
			'GBP' => 'British Pound',
			'GEL' => 'Georgian Lari',
			'HKD' => 'Hong Kong Dollar',
			'HUF' => 'Hungarian Forint',
			'IDR' => 'Indonesian Rupiah',
			'ILS' => 'Israeli Shekel',
			'INR' => 'Indian Rupee',
			'ISK' => 'Icelandic Króna',
			'JPY' => 'Japanese Yen',
			'KRW' => 'South Korean Won',
			'MXN' => 'Mexican Peso',
			'MYR' => 'Malaysian Ringgit',
			'NOK' => 'Norwegian Krone',
			'NZD' => 'New Zealand Dollar',
			'PHP' => 'Philippine Peso',
			'PLN' => 'Polish Złoty',
			'RON' => 'Romanian Leu',
			'RUB' => 'Russian Ruble',
			'SAR' => 'Saudi Riyal',
			'SEK' => 'Swedish Krona',
			'SGD' => 'Singapore Dollar',
			'THB' => 'Thai Baht',
			'TRY' => 'Turkish Lira',
			'TWD' => 'Taiwan Dollar',
			'UAH' => 'Ukrainian Hryvnia',
			'USD' => 'US Dollar',
			'ZAR' => 'South African Rand',
		);
	}
}
