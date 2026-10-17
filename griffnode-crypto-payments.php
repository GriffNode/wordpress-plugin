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

define( 'GRIFFNODE_PAY_VERSION',    '1.0.2' );
define( 'GRIFFNODE_PAY_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'GRIFFNODE_PAY_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'GRIFFNODE_PAY_API_BASE',   'https://api.griffnode.com' );

require_once GRIFFNODE_PAY_PLUGIN_DIR . 'includes/class-griffnode-settings.php';
require_once GRIFFNODE_PAY_PLUGIN_DIR . 'includes/class-griffnode-api.php';
require_once GRIFFNODE_PAY_PLUGIN_DIR . 'includes/class-griffnode-shortcode.php';
require_once GRIFFNODE_PAY_PLUGIN_DIR . 'includes/class-griffnode-webhook.php';

add_action( 'plugins_loaded', function () {
    GriffNode_Pay_Settings::init();
    GriffNode_Pay_Shortcode::init();
    GriffNode_Pay_Webhook::init();
} );

// API checks failing (wrong key, network): the site owner must know, events are not arriving.
add_action( 'admin_notices', function () {
    $err = get_option( 'griffnode_pay_api_error' );
    if ( ! $err || time() - $err['time'] > DAY_IN_SECONDS || ! current_user_can( 'manage_options' ) ) return;
    printf(
        '<div class="notice notice-error"><p><strong>GriffNode:</strong> %s</p></div>',
        esc_html( sprintf(
            /* translators: %s: error message from the GriffNode API. */
            __( 'Payment status checks are failing, so payment events are not arriving: %s. Check the Secret Key under Settings → GriffNode.', 'griffnode-crypto-payments' ),
            $err['message']
        ) )
    );
} );

add_filter( 'cron_schedules', function ( $schedules ) {
    $schedules['griffnode_pay_minute'] = [ 'interval' => 60, 'display' => 'Every minute (GriffNode)' ];
    return $schedules;
} );

register_deactivation_hook( __FILE__, function () {
    wp_clear_scheduled_hook( 'griffnode_pay_poll_kick' );
    wp_clear_scheduled_hook( 'griffnode_sync_pending' ); // 1.0.1 builds of 1.0.2
} );
