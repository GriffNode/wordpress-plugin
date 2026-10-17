<?php
defined( 'ABSPATH' ) || exit;

/**
 * [griffnode_button] shortcode — renders a crypto-selector + pay button,
 * and the admin-ajax endpoint that creates the transaction server-side.
 */
class GriffNode_Pay_Shortcode {

    public static function init() {
        add_shortcode( 'griffnode_button', [ __CLASS__, 'render' ] );
        add_action( 'wp_ajax_griffnode_create_payment', [ __CLASS__, 'ajax_create' ] );
        add_action( 'wp_ajax_nopriv_griffnode_create_payment', [ __CLASS__, 'ajax_create' ] );
    }

    /**
     * Render the button. Amount/currency/etc. are signed into a token so they
     * can't be tampered with in the browser — the server reads them back from
     * the signed token, not from the raw POST.
     */
    public static function render( $atts ): string {
        $atts = shortcode_atts( [
            'amount'      => '',
            'currency'    => GriffNode_Pay_Settings::get( 'default_currency', 'USD' ),
            'crypto'      => '',      // lock to a single coin; empty = let customer pick
            'button_text' => 'Pay with Crypto',
            'reference'   => '',      // merchant order id, echoed back in webhooks
            'email'       => '',      // pre-fill the payment page
            'success_url' => '',
            'cancel_url'  => '',
        ], $atts, 'griffnode_button' );

        if ( ! GriffNode_Pay_Settings::get( 'secret_key' ) ) {
            return current_user_can( 'manage_options' )
                ? '<p><strong>GriffNode:</strong> configure your API keys under Settings → GriffNode.</p>'
                : '';
        }
        if ( ! is_numeric( $atts['amount'] ) || (float) $atts['amount'] <= 0 ) {
            return current_user_can( 'manage_options' )
                ? '<p><strong>GriffNode:</strong> the shortcode needs a positive <code>amount</code>.</p>'
                : '';
        }

        $currency = strtoupper( $atts['currency'] );
        if ( ! in_array( $currency, [ 'USD', 'PLN', 'EUR', 'GBP' ], true ) ) {
            $currency = 'USD';
        }

        $config = [
            'amount'      => (float) $atts['amount'],
            'currency'    => $currency,
            'reference'   => (string) $atts['reference'],
            'email'       => (string) $atts['email'],
            'success_url' => (string) $atts['success_url'],
            'cancel_url'  => (string) $atts['cancel_url'],
        ];
        $token = GriffNode_Pay_Settings::sign( $config );
        $lock  = strtoupper( $atts['crypto'] );
        self::enqueue();

        ob_start();
        ?>
        <div class="griffnode-button-wrap" data-token="<?php echo esc_attr( $token ); ?>" data-lock="<?php echo esc_attr( $lock ); ?>">
            <?php if ( ! $lock ) : ?>
                <select class="cg-crypto" style="margin-bottom:8px;display:block">
                    <option value=""><?php esc_html_e( 'Loading…', 'griffnode-crypto-payments' ); ?></option>
                </select>
            <?php endif; ?>
            <button type="button" class="cg-pay button"><?php echo esc_html( $atts['button_text'] ); ?></button>
            <span class="cg-msg" style="margin-left:8px"></span>
        </div>
        <?php
        return ob_get_clean();
    }

    /**
     * Enqueue the button script once per page, only on pages that render a button.
     */
    private static function enqueue() {
        if ( wp_script_is( 'griffnode-button', 'enqueued' ) ) {
            return;
        }
        wp_enqueue_script( 'griffnode-button', GRIFFNODE_PAY_PLUGIN_URL . 'js/button.js', [], GRIFFNODE_PAY_VERSION, true );
        wp_localize_script( 'griffnode-button', 'griffnodeButton', [
            'ajax'    => admin_url( 'admin-ajax.php' ),
            'nonce'   => wp_create_nonce( 'griffnode_pay' ),
            'pk'      => GriffNode_Pay_Settings::get( 'publishable_key' ),
            'apiBase' => GRIFFNODE_PAY_API_BASE,
            'i18n'    => [
                'noCryptos'    => __( 'No cryptos configured', 'griffnode-crypto-payments' ),
                'loadFailed'   => __( 'Failed to load', 'griffnode-crypto-payments' ),
                'noKey'        => __( 'No publishable key', 'griffnode-crypto-payments' ),
                'selectCrypto' => __( 'Please select a cryptocurrency.', 'griffnode-crypto-payments' ),
                'creating'     => __( 'Creating payment…', 'griffnode-crypto-payments' ),
                'payError'     => __( 'Payment error. Try again.', 'griffnode-crypto-payments' ),
                'netError'     => __( 'Network error. Try again.', 'griffnode-crypto-payments' ),
            ],
        ] );
    }

    public static function ajax_create() {
        if ( ! check_ajax_referer( 'griffnode_pay', 'nonce', false ) ) {
            wp_send_json_error( [ 'error' => 'Security check failed. Refresh and try again.' ], 400 );
        }

        $config = GriffNode_Pay_Settings::unsign( sanitize_text_field( wp_unslash( $_POST['token'] ?? '' ) ) );
        if ( ! $config || empty( $config['amount'] ) ) {
            wp_send_json_error( [ 'error' => 'Invalid payment request.' ], 400 );
        }

        $crypto = strtoupper( sanitize_text_field( wp_unslash( $_POST['crypto'] ?? '' ) ) );
        if ( ! $crypto ) {
            wp_send_json_error( [ 'error' => 'Please select a cryptocurrency.' ], 400 );
        }

        $payload = [
            'crypto'   => $crypto,
            'amount'   => (float) $config['amount'],
            // The API field is currency_fiat; "currency" is ignored and silently bills USD.
            'currency_fiat' => $config['currency'] ?? 'USD',
            'metadata' => [
                'source'    => 'wordpress',
                'site'      => home_url(),
                'reference' => (string) ( $config['reference'] ?? '' ),
            ],
        ];
        if ( ! empty( $config['reference'] ) )   { $payload['order_id'] = (string) $config['reference']; }
        if ( ! empty( $config['email'] ) )       { $payload['customer_email'] = sanitize_email( $config['email'] ); }
        if ( ! empty( $config['success_url'] ) ) { $payload['success_url'] = esc_url_raw( $config['success_url'] ); }
        if ( ! empty( $config['cancel_url'] ) )  { $payload['cancel_url'] = esc_url_raw( $config['cancel_url'] ); }

        $result = GriffNode_Pay_API::create_transaction( $payload );
        if ( ! $result['ok'] ) {
            wp_send_json_error( [ 'error' => $result['error'] ], 502 );
        }
        GriffNode_Pay_Webhook::track( $result['txid'] );

        wp_send_json_success( [ 'payment_url' => $result['payment_url'], 'txid' => $result['txid'] ] );
    }
}
