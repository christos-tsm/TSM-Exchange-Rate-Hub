<?php

/**
 * The core plugin class.
 *
 * Loads all dependencies and wires every action/filter through the Loader.
 * Follows the WPPB pattern: admin hooks, public hooks, cron hooks, and REST
 * routes are each defined in their own method for clear separation of concerns.
 *
 * @since      1.0.0
 * @package    Tsm_Exchange_Rate_Hub
 * @subpackage Tsm_Exchange_Rate_Hub/includes
 */

if ( ! defined( 'WPINC' ) ) {
	die;
}

class Tsm_Exchange_Rate_Hub {

	protected $loader;
	protected $plugin_name;
	protected $version;

	public function __construct() {
		$this->version     = defined( 'TSM_EXCHANGE_RATE_HUB_VERSION' )
			? TSM_EXCHANGE_RATE_HUB_VERSION
			: '1.0.0';
		$this->plugin_name = 'tsm-exchange-rate-hub';

		$this->load_dependencies();
		$this->maybe_create_tables();
		$this->set_locale();
		$this->define_cron_hooks();
		$this->define_admin_hooks();
		$this->define_public_hooks();
		$this->define_rest_hooks();
		$this->define_cli_commands();
	}

	/**
	 * Create DB tables and set default options if they are missing.
	 *
	 * Covers two cases the activation hook alone cannot:
	 *   1. Plugin was already active when the code was first deployed.
	 *   2. Plugin was updated and the schema changed (version bump).
	 */
	private function maybe_create_tables() {
		$installed_version = get_option( 'tsm_erh_db_version', '0' );

		if ( version_compare( $installed_version, $this->version, '<' ) ) {
			Tsm_Exchange_Rate_Hub_DB::create_tables();

			add_option( 'tsm_erh_base_currency', 'EUR' );
			add_option( 'tsm_erh_enabled_currencies', array(
				'USD', 'GBP', 'JPY', 'CHF', 'CAD', 'AUD',
			) );
			add_option( 'tsm_erh_update_frequency', 60 );

			if ( ! wp_next_scheduled( Tsm_Exchange_Rate_Hub_Cron::HOOK ) ) {
				Tsm_Exchange_Rate_Hub_Cron::schedule();
			}
		}
	}

	private function load_dependencies() {
		$path = plugin_dir_path( dirname( __FILE__ ) );

		require_once $path . 'includes/class-tsm-exchange-rate-hub-loader.php';
		require_once $path . 'includes/class-tsm-exchange-rate-hub-i18n.php';
		require_once $path . 'includes/class-tsm-exchange-rate-hub-db.php';
		require_once $path . 'includes/class-tsm-exchange-rate-hub-api.php';
		require_once $path . 'includes/class-tsm-exchange-rate-hub-cache.php';
		require_once $path . 'includes/class-tsm-exchange-rate-hub-cron.php';
		require_once $path . 'includes/class-tsm-exchange-rate-hub-rest.php';
		require_once $path . 'includes/class-tsm-exchange-rate-hub-cli.php';
		require_once $path . 'admin/class-tsm-exchange-rate-hub-admin.php';
		require_once $path . 'public/class-tsm-exchange-rate-hub-public.php';

		$this->loader = new Tsm_Exchange_Rate_Hub_Loader();
	}

	private function set_locale() {
		$plugin_i18n = new Tsm_Exchange_Rate_Hub_i18n();
		$this->loader->add_action( 'plugins_loaded', $plugin_i18n, 'load_plugin_textdomain' );
	}

	private function define_cron_hooks() {
		$this->loader->add_filter(
			'cron_schedules',
			'Tsm_Exchange_Rate_Hub_Cron',
			'add_custom_schedule'
		);
		$this->loader->add_action(
			Tsm_Exchange_Rate_Hub_Cron::HOOK,
			'Tsm_Exchange_Rate_Hub_Cron',
			'execute_update'
		);
	}

	private function define_admin_hooks() {
		$plugin_admin = new Tsm_Exchange_Rate_Hub_Admin(
			$this->get_plugin_name(),
			$this->get_version()
		);

		$this->loader->add_action( 'admin_enqueue_scripts', $plugin_admin, 'enqueue_styles' );
		$this->loader->add_action( 'admin_enqueue_scripts', $plugin_admin, 'enqueue_scripts' );
		$this->loader->add_action( 'admin_menu', $plugin_admin, 'add_admin_menu' );
		$this->loader->add_action( 'admin_init', $plugin_admin, 'register_settings' );

		$this->loader->add_action(
			'wp_ajax_tsm_erh_refresh_rates',
			$plugin_admin,
			'ajax_refresh_rates'
		);

		// Reschedule cron & clear cache when any core setting changes.
		$this->loader->add_action(
			'update_option_tsm_erh_update_frequency',
			$plugin_admin,
			'handle_settings_update'
		);
		$this->loader->add_action(
			'update_option_tsm_erh_enabled_currencies',
			$plugin_admin,
			'handle_settings_update'
		);
		$this->loader->add_action(
			'update_option_tsm_erh_base_currency',
			$plugin_admin,
			'handle_settings_update'
		);
	}

	private function define_public_hooks() {
		$plugin_public = new Tsm_Exchange_Rate_Hub_Public(
			$this->get_plugin_name(),
			$this->get_version()
		);

		$this->loader->add_action( 'wp_enqueue_scripts', $plugin_public, 'enqueue_styles' );
		$this->loader->add_action( 'wp_enqueue_scripts', $plugin_public, 'enqueue_scripts' );
		$this->loader->add_action( 'init', $plugin_public, 'register_shortcode' );

		$this->loader->add_filter(
			'theme_page_templates',
			$plugin_public,
			'register_page_template'
		);
		$this->loader->add_filter(
			'template_include',
			$plugin_public,
			'load_page_template'
		);
	}

	private function define_rest_hooks() {
		$rest = new Tsm_Exchange_Rate_Hub_REST();
		$this->loader->add_action( 'rest_api_init', $rest, 'register_routes' );
	}

	private function define_cli_commands() {
		if ( defined( 'WP_CLI' ) && WP_CLI ) {
			WP_CLI::add_command( 'tsm-erh', 'Tsm_Exchange_Rate_Hub_CLI' );
		}
	}

	public function run() {
		$this->loader->run();
	}

	public function get_plugin_name() {
		return $this->plugin_name;
	}

	public function get_loader() {
		return $this->loader;
	}

	public function get_version() {
		return $this->version;
	}
}
