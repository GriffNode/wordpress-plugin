=== GriffNode Crypto Payments ===
Contributors: griffnode
Tags: cryptocurrency, bitcoin, crypto payments, payments, ethereum
Requires at least: 6.0
Tested up to: 7.1
Requires PHP: 7.4
Stable tag: 1.0.2
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Accept Bitcoin, Ethereum and stablecoins anywhere on WordPress with a shortcode. Non-custodial, straight to your own wallet.

== Description ==

**GriffNode Crypto Payments lets you accept cryptocurrency anywhere on WordPress with a simple shortcode, no WooCommerce required.** Drop a "Pay with crypto" button into any post, page or widget, and the money settles **straight to your own wallet**. GriffNode is non-custodial: we never hold, touch, or can freeze your funds. There are no chargebacks.

You connect your wallet's extended public key (xPub) once at [griffnode.com](https://griffnode.com/), and a fresh receiving address is derived for every payment, so funds go customer to you directly.

**Add a pay button with one shortcode:**

`[griffnode_button amount="49" reference="order-123"]`

Optional attributes let you lock to one coin, pre-fill the customer email, or set success and cancel URLs. Full options are in the [documentation](https://docs.griffnode.com/).

**Why accept crypto this way**

* **Non-custodial**: funds settle directly to your wallet, not ours. We literally cannot spend or freeze them.
* **No chargebacks**: on-chain settlement is final. No clawbacks weeks after you deliver.
* **Live in minutes**: sign up, paste your xPub, drop in the shortcode.
* **Works anywhere**: posts, pages, sidebars, any theme. No WooCommerce, no store required. For WooCommerce stores, use our separate GriffNode for WooCommerce plugin.
* **Flat monthly pricing**: no percentage per transaction. See [pricing](https://griffnode.com/).

**Coins supported**

Bitcoin (BTC), Ethereum (ETH), Litecoin (LTC), Dogecoin (DOGE), Dash (DASH), plus the ERC-20 stablecoins **USDT, USDC and DAI** for price-stable payments.

**Features**

* `[griffnode_button]` shortcode with a live coin selector and a hosted, mobile-friendly payment page (BIP-21 QR code).
* Real-time on-chain confirmation via signed webhooks (HMAC-SHA256).
* Handles completed, partial and expired payment events.
* Settings screen for your API keys and the webhook URL.

A GriffNode account and API keys are required (see External services below).

== External services ==

This plugin relies on the GriffNode API (`api.griffnode.com`), operated by GriffNode, to create crypto payment sessions and to confirm payments. It is not optional: the plugin cannot function without it.

**What is sent, and when:**

* When a page with the `[griffnode_button]` shortcode loads, the visitor's browser fetches the list of cryptocurrencies you have configured wallets for from `https://api.griffnode.com/merchant/cryptos`, authenticated with your Publishable Key (safe to expose, it grants no write access). This request reveals the visitor's IP address to GriffNode.
* When a visitor clicks the pay button, your site sends the amount, fiat currency, chosen cryptocurrency, your site URL, the button's reference, and (if you set them in the shortcode) the customer email and success/cancel URLs to `https://api.griffnode.com/transactions/create`, authenticated with your Secret Key. The visitor is then redirected to the GriffNode hosted payment page.
* When the payment status changes, GriffNode sends a signed webhook to your site at `/wp-json/griffnode/v1/webhook`. The plugin verifies the HMAC-SHA256 signature before acting on it.

GriffNode [Terms of Service](https://info.griffnode.com/terms-of-service) and [Privacy Policy](https://info.griffnode.com/privacy-policy).

== Installation ==

1. Create a free account at [griffnode.com](https://griffnode.com/) and connect your wallet's xPub.
2. In your GriffNode dashboard, copy your Publishable key, Secret key and Webhook secret.
3. Install this plugin: Plugins → Add New → Upload, choose the zip, and Activate.
4. Go to Settings → GriffNode and paste your keys.
5. In your GriffNode dashboard, set the webhook URL shown on the settings screen.
6. Add `[griffnode_button amount="10"]` to any page and place a small test payment.

== Frequently Asked Questions ==

= Do I need WooCommerce? =
No. This plugin works on any WordPress site via the `[griffnode_button]` shortcode. If you run a WooCommerce store, use our separate GriffNode for WooCommerce plugin instead.

= Where does the money go? =
Directly to your own wallet, every time, via the xPub you connect. GriffNode cannot custody, spend, or freeze it.

= Are crypto payments reversible? =
No. On-chain settlement is final, so there are no chargebacks.

= Which cryptocurrencies can customers pay with? =
Bitcoin, Ethereum, Litecoin, Dogecoin and Dash, plus the USDT, USDC and DAI stablecoins.

= Where are the docs? =
Full documentation and shortcode options are at [docs.griffnode.com](https://docs.griffnode.com/).

== Screenshots ==

1. A "Pay with crypto" button rendered on a WordPress page by the shortcode.
2. The GriffNode Crypto Payments settings screen (API keys and webhook URL).
3. The hosted payment page with a BIP-21 QR code and live confirmation status.

== Changelog ==

= 1.0.2 =
* Works on every GriffNode plan. Without a webhook (free Starter plan) the plugin checks payment status through the API when the customer returns and every 5 minutes, and fires the same griffnode_payment_* actions (with source = api_check).
* Fixed: non-USD buttons were charged in USD (the currency was sent under the wrong field name).
* Fixed: the transaction id returned after creating a payment was always empty.
* Webhook events fire once: retries and replays of the same event id are ignored, and events older than 48 hours are refused.
* The API status check also runs alongside a webhook as a backstop (missed delivery, or a plan downgraded to Starter); each payment still fires once.

= 1.0.1 =
* Renamed to GriffNode Crypto Payments.
* Pay button script is now a properly enqueued file, loaded only on pages that show a button.
* The cryptocurrency list is built safely with DOM methods and fetched once per page.
* Documented the external services the plugin uses.

= 1.0.0 =
* Initial release: shortcode-based non-custodial crypto payments for any WordPress site (BTC, ETH, LTC, DOGE, DASH + USDT/USDC/DAI), hosted checkout, signed webhooks.

== Upgrade Notice ==

= 1.0.0 =
Initial release.
