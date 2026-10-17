<?php
defined( 'ABSPATH' ) || exit;

/**
 * Thin wrapper over the GriffNode REST API using the WordPress HTTP layer.
 */
class GriffNode_Pay_API {

    /**
     * Create a hosted-checkout transaction.
     *
     * @return array{ok:bool, payment_url?:string, txid?:string, error?:string}
     */
    public static function create_transaction( array $payload ): array {
        $secret = GriffNode_Pay_Settings::get( 'secret_key' );
        if ( ! $secret ) {
            return [ 'ok' => false, 'error' => 'Secret key not configured.' ];
        }

        $response = wp_remote_post( GRIFFNODE_PAY_API_BASE . '/transactions/create', [
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
     * Fetch up to 100 transactions in one request (public shape: transaction_id, status,
     * exception, metadata, ...). Records the failure for the admin notice on error.
     */
    public static function get_transactions( array $ids ): array {
        if ( ! $ids ) return [];
        $response = wp_remote_get( GRIFFNODE_PAY_API_BASE . '/transactions/list?ids=' . rawurlencode( implode( ',', $ids ) ), [
            'headers' => [
                'Authorization' => 'Bearer ' . GriffNode_Pay_Settings::get( 'secret_key' ),
                'Accept'        => 'application/json',
            ],
            'timeout' => 15,
        ] );
        $code = is_wp_error( $response ) ? 0 : wp_remote_retrieve_response_code( $response );
        $body = $code ? json_decode( wp_remote_retrieve_body( $response ), true ) : null;
        if ( 200 !== $code || ! isset( $body['data']['transactions'] ) ) {
            $msg = is_wp_error( $response ) ? $response->get_error_message() : ( $body['message'] ?? $body['error'] ?? 'HTTP ' . $code );
            update_option( 'griffnode_pay_api_error', [ 'time' => time(), 'message' => (string) $msg ], false );
            return [];
        }
        delete_option( 'griffnode_pay_api_error' );
        return $body['data']['transactions'];
    }
}
