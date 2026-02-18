<?php

/**
 * Custom REST API endpoints (Bonus feature).
 *
 * Exposes exchange rates via /wp-json/tsm-exchange-rate-hub/v1/
 * so external consumers or JS front-ends can fetch data.
 *
 * @since      1.0.0
 * @package    Tsm_Exchange_Rate_Hub
 * @subpackage Tsm_Exchange_Rate_Hub/includes
 */

if ( ! defined( 'WPINC' ) ) {
	die;
}

class Tsm_Exchange_Rate_Hub_REST {

	private $namespace = 'tsm-exchange-rate-hub/v1';

	public function register_routes() {
		register_rest_route( $this->namespace, '/rates', array(
			'methods'             => WP_REST_Server::READABLE,
			'callback'            => array( $this, 'get_rates' ),
			'permission_callback' => '__return_true',
			'args'                => array(
				'base' => array(
					'description'       => 'Base currency code',
					'type'              => 'string',
					'sanitize_callback' => 'sanitize_text_field',
					'default'           => '',
				),
			),
		) );

		register_rest_route( $this->namespace, '/rates/(?P<base>[A-Z]{3})', array(
			'methods'             => WP_REST_Server::READABLE,
			'callback'            => array( $this, 'get_rates_by_base' ),
			'permission_callback' => '__return_true',
			'args'                => array(
				'base' => array(
					'description'       => 'Base currency code',
					'type'              => 'string',
					'validate_callback' => function ( $param ) {
						return preg_match( '/^[A-Z]{3}$/', $param );
					},
				),
			),
		) );

		register_rest_route( $this->namespace, '/refresh', array(
			'methods'             => WP_REST_Server::CREATABLE,
			'callback'            => array( $this, 'refresh_rates' ),
			'permission_callback' => function () {
				return current_user_can( 'manage_options' );
			},
		) );
	}

	public function get_rates( $request ) {
		$base = strtoupper( $request->get_param( 'base' ) );
		if ( empty( $base ) ) {
			$base = get_option( 'tsm_erh_base_currency', 'EUR' );
		}
		return $this->build_rates_response( $base );
	}

	public function get_rates_by_base( $request ) {
		return $this->build_rates_response( strtoupper( $request['base'] ) );
	}

	public function refresh_rates() {
		$api    = new Tsm_Exchange_Rate_Hub_API();
		$result = $api->fetch_and_store();

		if ( is_wp_error( $result ) ) {
			return new WP_REST_Response( array(
				'success' => false,
				'message' => $result->get_error_message(),
			), 500 );
		}

		return new WP_REST_Response( array(
			'success' => true,
			'message' => 'Rates refreshed successfully',
			'rates'   => $result,
		), 200 );
	}

	private function build_rates_response( $base ) {
		$cached_rates = Tsm_Exchange_Rate_Hub_Cache::get_rates( $base );

		if ( $cached_rates !== null ) {
			return new WP_REST_Response( array(
				'success'      => true,
				'base'         => $base,
				'rates'        => $cached_rates,
				'last_updated' => get_option( 'tsm_erh_last_updated', '' ),
				'cached'       => true,
			), 200 );
		}

		$db_rates = Tsm_Exchange_Rate_Hub_DB::get_latest_rates( $base );

		if ( empty( $db_rates ) ) {
			return new WP_REST_Response( array(
				'success' => false,
				'message' => 'No rates available for the requested base currency.',
			), 404 );
		}

		$rates        = array();
		$last_updated = '';

		foreach ( $db_rates as $row ) {
			$rates[ $row['target_currency'] ] = (float) $row['rate'];
			$last_updated                     = $row['last_updated'];
		}

		Tsm_Exchange_Rate_Hub_Cache::set_rates( $base, $rates );

		return new WP_REST_Response( array(
			'success'      => true,
			'base'         => $base,
			'rates'        => $rates,
			'last_updated' => $last_updated,
			'cached'       => false,
		), 200 );
	}
}
