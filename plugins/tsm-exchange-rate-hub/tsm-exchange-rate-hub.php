<?php

/**
 * The plugin bootstrap file
 *
 * @link              https://github.com/christos-tsm/
 * @since             1.0.0
 * @package           Tsm_Exchange_Rate_Hub
 *
 * @wordpress-plugin
 * Plugin Name:       TSM Exchange Rate Hub
 * Plugin URI:        https://github.com/christos-tsm/TSM-Exchange-Rate-Hub
 * Description:       Exchange Rate Hub implemented as a custom plugin, designed to provide a centralized, maintainable, and extensible way to manage and display currency exchange rates inside WordPress.
 * Version:           1.0.0
 * Author:            Christos Tsamis
 * Author URI:        https://github.com/christos-tsm/
 * License:           GPL-2.0+
 * License URI:       http://www.gnu.org/licenses/gpl-2.0.txt
 * Text Domain:       tsm-exchange-rate-hub
 * Domain Path:       /languages
 */

if ( ! defined( 'WPINC' ) ) {
	die;
}

define( 'TSM_EXCHANGE_RATE_HUB_VERSION', '1.0.0' );
define( 'TSM_ERH_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'TSM_ERH_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'TSM_ERH_PLUGIN_BASENAME', plugin_basename( __FILE__ ) );

function activate_tsm_exchange_rate_hub() {
	require_once plugin_dir_path( __FILE__ ) . 'includes/class-tsm-exchange-rate-hub-activator.php';
	Tsm_Exchange_Rate_Hub_Activator::activate();
}

function deactivate_tsm_exchange_rate_hub() {
	require_once plugin_dir_path( __FILE__ ) . 'includes/class-tsm-exchange-rate-hub-deactivator.php';
	Tsm_Exchange_Rate_Hub_Deactivator::deactivate();
}

register_activation_hook( __FILE__, 'activate_tsm_exchange_rate_hub' );
register_deactivation_hook( __FILE__, 'deactivate_tsm_exchange_rate_hub' );

require plugin_dir_path( __FILE__ ) . 'includes/class-tsm-exchange-rate-hub.php';

function run_tsm_exchange_rate_hub() {
	$plugin = new Tsm_Exchange_Rate_Hub();
	$plugin->run();
}
run_tsm_exchange_rate_hub();
