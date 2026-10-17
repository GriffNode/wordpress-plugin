<?php
defined( 'ABSPATH' ) || exit;

/**
 * Thin wrapper over the GriffNode REST API using the WordPress HTTP layer.
 */
class GriffNode_API {

    /**
     * Create a hosted-checkout transaction.
     *
     * @return array{ok:bool, payment_url?:string, txid?:string, error?:string}
     */
    public static function create_transaction( array $payload ): array {
        $secret = GriffNode_Settings::get( 'secret_key' );
        if ( ! $secret ) {
            return [ 'ok' => false, 'error' => 'Secret key not configured.' ];
        }

        $response = wp_remote_post( GRIFFNODE_API_BASE . '/transactions/create', [
            'headers' => [
                'Authorization' => 'Bearer ' . $secret,
                'Content-Type'  => 'application/json',
                'Accept'        => 'application/json',
            ],
            'body'    => wp_json_encode( $payload ),
            'timeout' => 30,
        ] );

        if ( is_wp_error( $response ) ) {
            return [ 'ok' => false, 'error' => 'Could not reach GriffNode.' ];
        }

        $body = json_decode( wp_remote_retrieve_body( $response ), true );
        $code = wp_remote_retrieve_response_code( $response );

        if ( $code !== 201 || empty( $body['data']['payment_url'] ) ) {
            return [ 'ok' => false, 'error' => $body['message'] ?? $body['error'] ?? 'Unknown error.' ];
        }

        return [
            'ok'          => true,
            'payment_url' => $body['data']['payment_url'],
            // The create response field is transaction_id (the legacy txid key was retired).
            'txid'        => $body['data']['transaction_id'] ?? '',
        ];
    }

    /**
     * Fetch one transaction (public shape: transaction_id, status, metadata, ...).
     */
    public static function get_transaction( string $txid ): ?array {
        $response = wp_remote_get( GRIFFNODE_API_BASE . '/transactions/' . rawurlencode( $txid ), [
            'headers' => [
                'Authorization' => 'Bearer ' . GriffNode_Settings::get( 'secret_key' ),
                'Accept'        => 'application/json',
            ],
            'timeout' => 15,
        ] );
        if ( is_wp_error( $response ) || 200 !== wp_remote_retrieve_response_code( $response ) ) {
            return null;
        }
        $body = json_decode( wp_remote_retrieve_body( $response ), true );
        $tx   = $body['data'] ?? null;
        return ( is_array( $tx ) && ( $tx['transaction_id'] ?? '' ) === $txid ) ? $tx : null;
    }
}
