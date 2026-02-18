<?php

/**
 * Admin-specific functionality of the plugin.
 *
 * Registers the admin menu pages (dashboard + settings), handles
 * the Settings API integration, and exposes an AJAX endpoint for
 * manual rate refreshes.
 *
 * @since      1.0.0
 * @package    Tsm_Exchange_Rate_Hub
 * @subpackage Tsm_Exchange_Rate_Hub/admin
 */

if ( ! defined( 'WPINC' ) ) {
	die;
}

class Tsm_Exchange_Rate_Hub_Admin {

	private $plugin_name;
	private $version;

	public function __construct( $plugin_name, $version ) {
		$this->plugin_name = $plugin_name;
		$this->version     = $version;
	}

	public function enqueue_styles( $hook ) {
		if ( strpos( $hook, 'tsm-exchange-rate-hub' ) === false ) {
			return;
		}

		wp_enqueue_style(
			$this->plugin_name,
			plugin_dir_url( __FILE__ ) . 'css/tsm-exchange-rate-hub-admin.css',
			array(),
			$this->version,
			'all'
		);
	}

	public function enqueue_scripts( $hook ) {
		if ( strpos( $hook, 'tsm-exchange-rate-hub' ) === false ) {
			return;
		}

		wp_enqueue_script(
			$this->plugin_name,
			plugin_dir_url( __FILE__ ) . 'js/tsm-exchange-rate-hub-admin.js',
			array( 'jquery' ),
			$this->version,
			true
		);

		wp_localize_script( $this->plugin_name, 'tsmErh', array(
			'ajaxUrl' => admin_url( 'admin-ajax.php' ),
			'nonce'   => wp_create_nonce( 'tsm_erh_admin_nonce' ),
			'strings' => array(
				'refreshing' => __( 'Refreshing…', 'tsm-exchange-rate-hub' ),
				'success'    => __( 'Rates updated successfully!', 'tsm-exchange-rate-hub' ),
				'error'      => __( 'Failed to update rates.', 'tsm-exchange-rate-hub' ),
			),
		) );
	}

	/* ───── Menu Pages ───── */

	public function add_admin_menu() {
		add_menu_page(
			__( 'Exchange Rate Hub', 'tsm-exchange-rate-hub' ),
			__( 'Exchange Rates', 'tsm-exchange-rate-hub' ),
			'manage_options',
			'tsm-exchange-rate-hub',
			array( $this, 'display_dashboard_page' ),
			'dashicons-money-alt',
			30
		);

		add_submenu_page(
			'tsm-exchange-rate-hub',
			__( 'Dashboard', 'tsm-exchange-rate-hub' ),
			__( 'Dashboard', 'tsm-exchange-rate-hub' ),
			'manage_options',
			'tsm-exchange-rate-hub',
			array( $this, 'display_dashboard_page' )
		);

		add_submenu_page(
			'tsm-exchange-rate-hub',
			__( 'Settings', 'tsm-exchange-rate-hub' ),
			__( 'Settings', 'tsm-exchange-rate-hub' ),
			'manage_options',
			'tsm-exchange-rate-hub-settings',
			array( $this, 'display_settings_page' )
		);
	}

	/* ───── Settings API ───── */

	public function register_settings() {
		register_setting( 'tsm_erh_settings_group', 'tsm_erh_base_currency', array(
			'type'              => 'string',
			'sanitize_callback' => 'sanitize_text_field',
			'default'           => 'EUR',
		) );

		register_setting( 'tsm_erh_settings_group', 'tsm_erh_enabled_currencies', array(
			'type'              => 'array',
			'sanitize_callback' => array( $this, 'sanitize_currencies' ),
			'default'           => array( 'USD', 'GBP', 'JPY', 'CHF', 'CAD', 'AUD' ),
		) );

		register_setting( 'tsm_erh_settings_group', 'tsm_erh_update_frequency', array(
			'type'              => 'integer',
			'sanitize_callback' => array( $this, 'sanitize_frequency' ),
			'default'           => 60,
		) );
	}

	public function sanitize_currencies( $input ) {
		if ( ! is_array( $input ) ) {
			return array();
		}
		return array_map( 'sanitize_text_field', $input );
	}

	public function sanitize_frequency( $input ) {
		$value = absint( $input );
		return max( 5, min( 1440, $value ) );
	}

	/* ───── Page Renderers ───── */

	public function display_dashboard_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		include plugin_dir_path( __FILE__ ) . 'partials/tsm-exchange-rate-hub-admin-display.php';
	}

	public function display_settings_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		include plugin_dir_path( __FILE__ ) . 'partials/tsm-exchange-rate-hub-admin-settings.php';
	}

	/* ───── AJAX ───── */

	public function ajax_refresh_rates() {
		check_ajax_referer( 'tsm_erh_admin_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array(
				'message' => __( 'Unauthorized', 'tsm-exchange-rate-hub' ),
			) );
		}

		$api    = new Tsm_Exchange_Rate_Hub_API();
		$result = $api->fetch_and_store();

		if ( is_wp_error( $result ) ) {
			wp_send_json_error( array( 'message' => $result->get_error_message() ) );
		}

		wp_send_json_success( array(
			'message'      => __( 'Rates updated successfully!', 'tsm-exchange-rate-hub' ),
			'last_updated' => get_option( 'tsm_erh_last_updated', '' ),
			'rates'        => $result,
		) );
	}

	/**
	 * Called whenever a core plugin option is updated.
	 * Clears the transient cache and reschedules WP-Cron.
	 */
	public function handle_settings_update() {
		Tsm_Exchange_Rate_Hub_Cache::clear();
		Tsm_Exchange_Rate_Hub_Cron::reschedule();
	}
}
