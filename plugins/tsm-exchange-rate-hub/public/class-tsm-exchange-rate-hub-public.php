<?php

/**
 * Public-facing functionality of the plugin.
 *
 * Registers the [tsm_exchange_rates] shortcode, enqueues front-end
 * assets, and provides a custom page template for theme integration.
 *
 * @since      1.0.0
 * @package    Tsm_Exchange_Rate_Hub
 * @subpackage Tsm_Exchange_Rate_Hub/public
 */

if ( ! defined( 'WPINC' ) ) {
	die;
}

class Tsm_Exchange_Rate_Hub_Public {

	private $plugin_name;
	private $version;

	public function __construct( $plugin_name, $version ) {
		$this->plugin_name = $plugin_name;
		$this->version     = $version;
	}

	public function enqueue_styles() {
		wp_enqueue_style(
			$this->plugin_name,
			plugin_dir_url( __FILE__ ) . 'css/tsm-exchange-rate-hub-public.css',
			array(),
			$this->version,
			'all'
		);
	}

	public function enqueue_scripts() {
		wp_enqueue_script(
			$this->plugin_name,
			plugin_dir_url( __FILE__ ) . 'js/tsm-exchange-rate-hub-public.js',
			array( 'jquery' ),
			$this->version,
			true
		);
	}

	/* ───── Shortcode ───── */

	public function register_shortcode() {
		add_shortcode( 'tsm_exchange_rates', array( $this, 'render_shortcode' ) );
	}

	/**
	 * Render the [tsm_exchange_rates] shortcode.
	 *
	 * Attributes:
	 *   base         – Override the configured base currency (e.g. "USD").
	 *   currencies   – Comma-separated list to filter display (e.g. "USD,GBP,JPY").
	 *   title        – Heading shown above the rates grid.
	 *   show_updated – "yes" (default) or "no".
	 */
	public function render_shortcode( $atts ) {
		$atts = shortcode_atts( array(
			'base'         => '',
			'currencies'   => '',
			'title'        => __( 'Exchange Rates', 'tsm-exchange-rate-hub' ),
			'show_updated' => 'yes',
		), $atts, 'tsm_exchange_rates' );

		$base = ! empty( $atts['base'] )
			? strtoupper( sanitize_text_field( $atts['base'] ) )
			: get_option( 'tsm_erh_base_currency', 'EUR' );

		$filter_currencies = array();
		if ( ! empty( $atts['currencies'] ) ) {
			$filter_currencies = array_map( 'trim',
				array_map( 'strtoupper',
					explode( ',', sanitize_text_field( $atts['currencies'] ) )
				)
			);
		}

		$title        = sanitize_text_field( $atts['title'] );
		$show_updated = ( $atts['show_updated'] === 'yes' );

		$rates_data = $this->get_display_rates( $base, $filter_currencies );

		$last_updated = get_option( 'tsm_erh_last_updated', '' );
		$supported    = Tsm_Exchange_Rate_Hub_API::get_supported_currencies();

		ob_start();
		include plugin_dir_path( __FILE__ ) . 'partials/tsm-exchange-rate-hub-public-display.php';
		return ob_get_clean();
	}

	/**
	 * Resolve rates from cache → DB, applying any currency filter.
	 */
	private function get_display_rates( $base, $filter_currencies ) {
		$cached_rates = Tsm_Exchange_Rate_Hub_Cache::get_rates( $base );

		if ( $cached_rates !== null ) {
			return $this->filter_cached_rates( $cached_rates, $filter_currencies );
		}

		$db_rates   = Tsm_Exchange_Rate_Hub_DB::get_latest_rates( $base );
		$rates_data = array();
		$cache_data = array();

		foreach ( $db_rates as $row ) {
			$cache_data[ $row['target_currency'] ] = (float) $row['rate'];

			if ( ! empty( $filter_currencies ) && ! in_array( $row['target_currency'], $filter_currencies, true ) ) {
				continue;
			}

			$rates_data[] = array(
				'target_currency' => $row['target_currency'],
				'rate'            => (float) $row['rate'],
			);
		}

		if ( ! empty( $cache_data ) ) {
			Tsm_Exchange_Rate_Hub_Cache::set_rates( $base, $cache_data );
		}

		return $rates_data;
	}

	private function filter_cached_rates( $cached_rates, $filter_currencies ) {
		$rates_data = array();

		foreach ( $cached_rates as $currency => $rate ) {
			if ( ! empty( $filter_currencies ) && ! in_array( $currency, $filter_currencies, true ) ) {
				continue;
			}
			$rates_data[] = array(
				'target_currency' => $currency,
				'rate'            => (float) $rate,
			);
		}

		return $rates_data;
	}

	/* ───── Page Template (Theme Integration) ───── */

	/**
	 * Register a custom page template selectable in the Page Attributes meta box.
	 */
	public function register_page_template( $templates ) {
		$templates['tsm-exchange-rates-template'] = __( 'Exchange Rates', 'tsm-exchange-rate-hub' );
		return $templates;
	}

	/**
	 * Load the plugin-provided template file when it is selected.
	 */
	public function load_page_template( $template ) {
		if ( is_page() ) {
			$page_template = get_page_template_slug();
			if ( $page_template === 'tsm-exchange-rates-template' ) {
				$plugin_template = plugin_dir_path( dirname( __FILE__ ) ) . 'templates/page-exchange-rates.php';
				if ( file_exists( $plugin_template ) ) {
					return $plugin_template;
				}
			}
		}
		return $template;
	}
}
