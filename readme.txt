=== Weld Cardano ===
Contributors: invalidcredentials
Tags: cardano, blockchain, wallet, web3, cryptocurrency
Requires at least: 5.8
Tested up to: 6.9
Stable tag: 0.3.1
Requires PHP: 7.4
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Cardano wallet connectivity and transaction signing for WordPress. Connect browser wallets, build transactions, sign server-side with pure PHP.

== Description ==

Weld Cardano brings Cardano to WordPress without the typical dApp stack. Inspired by the Weld wallet connector by Anvil. No Next.js, no Webpack, no WASM blobs. Just a clean plugin.

**Frontend wallet architecture inspired by [Weld](https://github.com/Cardano-Forge/weld) v0.6.0 by [Anvil](https://ada-anvil.io).**
**Built using the [Anvil API](https://ada-anvil.io). Not an official Anvil product.**
**Server-side signing powered by [PHP-Cardano](https://github.com/invalidcredentials/PHP-Cardano).**

= What it does =

* **Browser wallet connection** — Connect Eternl, Lace, Nami, and other CIP-30 wallets via shortcodes
* **Transaction building** — Build unsigned Cardano transactions through the Anvil API
* **Browser-side signing** — Users sign transactions in their own wallet (keys never touch the server)
* **Server-side signing** — Generate custodial wallets and sign transactions with pure PHP (no external binaries)
* **Balance display** — Live ADA balance and native token/NFT display via Blockfrost
* **Admin dashboard** — Full wallet management with encrypted key storage, send ADA, archive/restore

= Shortcodes =

* `[weldpress_connect]` — Wallet connect button with modal
* `[weldpress_wallet_badge]` — Connected wallet address and balance
* `[weldpress_send]` — Send ADA form (browser-side signing)

= REST API =

* `GET /weldpress/v1/config` — Public configuration endpoint
* `POST /weldpress/v1/tx/build` — Build transactions via Anvil (authenticated)
* `POST /weldpress/v1/tx/submit` — Submit signed transactions via Anvil (authenticated)

= JavaScript API =

The plugin exposes `window.WeldPress` for custom integrations:

    WeldPress.wallet.connect('eternl');
    WeldPress.wallet.subscribe(function(state) {
        console.log(state.address, state.balanceAda);
    });

= Bundle size =

The entire frontend is **6 KB gzipped**. The PHP transaction signer is 18 KB. No WASM dependencies.

== External Services ==

This plugin connects to the following third-party services when configured by the site administrator. No data is transmitted until API keys are added and a user initiates a blockchain operation.

= Ada Anvil API =

* **Service:** [Ada Anvil](https://ada-anvil.io)
* **Used for:** Building and submitting Cardano transactions
* **Data sent:** Wallet addresses, transaction outputs, signed transaction data
* **When:** Only when a user or admin initiates a transaction (send ADA, build TX)
* **Terms of Service:** [https://ada-anvil.io/terms](https://ada-anvil.io/terms)
* **Privacy Policy:** [https://ada-anvil.io/privacy](https://ada-anvil.io/privacy)

= Blockfrost API =

* **Service:** [Blockfrost](https://blockfrost.io)
* **Used for:** Querying Cardano blockchain data (address balances, native asset metadata)
* **Data sent:** Wallet addresses for balance lookups, asset identifiers for metadata
* **When:** Only when the admin views the wallet dashboard or balance is fetched
* **Terms of Service:** [https://blockfrost.io/terms](https://blockfrost.io/terms)
* **Privacy Policy:** [https://blockfrost.io/privacy](https://blockfrost.io/privacy)

= IPFS Gateway =

* **Service:** [IPFS](https://ipfs.io)
* **Used for:** Loading NFT images referenced in CIP-25 on-chain metadata
* **Data sent:** None (images are fetched by the browser, not the server)
* **When:** Only when viewing native tokens with image metadata

== Installation ==

1. Upload the `weldpress` folder to `/wp-content/plugins/`
2. Activate the plugin through the Plugins menu
3. Go to **Weld Cardano > Settings** and select your network (Preprod or Mainnet)
4. Add your Anvil API key ([get one here](https://ada-anvil.io))
5. Add your Blockfrost API key ([free tier here](https://blockfrost.io))
6. Use `[weldpress_connect]` on any page to add a wallet connect button

= Optional: Custodial wallets =

1. Enable "Custodial Wallets" in Settings
2. Go to the Wallet tab and generate a wallet
3. **Save your seed phrase immediately** — it is shown only once
4. Fund the wallet and use the Send ADA form for server-side transactions

== Frequently Asked Questions ==

= Do I need an Anvil API key? =

Yes, for building and submitting transactions. Anvil provides the transaction infrastructure. Visit [ada-anvil.io](https://ada-anvil.io) to get a key.

= Do I need a Blockfrost API key? =

Only for the custodial wallet dashboard (balance display and token grid). Blockfrost offers a free tier at [blockfrost.io](https://blockfrost.io). Browser-side wallet connection works without it.

= Which wallets are supported? =

Any CIP-30 compatible Cardano wallet: Eternl, Lace, Nami, Flint, Typhon, GeroWallet, NuFi, Begin, VESPR, Yoroi.

= Is this safe for mainnet? =

The plugin encrypts all sensitive data (wallet keys, API keys) at rest. However, custodial wallet management carries inherent risk. Use at your own discretion and always keep backups of your seed phrase.

= What data does this plugin store? =

* **WordPress options:** Network selection, encrypted API keys, custodial toggle, plugin version
* **Custom database table:** Encrypted wallet mnemonics and signing keys, payment addresses, key hashes
* **Transients:** Temporary admin notices, one-time mnemonic display (5-minute TTL)

All stored data is removed on plugin uninstall.

= Does it work with WordPress multisite? =

The plugin has not been tested with multisite. Each site in a multisite network would need its own configuration.

= How are wallet keys encrypted? =

Wallet mnemonics and signing keys are encrypted with AES-256-CBC using a key derived from your WordPress security salts (AUTH_KEY, SECURE_AUTH_KEY, LOGGED_IN_KEY, NONCE_KEY). You can define a custom `WELDPRESS_MASTER_KEY` constant in `wp-config.php` for additional security.

API keys are encrypted with libsodium (XSalsa20-Poly1305) when available, with a base64 fallback.

= Can I use this for payments / e-commerce? =

Yes. The JavaScript API (`window.WeldPress`) allows you to build custom payment flows. See the [GitHub README](https://github.com/invalidcredentials/weld-for-wp) for integration examples including payment gates and automated disbursement.

== Screenshots ==

1. Settings page — network selection, API key configuration, custodial toggle
2. Wallet connect modal — detected CIP-30 wallets
3. Connected wallet badge — address, balance, wallet icon
4. Custodial wallet dashboard — live balance, native tokens, send ADA
5. Send ADA form — server-side build, sign, and submit

== Changelog ==

= 0.3.1 =
* Renamed plugin from "Weld for WP" to "Weld Cardano" (WordPress trademark compliance)
* Fixed all WordPress plugin check errors and warnings
* Prefixed Cardano library classes with WeldPress_ namespace
* Added phpcs annotations for direct database queries on custom table
* Improved nonce verification documentation in code
* Added .distignore for clean distribution builds

= 0.3.0 =
* Added Blockfrost API integration for live balance and native token display
* Added token/NFT grid with CIP-25 metadata, images, and expandable details
* Added Blockfrost API key settings (preprod + mainnet)
* Rewrote wallet admin UI with proper CSS (no inline styles)
* Moved admin JS to separate enqueued file
* Fixed copy buttons for non-HTTPS environments (Local dev)
* Added send confirmation dialog
* Added auto-refresh balance after send
* Added funding reminder banner
* Added mnemonic pulse animation
* Added responsive breakpoints for mobile
* Added privacy policy content registration

= 0.2.0 =
* Integrated custodial wallet system from pbay-marketplace-cardano
* Added wallet generation with PHP-Cardano (BIP39/CIP-1852)
* Added encrypted wallet storage (AES-256-CBC)
* Added server-side send ADA flow (build/sign/submit)
* Added wallet archive/restore lifecycle
* Added one-time mnemonic display
* Added auto-upgrade mechanism (no deactivate/reactivate needed)

= 0.1.0 =
* Initial release
* CIP-30 browser wallet connection (Eternl, Lace, Nami, etc.)
* Shortcodes: connect, badge, send
* REST API: config, build, submit
* Anvil API integration for transaction building
* Pure-PHP bech32 address encoding (no npm dependencies)
* Vite IIFE build pipeline (6 KB gzipped frontend)
* Admin settings with encrypted API key storage
* Dev theme with demo page

== Upgrade Notice ==

= 0.3.0 =
Adds live balance display and native token grid. Requires a Blockfrost API key for wallet dashboard features. Existing settings are preserved.

= 0.2.0 =
Adds custodial wallet support. The database table is created automatically on upgrade.
