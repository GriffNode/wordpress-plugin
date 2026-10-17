<?php
defined( 'ABSPATH' ) || exit;

/**
 * Webhook receiver. Registers a public REST route, verifies the HMAC-SHA256
 * signature, then fires WordPress actions so themes/plugins can fulfil orders:
 *
 *   do_action( 'griffnode_payment_completed', $event );
 *   do_action( 'griffnode_payment_partial',   $event );
 *   do_action( 'griffnode_payment_expired',   $event );
 *   do_action( 'griffnode_webhook',           $event );  // fires for every event
 *
 * Endpoint: /wp-json/griffnode/v1/webhook
 *
 * Without a webhook secret (free Starter plan) the same actions fire from an API status
 * check instead: when the customer returns with ?transaction_id= and every 5 minutes for
 * payments created in the last 2 hours. Those events carry 'source' => 'api_check'.
 */
class GriffNode_Webhook {

    const PENDING = 'griffnode_pending'; // option: txid => created timestamp

    public static function init() {
        add_action( 'griffnode_sync_pending', [ __CLASS__, 'sync_pending' ] );
        add_action( 'template_redirect', [ __CLASS__, 'check_return' ] );
        add_action( 'rest_api_init', function () {
            register_rest_route( 'griffnode/v1', '/webhook', [
                'methods'             => 'POST',
                'callback'            => [ __CLASS__, 'handle' ],
                'permission_callback' => '__return_true', // verified by HMAC below, not by WP auth
            ] );
        } );
    }

    public static function handle( WP_REST_Request $request ) {
        $secret = GriffNode_Settings::get( 'webhook_secret' );
        if ( ! $secret ) {
            return new WP_REST_Response( [ 'error' => 'Webhook secret not configured.' ], 400 );
        }

        $raw       = $request->get_body();
        $sig       = $request->get_header( 'x_griffnode_signature' ) ?? '';
        $received  = str_starts_with( $sig, 'sha256=' ) ? substr( $sig, 7 ) : $sig;
        $expected  = hash_hmac( 'sha256', $raw, $secret );

        if ( ! $received || ! hash_equals( $expected, $received ) ) {
            return new WP_REST_Response( [ 'error' => 'Invalid signature.' ], 401 );
        }

        $event = json_decode( $raw, true );
        if ( ! is_array( $event ) || empty( $event['event'] ) ) {
            return new WP_REST_Response( [ 'error' => 'Invalid payload.' ], 400 );
        }

        self::dispatch( $event );

        return new WP_REST_Response( [ 'ok' => true ], 200 );
    }

    private static function dispatch( array $event ): void {
        // Generic hook for every verified event.
        do_action( 'griffnode_webhook', $event );

        // Specific hook, e.g. payment.completed -> griffnode_payment_completed.
        $name = str_replace( '.', '_', sanitize_key( $event['event'] ) );
        do_action( 'griffnode_' . $name, $event );
    }

    // ── API status check (no webhook, e.g. Starter plan) ──────────────────────

    /** Remember a new payment so the status check can follow it. No-op with a webhook. */
    public static function track( string $txid ): void {
        if ( ! $txid || GriffNode_Settings::get( 'webhook_secret' ) ) return;
        // ponytail: read-modify-write on one option; a lost write under heavy concurrent
        // checkout only delays that payment's check. Fine at Starter volume.
        $pending          = get_option( self::PENDING, [] );
        $pending[ $txid ] = time();
        update_option( self::PENDING, array_slice( $pending, -200, null, true ), false );
    }

    /** Customer came back via success_url (?transaction_id=): check that one payment now. */
    public static function check_return(): void {
        $txid = isset( $_GET['transaction_id'] ) ? sanitize_text_field( wp_unslash( $_GET['transaction_id'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        if ( $txid && isset( get_option( self::PENDING, [] )[ $txid ] ) ) {
            self::check( $txid );
        }
    }

    /** Cron: up to 20 tracked payments per run, dropping any older than 2 hours. */
    public static function sync_pending(): void {
        $pending = get_option( self::PENDING, [] );
        $cutoff  = time() - 2 * HOUR_IN_SECONDS;
        foreach ( $pending as $txid => $ts ) {
            if ( $ts < $cutoff ) unset( $pending[ $txid ] );
        }
        update_option( self::PENDING, $pending, false );
        foreach ( array_slice( array_keys( $pending ), 0, 20 ) as $txid ) {
            self::check( (string) $txid );
        }
    }

    /** Fire the webhook actions once the payment reaches a final status, then forget it. */
    private static function check( string $txid ): void {
        $tx     = GriffNode_API::get_transaction( $txid );
        $status = $tx['status'] ?? '';
        if ( ! in_array( $status, [ 'completed', 'overpaid', 'partial', 'expired', 'failed' ], true ) ) return;

        $pending = get_option( self::PENDING, [] );
        if ( ! isset( $pending[ $txid ] ) ) return; // already handled
        unset( $pending[ $txid ] );
        update_option( self::PENDING, $pending, false );

        self::dispatch( array_merge( $tx, [
            'event'           => 'payment.' . $status,
            'currency_crypto' => $tx['crypto'] ?? '',
            'source'          => 'api_check',
        ] ) );
    }
}
