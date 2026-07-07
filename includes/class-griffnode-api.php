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
            'txid'        => $body['data']['txid'] ?? '',
        ];
    }
}
