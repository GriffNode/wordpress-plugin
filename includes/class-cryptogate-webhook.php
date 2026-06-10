<?php
defined( 'ABSPATH' ) || exit;

/**
 * Webhook receiver. Registers a public REST route, verifies the HMAC-SHA256
 * signature, then fires WordPress actions so themes/plugins can fulfil orders:
 *
 *   do_action( 'cryptogate_payment_completed', $event );
 *   do_action( 'cryptogate_payment_partial',   $event );
 *   do_action( 'cryptogate_payment_expired',   $event );
 *   do_action( 'cryptogate_webhook',           $event );  // fires for every event
 *
 * Endpoint: /wp-json/cryptogate/v1/webhook
 */
class CryptoGate_Webhook {

    public static function init() {
        add_action( 'rest_api_init', function () {
            register_rest_route( 'cryptogate/v1', '/webhook', [
                'methods'             => 'POST',
                'callback'            => [ __CLASS__, 'handle' ],
                'permission_callback' => '__return_true', // verified by HMAC below, not by WP auth
            ] );
        } );
    }

    public static function handle( WP_REST_Request $request ) {
        $secret = CryptoGate_Settings::get( 'webhook_secret' );
        if ( ! $secret ) {
            return new WP_REST_Response( [ 'error' => 'Webhook secret not configured.' ], 400 );
        }

        $raw       = $request->get_body();
        $sig       = $request->get_header( 'x_cryptogate_signature' ) ?? '';
        $received  = str_starts_with( $sig, 'sha256=' ) ? substr( $sig, 7 ) : $sig;
        $expected  = hash_hmac( 'sha256', $raw, $secret );

        if ( ! $received || ! hash_equals( $expected, $received ) ) {
            return new WP_REST_Response( [ 'error' => 'Invalid signature.' ], 401 );
        }

        $event = json_decode( $raw, true );
        if ( ! is_array( $event ) || empty( $event['event'] ) ) {
            return new WP_REST_Response( [ 'error' => 'Invalid payload.' ], 400 );
        }

        // Generic hook for every verified event.
        do_action( 'cryptogate_webhook', $event );

        // Specific hook, e.g. payment.completed -> cryptogate_payment_completed.
        $name = str_replace( '.', '_', sanitize_key( $event['event'] ) );
        do_action( 'cryptogate_' . $name, $event );

        return new WP_REST_Response( [ 'ok' => true ], 200 );
    }
}
