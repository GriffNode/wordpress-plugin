<?php
defined( 'ABSPATH' ) || exit;

/**
 * Payment events for themes/plugins to fulfil orders:
 *
 *   do_action( 'griffnode_payment_completed', $event );  // also _overpaid, _partial,
 *   do_action( 'griffnode_payment_expired',   $event );  // _failed, _paid_late, _needs_review
 *   do_action( 'griffnode_webhook',           $event );  // fires for every event
 *
 * Two sources, same actions, each fired ONCE per payment and event:
 *  - Webhook (Business plan and up): POST /wp-json/griffnode/v1/webhook, HMAC-verified.
 *  - Status check (every plan, the only source on Starter): while any payment made through
 *    this site is open, the site checks all of them every 30 seconds in ONE request
 *    (GET /transactions/list?ids=...). It does not depend on visitors: each run calls the
 *    site itself (a loopback request), waits 30s and starts the next. With nothing open it
 *    stops. If the loop dies, the next payment, visit or cron run restarts it within ~90s.
 *    These events carry 'source' => 'api_check'.
 *
 * A payment stays open until it is completed, expired or failed, for at most 7 days, so
 * slow confirmations, partial payments topped up later and late payments the merchant
 * confirms in the GriffNode dashboard all still arrive.
 */
class GriffNode_Pay_Webhook {

    const PENDING  = 'griffnode_pending';        // option: txid => created timestamp
    const INTERVAL = 30;
    const LOCK     = 'griffnode_pay_poll_lock';
    const BEAT     = 'griffnode_pay_poll_beat';
    const KEY      = 'griffnode_pay_poll_key';

    public static function init() {
        add_action( 'init', [ __CLASS__, 'kick' ] );
        add_action( 'griffnode_pay_poll_kick', [ __CLASS__, 'kick' ] );
        add_action( 'template_redirect', [ __CLASS__, 'check_return' ] );
        add_action( 'rest_api_init', function () {
            register_rest_route( 'griffnode/v1', '/webhook', [
                'methods'             => 'POST',
                'callback'            => [ __CLASS__, 'handle' ],
                'permission_callback' => '__return_true', // verified by HMAC below, not by WP auth
            ] );
            register_rest_route( 'griffnode/v1', '/poll', [
                'methods'             => 'GET',
                'callback'            => [ __CLASS__, 'handle_loop' ],
                'permission_callback' => '__return_true', // verified by the secret k below
            ] );
        } );
        if ( ! wp_next_scheduled( 'griffnode_pay_poll_kick' ) ) {
            wp_schedule_event( time() + 60, 'griffnode_pay_minute', 'griffnode_pay_poll_kick' );
        }
    }

    public static function handle( WP_REST_Request $request ) {
        $secret = GriffNode_Pay_Settings::get( 'webhook_secret' );
        if ( ! $secret ) {
            return new WP_REST_Response( [ 'error' => 'Webhook secret not configured.' ], 400 );
        }

        $raw       = $request->get_body();
        $sig       = $request->get_header( 'x_griffnode_signature' ) ?? '';
        $received  = 0 === strpos( $sig, 'sha256=' ) ? substr( $sig, 7 ) : $sig;
        $expected  = hash_hmac( 'sha256', $raw, $secret );

        if ( ! $received || ! hash_equals( $expected, $received ) ) {
            return new WP_REST_Response( [ 'error' => 'Invalid signature.' ], 401 );
        }

        $event = json_decode( $raw, true );
        if ( ! is_array( $event ) || empty( $event['event'] ) ) {
            return new WP_REST_Response( [ 'error' => 'Invalid payload.' ], 400 );
        }

        // Fire each event once. Retries carry the same id; a replayed old delivery is
        // refused by age (retries stop after 24h, so 48h is generous).
        $ts = isset( $event['timestamp'] ) ? strtotime( (string) $event['timestamp'] ) : false;
        if ( $ts && $ts < time() - 2 * DAY_IN_SECONDS ) {
            return new WP_REST_Response( [ 'error' => 'Event too old.' ], 400 );
        }
        if ( ! empty( $event['id'] ) ) {
            $seen = 'griffnode_evt_' . md5( (string) $event['id'] );
            if ( get_transient( $seen ) ) {
                return new WP_REST_Response( [ 'ok' => true, 'duplicate' => true ], 200 );
            }
            set_transient( $seen, 1, 2 * DAY_IN_SECONDS );
        }

        self::dispatch( $event );

        return new WP_REST_Response( [ 'ok' => true ], 200 );
    }

    /**
     * Fire an event once per payment. Completed and overpaid count as the same outcome,
     * so a webhook and a status check never both announce a paid order.
     */
    private static function dispatch( array $event ): void {
        $txid = (string) ( $event['transaction_id'] ?? '' );
        if ( $txid ) {
            $kind = in_array( $event['event'], [ 'payment.completed', 'payment.overpaid' ], true ) ? 'paid' : $event['event'];
            $once = 'griffnode_pay_fired_' . md5( $txid . '|' . $kind );
            if ( get_transient( $once ) ) return;
            set_transient( $once, 1, 8 * DAY_IN_SECONDS );
            if ( in_array( $kind, [ 'paid', 'payment.expired', 'payment.failed' ], true ) ) {
                self::untrack( $txid );
            }
        }

        // Generic hook for every event.
        do_action( 'griffnode_webhook', $event );

        // Specific hook, e.g. payment.completed -> griffnode_payment_completed.
        $name = str_replace( '.', '_', sanitize_key( $event['event'] ) );
        do_action( 'griffnode_' . $name, $event );
    }

    // ── Status check ──────────────────────────────────────────────────────────

    /** A payment was created through this site: follow it and start the loop. */
    public static function track( string $txid ): void {
        if ( ! $txid ) return;
        // ponytail: read-modify-write on one option; a lost write under heavy concurrent
        // checkout only delays that payment until its webhook or the next payment. Fine at
        // the volume a WordPress button sees.
        $pending          = get_option( self::PENDING, [] );
        $pending[ $txid ] = time();
        update_option( self::PENDING, array_slice( $pending, -300, null, true ), true );
        update_option( self::BEAT, 0, true );
        self::kick();
    }

    /** Customer came back via success_url (?transaction_id=): check that payment now. */
    public static function check_return(): void {
        $txid = isset( $_GET['transaction_id'] ) ? sanitize_text_field( wp_unslash( $_GET['transaction_id'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        if ( $txid && isset( get_option( self::PENDING, [] )[ $txid ] ) ) {
            self::poll( [ $txid ] );
            self::kick();
        }
    }

    /** Start the loop if payments are open and no run happened recently. */
    public static function kick(): void {
        if ( ! get_option( self::PENDING ) || get_transient( self::LOCK ) ) return;
        if ( time() - (int) get_option( self::BEAT, 0 ) < 3 * self::INTERVAL ) return;
        // A start is already on its way (loopback not running yet): don't start a second loop.
        if ( get_transient( 'griffnode_pay_poll_spawned' ) ) return;
        set_transient( 'griffnode_pay_poll_spawned', 1, 15 );
        self::spawn();
    }

    /** Loopback entry point: GET /wp-json/griffnode/v1/poll?k=<key>. */
    public static function handle_loop( WP_REST_Request $request ) {
        if ( ! hash_equals( self::key(), (string) $request->get_param( 'k' ) ) ) {
            return new WP_REST_Response( [ 'error' => 'Forbidden.' ], 403 );
        }
        ignore_user_abort( true );
        if ( get_transient( self::LOCK ) ) {
            return new WP_REST_Response( [ 'ok' => true, 'running' => true ], 200 );
        }
        set_transient( self::LOCK, 1, 2 * self::INTERVAL );
        update_option( self::BEAT, time(), true );

        if ( self::poll() ) {
            // ponytail: one PHP worker sleeps between checks while payments are open. sleep()
            // does not count toward max_execution_time (except on Windows); a host with a hard
            // request timeout under ~40s breaks the chain and kick() restarts it.
            sleep( self::INTERVAL );
            delete_transient( self::LOCK );
            self::spawn();
        } else {
            delete_transient( self::LOCK );
        }
        return new WP_REST_Response( [ 'ok' => true ], 200 );
    }

    /**
     * Check open payments (or just $ids) in bulk and fire what changed.
     * Returns how many payments are still open.
     */
    public static function poll( ?array $ids = null ): int {
        $pending = get_option( self::PENDING, [] );
        $cutoff  = time() - 7 * DAY_IN_SECONDS;
        foreach ( $pending as $txid => $ts ) {
            if ( $ts < $cutoff ) unset( $pending[ $txid ] );
        }
        update_option( self::PENDING, $pending, true );
        $ids = $ids ?? array_map( 'strval', array_keys( $pending ) );

        foreach ( array_chunk( $ids, 100 ) as $chunk ) {
            foreach ( GriffNode_Pay_API::get_transactions( $chunk ) as $tx ) {
                $event = self::event_for( $tx );
                if ( $event ) self::dispatch( $event );
            }
        }
        return count( get_option( self::PENDING, [] ) );
    }

    /** Map a transaction (public API shape) to the webhook event it stands for, if any. */
    private static function event_for( array $tx ): ?array {
        $status    = $tx['status'] ?? '';
        $exception = $tx['exception'] ?? '';
        if ( in_array( $exception, [ 'paid_late', 'needs_review' ], true ) ) {
            $name = $exception;
        } elseif ( in_array( $status, [ 'completed', 'overpaid', 'partial', 'expired', 'failed' ], true ) ) {
            $name = $status;
        } else {
            return null; // still pending
        }
        return array_merge( $tx, [
            'event'           => 'payment.' . $name,
            'currency_crypto' => $tx['crypto'] ?? '',
            'source'          => 'api_check',
        ] );
    }

    private static function untrack( string $txid ): void {
        $pending = get_option( self::PENDING, [] );
        if ( isset( $pending[ $txid ] ) ) {
            unset( $pending[ $txid ] );
            update_option( self::PENDING, $pending, true );
        }
    }

    private static function spawn(): void {
        wp_remote_get( add_query_arg( 'k', self::key(), rest_url( 'griffnode/v1/poll' ) ), [
            'blocking'  => false,
            'timeout'   => 0.01,
            'sslverify' => apply_filters( 'https_local_ssl_verify', false ), // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- core filter
        ] );
    }

    private static function key(): string {
        $key = get_option( self::KEY );
        if ( ! $key ) {
            $key = wp_generate_password( 32, false );
            update_option( self::KEY, $key, true );
        }
        return $key;
    }
}
