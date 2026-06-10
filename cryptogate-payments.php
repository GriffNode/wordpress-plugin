<?php
/**
 * Plugin Name: CryptoGate Payments
 * Plugin URI:  https://docs.cryptogate.live
 * Description: Accept Bitcoin, Litecoin, Dogecoin and Dash payments on any WordPress site via CryptoGate. Drop a pay button anywhere with the [cryptogate_button] shortcode — no WooCommerce required.
 * Version:     1.0.0
 * Author:      CryptoGate
 * Author URI:  https://cryptogate.live
 * License:     MIT
 * Text Domain: cryptogate-payments
 * Requires at least: 6.0
 * Requires PHP: 7.4
 */

defined( 'ABSPATH' ) || exit;

define( 'CRYPTOGATE_VERSION',    '1.0.0' );
define( 'CRYPTOGATE_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'CRYPTOGATE_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'CRYPTOGATE_API_BASE',   'https://api.cryptogate.live' );

require_once CRYPTOGATE_PLUGIN_DIR . 'includes/class-cryptogate-settings.php';
require_once CRYPTOGATE_PLUGIN_DIR . 'includes/class-cryptogate-api.php';
require_once CRYPTOGATE_PLUGIN_DIR . 'includes/class-cryptogate-shortcode.php';
require_once CRYPTOGATE_PLUGIN_DIR . 'includes/class-cryptogate-webhook.php';

add_action( 'plugins_loaded', function () {
    CryptoGate_Settings::init();
    CryptoGate_Shortcode::init();
    CryptoGate_Webhook::init();
} );
