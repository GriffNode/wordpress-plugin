<?php
/**
 * Plugin Name: GriffNode Payments
 * Plugin URI:  https://docs.griffnode.com
 * Description: Accept Bitcoin, Litecoin, Dogecoin and Dash payments on any WordPress site via GriffNode. Drop a pay button anywhere with the [griffnode_button] shortcode — no WooCommerce required.
 * Version:     1.0.0
 * Author:      GriffNode
 * Author URI:  https://griffnode.com
 * License:     MIT
 * Text Domain: griffnode-payments
 * Requires at least: 6.0
 * Requires PHP: 7.4
 */

defined( 'ABSPATH' ) || exit;

define( 'GRIFFNODE_VERSION',    '1.0.0' );
define( 'GRIFFNODE_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'GRIFFNODE_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'GRIFFNODE_API_BASE',   'https://api.griffnode.com' );

require_once GRIFFNODE_PLUGIN_DIR . 'includes/class-griffnode-settings.php';
require_once GRIFFNODE_PLUGIN_DIR . 'includes/class-griffnode-api.php';
require_once GRIFFNODE_PLUGIN_DIR . 'includes/class-griffnode-shortcode.php';
require_once GRIFFNODE_PLUGIN_DIR . 'includes/class-griffnode-webhook.php';

add_action( 'plugins_loaded', function () {
    GriffNode_Settings::init();
    GriffNode_Shortcode::init();
    GriffNode_Webhook::init();
} );
