<?php
/**
 * Plugin Name: GriffNode Crypto Payments
 * Plugin URI:  https://docs.griffnode.com/
 * Description: Accept Bitcoin, Ethereum, Litecoin, Dogecoin, Dash and USDT/USDC/DAI stablecoins on any WordPress site. Drop a pay button anywhere with the [griffnode_button] shortcode, no WooCommerce required. Non-custodial, no chargebacks.
 * Version:     1.0.2
 * Author:      GriffNode
 * Author URI:  https://griffnode.com
 * License:     GPLv2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: griffnode-crypto-payments
 * Requires at least: 6.0
 * Requires PHP: 7.4
 */

defined( 'ABSPATH' ) || exit;

define( 'GRIFFNODE_VERSION',    '1.0.2' );
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
    if ( ! wp_next_scheduled( 'griffnode_sync_pending' ) ) {
        wp_schedule_event( time() + 300, 'griffnode_five_minutes', 'griffnode_sync_pending' );
    }
} );

add_filter( 'cron_schedules', function ( $schedules ) {
    $schedules['griffnode_five_minutes'] = [ 'interval' => 300, 'display' => 'Every 5 minutes (GriffNode)' ];
    return $schedules;
} );

register_deactivation_hook( __FILE__, function () {
    wp_clear_scheduled_hook( 'griffnode_sync_pending' );
} );
