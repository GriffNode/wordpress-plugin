<?php
defined( 'ABSPATH' ) || exit;

/**
 * Admin settings page + option accessors.
 *
 * All settings live in a single option array `griffnode_settings`:
 *   publishable_key, secret_key, webhook_secret, default_currency
 */
class GriffNode_Settings {

    const OPTION = 'griffnode_settings';

    public static function init() {
        add_action( 'admin_menu', [ __CLASS__, 'add_menu' ] );
        add_action( 'admin_init', [ __CLASS__, 'register' ] );
    }

    // ── Option accessors ────────────────────────────────────────────────────

    public static function get( string $key, $default = '' ) {
        $opts = get_option( self::OPTION, [] );
        return isset( $opts[ $key ] ) && $opts[ $key ] !== '' ? $opts[ $key ] : $default;
    }

    /**
     * Sign a payload with the secret key so button parameters (amount, currency)
     * cannot be tampered with in the browser before they come back to the server.
     */
    public static function sign( array $data ): string {
        $json = wp_json_encode( $data );
        $b64  = rtrim( strtr( base64_encode( $json ), '+/', '-_' ), '=' );
        $sig  = hash_hmac( 'sha256', $b64, self::get( 'secret_key' ) );
        return $b64 . '.' . $sig;
    }

    /**
     * Verify a token produced by sign() and return the decoded payload, or null.
     */
    public static function unsign( string $token ): ?array {
        $parts = explode( '.', $token, 2 );
        if ( count( $parts ) !== 2 ) {
            return null;
        }
        [ $b64, $sig ] = $parts;
        $expected = hash_hmac( 'sha256', $b64, self::get( 'secret_key' ) );
        if ( ! hash_equals( $expected, $sig ) ) {
            return null;
        }
        $json = base64_decode( strtr( $b64, '-_', '+/' ) );
        $data = json_decode( $json, true );
        return is_array( $data ) ? $data : null;
    }

    // ── Settings page ─────────────────────────────────────────────────────────

    public static function add_menu() {
        add_options_page(
            'GriffNode',
            'GriffNode',
            'manage_options',
            'griffnode-payments',
            [ __CLASS__, 'render_page' ]
        );
    }

    public static function register() {
        register_setting( 'griffnode_settings_group', self::OPTION, [ __CLASS__, 'sanitize' ] );

        add_settings_section( 'griffnode_main', 'API credentials', function () {
            echo '<p>' . esc_html__( 'Find these in your GriffNode dashboard under API Integration and Webhooks.', 'griffnode-payments' ) . '</p>';
        }, 'griffnode-payments' );

        $fields = [
            'publishable_key'  => [ 'Publishable Key (pk_live_ / pk_test_)', 'text', 'Used to fetch your supported cryptos on the page. Safe to expose in the browser.' ],
            'secret_key'       => [ 'Secret Key (sk_live_ / sk_test_)', 'password', 'Used server-side to create transactions. Never exposed to the browser.' ],
            'webhook_secret'   => [ 'Webhook Secret', 'password', 'From your dashboard Webhooks page. Verifies incoming payment events.' ],
            'default_currency' => [ 'Default Currency', 'text', 'Fiat currency for prices when the shortcode does not set one (USD, PLN, EUR, GBP).' ],
        ];

        foreach ( $fields as $key => [$label, $type, $help] ) {
            add_settings_field( $key, esc_html( $label ), function () use ( $key, $type, $help ) {
                $val = self::get( $key, $key === 'default_currency' ? 'USD' : '' );
                printf(
                    '<input type="%s" name="%s[%s]" value="%s" class="regular-text" autocomplete="off" /><p class="description">%s</p>',
                    esc_attr( $type ),
                    esc_attr( self::OPTION ),
                    esc_attr( $key ),
                    esc_attr( $val ),
                    esc_html( $help )
                );
            }, 'griffnode-payments', 'griffnode_main' );
        }
    }

    public static function sanitize( $input ): array {
        $out = [];
        $out['publishable_key']  = sanitize_text_field( $input['publishable_key'] ?? '' );
        $out['secret_key']       = sanitize_text_field( $input['secret_key'] ?? '' );
        $out['webhook_secret']   = sanitize_text_field( $input['webhook_secret'] ?? '' );
        $cur                     = strtoupper( sanitize_text_field( $input['default_currency'] ?? 'USD' ) );
        $out['default_currency'] = in_array( $cur, [ 'USD', 'PLN', 'EUR', 'GBP' ], true ) ? $cur : 'USD';
        return $out;
    }

    public static function render_page() {
        if ( ! current_user_can( 'manage_options' ) ) {
            return;
        }
        $webhook_url = rest_url( 'griffnode/v1/webhook' );
        ?>
        <div class="wrap">
            <h1>GriffNode Payments</h1>
            <form method="post" action="options.php">
                <?php
                settings_fields( 'griffnode_settings_group' );
                do_settings_sections( 'griffnode-payments' );
                submit_button();
                ?>
            </form>

            <hr />
            <h2><?php esc_html_e( 'Webhook endpoint', 'griffnode-payments' ); ?></h2>
            <p><?php esc_html_e( 'Add this URL in your GriffNode dashboard under Webhooks and subscribe to payment.completed, payment.partial and payment.expired:', 'griffnode-payments' ); ?></p>
            <p><code><?php echo esc_url( $webhook_url ); ?></code></p>

            <h2><?php esc_html_e( 'Adding a pay button', 'griffnode-payments' ); ?></h2>
            <p><?php esc_html_e( 'Drop this shortcode into any post, page or widget:', 'griffnode-payments' ); ?></p>
            <p><code>[griffnode_button amount="49.99" currency="USD" button_text="Pay with Crypto"]</code></p>
            <p class="description"><?php esc_html_e( 'Optional attributes: crypto (lock to one coin), reference (your order id), email (pre-fill), success_url, cancel_url.', 'griffnode-payments' ); ?></p>
        </div>
        <?php
    }
}
