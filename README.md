# GriffNode Payments for WordPress

Accept Bitcoin, Litecoin, Dogecoin and Dash payments on **any** WordPress site — no WooCommerce required. Drop a pay button anywhere with a shortcode; customers pick a coin and are redirected to a hosted payment page. Funds go directly to your wallet, GriffNode never holds them.

> Running a WooCommerce store? Use the dedicated [GriffNode for WooCommerce](https://github.com/CryptoGateHQ/woocommerce-plugin) plugin instead — it hooks straight into checkout and orders.

**Requirements:** WordPress 6.0+, PHP 7.4+

---

## Installation

### 1 — Download

Download the latest `griffnode-payments.zip` from the [Releases](https://github.com/CryptoGateHQ/wordpress-plugin/releases) page.

### 2 — Install

1. In WordPress admin go to **Plugins → Add New → Upload Plugin**
2. Choose the downloaded ZIP and click **Install Now**
3. Click **Activate Plugin**

### 3 — Configure

Go to **Settings → GriffNode** and fill in:

| Field | Where to find it |
|-------|-----------------|
| **Publishable Key** | Dashboard → API Integration → `pk_live_...` — safe to expose in the browser |
| **Secret Key** | Dashboard → API Integration → `sk_live_...` — keep this private |
| **Webhook Secret** | Dashboard → Webhooks → your endpoint's signing secret |
| **Default Currency** | Fiat used when a shortcode doesn't set one (USD, PLN, EUR, GBP) |

### 4 — Register your webhook

In your [GriffNode dashboard](https://griffnode.com/dashboard) go to **Webhooks** and add the URL shown on the settings page:

```
https://yoursite.com/wp-json/griffnode/v1/webhook
```

Subscribe to: `payment.completed`, `payment.partial`, `payment.expired`. Copy the signing secret into the **Webhook Secret** field.

### 5 — Add wallets

The coin selector only shows cryptos you have wallets for. Go to **Dashboard → Wallet Management** and add an xpub for each coin you want to accept.

---

## Adding a pay button

Put the shortcode in any post, page, or widget:

```
[griffnode_button amount="49.99" currency="USD" button_text="Pay with Crypto"]
```

| Attribute | Required | Description |
|-----------|----------|-------------|
| `amount` | ✅ | Price in fiat (e.g. `49.99`) |
| `currency` | — | `USD` (default from settings), `PLN`, `EUR`, `GBP` |
| `crypto` | — | Lock the button to one coin (e.g. `BTC`); omit to let the customer choose |
| `button_text` | — | Button label (default *Pay with Crypto*) |
| `reference` | — | Your order/reference id — echoed back in every webhook as `order_id` |
| `email` | — | Pre-fill the customer email on the payment page |
| `success_url` | — | Where to send the customer after a confirmed payment |
| `cancel_url` | — | Where to send the customer on expiry/cancel |

The amount and currency are **signed server-side**, so a customer can't tamper with the price in the browser.

---

## Reacting to payments (for developers)

When a verified webhook arrives, the plugin fires WordPress actions. Hook into them from your theme's `functions.php` or your own plugin to fulfil orders, grant access, send emails, etc.

```php
add_action( 'griffnode_payment_completed', function ( $event ) {
    // $event['order_id']       — your `reference` from the shortcode
    // $event['transaction_id'] — GriffNode transaction id
    // $event['amount_crypto'], $event['currency_crypto'], ...
    error_log( 'Paid: ' . $event['order_id'] );
} );

add_action( 'griffnode_payment_partial', function ( $event ) { /* underpaid */ } );
add_action( 'griffnode_payment_expired', function ( $event ) { /* window expired */ } );

// Or catch everything:
add_action( 'griffnode_webhook', function ( $event ) { /* $event['event'] = 'payment.completed' ... */ } );
```

Only HMAC-verified events reach these hooks.

---

## How it works

1. A visitor selects a cryptocurrency and clicks the button
2. The plugin calls the GriffNode API server-side (your secret key never touches the browser) and redirects to a hosted payment page
3. The customer sends the exact crypto amount within the 60-minute window
4. GriffNode fires a webhook — the plugin verifies it and triggers the action hooks above

---

## Test mode

Use `pk_test_...` / `sk_test_...` keys in the settings. No real funds move in test mode.

---

## Support

- [Documentation](https://docs.griffnode.com)
- [GriffNode Dashboard](https://griffnode.com/dashboard) — open a support ticket
- [GitHub Issues](https://github.com/CryptoGateHQ/wordpress-plugin/issues)
