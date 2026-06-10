<?php
defined( 'ABSPATH' ) || exit;

/**
 * [cryptogate_button] shortcode — renders a crypto-selector + pay button,
 * and the admin-ajax endpoint that creates the transaction server-side.
 */
class CryptoGate_Shortcode {

    public static function init() {
        add_shortcode( 'cryptogate_button', [ __CLASS__, 'render' ] );
        add_action( 'wp_ajax_cryptogate_create_payment', [ __CLASS__, 'ajax_create' ] );
        add_action( 'wp_ajax_nopriv_cryptogate_create_payment', [ __CLASS__, 'ajax_create' ] );
    }

    /**
     * Render the button. Amount/currency/etc. are signed into a token so they
     * can't be tampered with in the browser — the server reads them back from
     * the signed token, not from the raw POST.
     */
    public static function render( $atts ): string {
        $atts = shortcode_atts( [
            'amount'      => '',
            'currency'    => CryptoGate_Settings::get( 'default_currency', 'USD' ),
            'crypto'      => '',      // lock to a single coin; empty = let customer pick
            'button_text' => 'Pay with Crypto',
            'reference'   => '',      // merchant order id, echoed back in webhooks
            'email'       => '',      // pre-fill the payment page
            'success_url' => '',
            'cancel_url'  => '',
        ], $atts, 'cryptogate_button' );

        if ( ! CryptoGate_Settings::get( 'secret_key' ) ) {
            return current_user_can( 'manage_options' )
                ? '<p><strong>CryptoGate:</strong> configure your API keys under Settings → CryptoGate.</p>'
                : '';
        }
        if ( ! is_numeric( $atts['amount'] ) || (float) $atts['amount'] <= 0 ) {
            return current_user_can( 'manage_options' )
                ? '<p><strong>CryptoGate:</strong> the shortcode needs a positive <code>amount</code>.</p>'
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
        $token = CryptoGate_Settings::sign( $config );
        $pk    = CryptoGate_Settings::get( 'publishable_key' );
        $lock  = strtoupper( $atts['crypto'] );
        $uid   = 'cg-' . substr( md5( $token ), 0, 8 );

        ob_start();
        ?>
        <div class="cryptogate-button-wrap" id="<?php echo esc_attr( $uid ); ?>">
            <?php if ( ! $lock ) : ?>
                <select class="cg-crypto" style="margin-bottom:8px;display:block">
                    <option value=""><?php esc_html_e( 'Loading…', 'cryptogate-payments' ); ?></option>
                </select>
            <?php endif; ?>
            <button type="button" class="cg-pay button"><?php echo esc_html( $atts['button_text'] ); ?></button>
            <span class="cg-msg" style="margin-left:8px"></span>
        </div>
        <script>
        (function () {
            var wrap   = document.getElementById('<?php echo esc_js( $uid ); ?>');
            if (!wrap || wrap.dataset.cgInit) { return; }
            wrap.dataset.cgInit = '1';

            var ajax   = <?php echo wp_json_encode( admin_url( 'admin-ajax.php' ) ); ?>;
            var nonce  = <?php echo wp_json_encode( wp_create_nonce( 'cryptogate_pay' ) ); ?>;
            var token  = <?php echo wp_json_encode( $token ); ?>;
            var lock   = <?php echo wp_json_encode( $lock ); ?>;
            var pk     = <?php echo wp_json_encode( $pk ); ?>;
            var apiBase= <?php echo wp_json_encode( CRYPTOGATE_API_BASE ); ?>;
            var sel    = wrap.querySelector('.cg-crypto');
            var btn    = wrap.querySelector('.cg-pay');
            var msg    = wrap.querySelector('.cg-msg');

            if (sel && pk) {
                fetch(apiBase + '/merchant/cryptos', { headers: { 'Authorization': 'Bearer ' + pk } })
                    .then(function (r) { return r.json(); })
                    .then(function (d) {
                        var list = (d && d.data && d.data.cryptocurrencies) || [];
                        sel.innerHTML = list.length
                            ? list.map(function (c) { return '<option value="' + c.symbol + '">' + c.name + ' (' + c.symbol + ')</option>'; }).join('')
                            : '<option value="">No cryptos configured</option>';
                    })
                    .catch(function () { sel.innerHTML = '<option value="">Failed to load</option>'; });
            } else if (sel) {
                sel.innerHTML = '<option value="">No publishable key</option>';
            }

            btn.addEventListener('click', function () {
                var crypto = lock || (sel ? sel.value : '');
                if (!crypto) { msg.textContent = 'Please select a cryptocurrency.'; return; }
                btn.disabled = true;
                msg.textContent = 'Creating payment…';

                var body = new URLSearchParams();
                body.set('action', 'cryptogate_create_payment');
                body.set('nonce', nonce);
                body.set('token', token);
                body.set('crypto', crypto);

                fetch(ajax, { method: 'POST', body: body })
                    .then(function (r) { return r.json(); })
                    .then(function (res) {
                        if (res && res.success && res.data && res.data.payment_url) {
                            window.location.href = res.data.payment_url;
                        } else {
                            btn.disabled = false;
                            msg.textContent = (res && res.data && res.data.error) || 'Payment error. Try again.';
                        }
                    })
                    .catch(function () { btn.disabled = false; msg.textContent = 'Network error. Try again.'; });
            });
        })();
        </script>
        <?php
        return ob_get_clean();
    }

    public static function ajax_create() {
        if ( ! check_ajax_referer( 'cryptogate_pay', 'nonce', false ) ) {
            wp_send_json_error( [ 'error' => 'Security check failed. Refresh and try again.' ], 400 );
        }

        $config = CryptoGate_Settings::unsign( sanitize_text_field( wp_unslash( $_POST['token'] ?? '' ) ) );
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
            'currency' => $config['currency'] ?? 'USD',
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

        $result = CryptoGate_API::create_transaction( $payload );
        if ( ! $result['ok'] ) {
            wp_send_json_error( [ 'error' => $result['error'] ], 502 );
        }

        wp_send_json_success( [ 'payment_url' => $result['payment_url'], 'txid' => $result['txid'] ] );
    }
}
