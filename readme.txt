=== GriffNode Payments ===
Contributors: griffnode
Tags: cryptocurrency, bitcoin, crypto payments, payments, ethereum
Requires at least: 6.0
Tested up to: 7.0
Requires PHP: 7.4
Stable tag: 1.0.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Accept crypto payments anywhere on WordPress with a shortcode. Bitcoin, Ethereum and stablecoins, non-custodial, straight to your own wallet. No KYC.

== Description ==

**GriffNode Payments lets you accept cryptocurrency anywhere on WordPress with a simple shortcode - no WooCommerce required.** Drop a "Pay with crypto" button into any post, page or widget, and the money settles **straight to your own wallet**. GriffNode is non-custodial: we never hold, touch, or can freeze your funds. There are no chargebacks and no KYC.

You connect your wallet's extended public key (xPub) once at [griffnode.com](https://griffnode.com/), and a fresh receiving address is derived for every payment, so funds go customer to you directly.

**Add a pay button with one shortcode:**

`[griffnode_button amount="49" reference="order-123"]`

Optional attributes let you lock to one coin, pre-fill the customer email, or set success and cancel URLs. Full options are in the [documentation](https://docs.griffnode.com/).

**Why accept crypto this way**

* **Non-custodial** - funds settle directly to your wallet, not ours. We literally cannot spend or freeze them.
* **No chargebacks** - on-chain settlement is final. No clawbacks weeks after you deliver.
* **No KYC** - because we never custody funds, signing up is just an email and password.
* **Works anywhere** - posts, pages, sidebars, any theme. No WooCommerce, no store required. For WooCommerce stores, use our separate GriffNode for WooCommerce plugin.
* **Flat monthly pricing** - no percentage per transaction. See [pricing](https://griffnode.com/).

**Coins supported**

Bitcoin (BTC), Ethereum (ETH), Litecoin (LTC), Dogecoin (DOGE), Dash (DASH), plus the ERC-20 stablecoins **USDT, USDC and DAI** for price-stable payments.

**Features**

* `[griffnode_button]` shortcode with a live coin selector and a hosted, mobile-friendly payment page (BIP-21 QR code).
* Real-time on-chain confirmation via signed webhooks (HMAC-SHA256).
* Handles completed, partial and expired payment events.
* Settings screen for your API keys and the webhook URL.

This plugin connects to the GriffNode API (griffnode.com) to create hosted payment sessions and receive payment webhooks; a free GriffNode account and API key are required.

== Installation ==

1. Create a free account at [griffnode.com](https://griffnode.com/) and connect your wallet's xPub.
2. In your GriffNode dashboard, copy your Publishable key, Secret key and Webhook secret.
3. Install this plugin: Plugins → Add New → Upload, choose the zip, and Activate.
4. Go to Settings → GriffNode Payments and paste your keys.
5. In your GriffNode dashboard, set the webhook URL shown on the settings screen.
6. Add `[griffnode_button amount="10"]` to any page and place a small test payment.

== Frequently Asked Questions ==

= Do I need WooCommerce? =
No. This plugin works on any WordPress site via the `[griffnode_button]` shortcode. If you run a WooCommerce store, use our separate GriffNode for WooCommerce plugin instead.

= Do I need KYC or identity verification? =
No. Because GriffNode never holds your funds, there is no custodial obligation to verify identity. You sign up with an email and password.

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
2. The GriffNode Payments settings screen (API keys and webhook URL).
3. The hosted payment page with a BIP-21 QR code and live confirmation status.

== Changelog ==

= 1.0.0 =
* Initial release: shortcode-based non-custodial crypto payments for any WordPress site (BTC, ETH, LTC, DOGE, DASH + USDT/USDC/DAI), hosted checkout, signed webhooks.

== Upgrade Notice ==

= 1.0.0 =
Initial release.
