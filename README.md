# Weld for WP

**Cardano wallet connectivity and transaction signing for WordPress.**

Built using the [Anvil API](https://ada-anvil.io). Not an official Anvil product.
Frontend wallet architecture inspired by [Weld](https://github.com/Cardano-Forge/weld) v0.6.0 by [Anvil](https://ada-anvil.io).
Server-side signing powered by [PHP-Cardano](https://github.com/invalidcredentials/PHP-Cardano).

A pb project.

---

## What is this?

Weld for WP brings Cardano to WordPress without the typical dApp stack. No Next.js, no Webpack, no CSL WASM blobs. Just a clean WordPress plugin that:

- **Connects browser wallets** (Eternl, Lace, Nami, etc.) via CIP-30
- **Builds and submits transactions** through the Anvil API
- **Signs transactions server-side** with pure PHP (no binaries, no CLI tools)
- **Manages custodial wallets** with encrypted key storage
- **Queries the blockchain** for balances and native assets via Blockfrost

The entire frontend bundle is **6 KB gzipped**. The PHP transaction signer is **18 KB**. Compare that to the typical 1.5+ MB CSL WASM dependency.

---

## Architecture

```
                     Browser                          WordPress Server
               +-----------------+              +------------------------+
               |  CIP-30 Wallet  |              |   Weld for WP Plugin   |
               |  (Eternl/Lace)  |              |                        |
               +--------+--------+              |  +------------------+  |
                        |                       |  |  REST API        |  |
  [weldpress_connect]   |   enable()            |  |  /weldpress/v1/  |  |
  [weldpress_badge]     +<--------------------->+  +--------+---------+  |
  [weldpress_send]      |   signTx()            |           |           |
                        |   getAddress()        |  +--------v---------+ |
                        |                       |  |  Anvil Client    | |
                        |                       |  |  (build/submit)  | |
                        |                       |  +--------+---------+ |
                        |                       |           |           |
                        |                       |  +--------v---------+ |
                        |                       |  | Blockfrost Client| |
                        |                       |  | (balance/assets) | |
                        |                       |  +------------------+ |
                        |                       |                        |
                        |                       |  +------------------+  |
                        |                       |  |  PHP-Cardano     |  |
                        |                       |  |  (generate keys, |  |
                        |                       |  |   sign tx)       |  |
                        |                       |  +------------------+  |
                        |                       |                        |
                        |                       |  +------------------+  |
                        |                       |  |  Wallet Model    |  |
                        |                       |  |  (encrypted DB)  |  |
                        |                       |  +------------------+  |
                        |                       +------------------------+
```

### Two signing paths

**Browser-side (CIP-30):** User connects their wallet extension. Transactions are built via Anvil, signed in the browser wallet, and submitted back through the REST API. The server never sees private keys.

**Server-side (custodial):** The admin generates a wallet in the plugin. Keys are encrypted with AES-256-CBC and stored in a custom database table. Transactions are built via Anvil, signed with PHP-Cardano's pure-PHP Ed25519 implementation, and submitted via Anvil. Useful for automated payments, treasury management, or any flow where a browser wallet popup isn't practical.

---

## Installation

### From source

```bash
cd wp-content/plugins/
git clone https://github.com/invalidcredentials/weld-for-wp.git weldpress
cd weldpress
npm install
npm run build
```

Activate in WordPress admin > Plugins.

### Configuration

1. **Weld for WP > Settings** — select your network (Preprod or Mainnet)
2. Add your **Anvil API key** ([get one here](https://ada-anvil.io))
3. Add your **Blockfrost API key** ([free tier here](https://blockfrost.io))
4. Optionally enable **Custodial Wallets** for server-side signing

---

## Shortcodes

### Connect Button

```
[weldpress_connect]
```

Renders a wallet connect button. When clicked, opens a modal listing all detected CIP-30 wallets. After connection, shows the truncated wallet address.

| Attribute | Default | Description |
|-----------|---------|-------------|
| `label` | "Connect Wallet" | Button text |
| `theme` | "light" | "light" or "dark" |

### Wallet Badge

```
[weldpress_wallet_badge]
```

Displays the connected wallet's icon, name, truncated address, and ADA balance. Hidden when no wallet is connected.

### Send ADA Form

```
[weldpress_send]
```

Renders a form for sending ADA from the connected browser wallet. The full flow: build (Anvil) -> sign (browser wallet) -> submit (Anvil).

| Attribute | Default | Description |
|-----------|---------|-------------|
| `to` | "" | Pre-fill recipient address |
| `amount` | "" | Pre-fill ADA amount |

---

## REST API

All endpoints are under `/wp-json/weldpress/v1/`.

### GET `/config`

Public. Returns the plugin configuration.

```json
{
  "network": "preprod",
  "features": { "custodial": false }
}
```

### POST `/tx/build`

Requires authentication (WordPress nonce via `X-WP-Nonce` header).

Builds an unsigned transaction via the Anvil API.

```json
// Request
{
  "change_address": "addr_test1qr...",
  "outputs": [
    { "address": "addr_test1qz...", "lovelace": 2000000 }
  ]
}

// Response
{
  "success": true,
  "complete": "84a400...",
  "fee": "180000"
}
```

### POST `/tx/submit`

Requires authentication.

Submits a signed transaction via the Anvil API.

```json
// Request
{
  "transaction": "84a400...",
  "witnesses": ["a10081..."]
}

// Response
{
  "success": true,
  "txHash": "abc123..."
}
```

---

## JavaScript API

The frontend bundle exposes `window.WeldPress` for custom integrations:

```javascript
// Connect a wallet
await WeldPress.wallet.connect('eternl');

// Get wallet state
const state = WeldPress.wallet.getState();
console.log(state.address);     // addr_test1qr...
console.log(state.balanceAda);  // "142.567890"

// Subscribe to changes
WeldPress.wallet.subscribe((state) => {
  if (state.isConnected) {
    console.log('Connected:', state.walletName);
  }
});

// List installed wallets
const wallets = WeldPress.wallet.getInstalledWallets();
// [{ key: 'eternl', name: 'Eternl', icon: '...', apiVersion: '0.1.0' }]

// Build + sign + submit a transaction
const buildResult = await WeldPress.api.buildTransaction(
  state.addressHex,
  [{ address: 'addr_test1qz...', lovelace: 5000000 }]
);

const witness = await WeldPress.wallet.signTx(buildResult.complete);

const submitResult = await WeldPress.api.submitTransaction(
  buildResult.complete,
  [witness]
);

console.log('TX Hash:', submitResult.txHash);
```

---

## Integration Examples

### Payment Gate

Accept ADA payments on any WordPress page. Combine the shortcodes with a bit of custom JS:

```php
// In your theme or a custom plugin
function my_payment_page() {
    echo do_shortcode('[weldpress_connect]');
    echo '<div id="payment-form" style="display:none;">
        <p>Send 10 ADA to complete your purchase.</p>
        <button id="pay-btn" class="button">Pay 10 ADA</button>
        <div id="pay-status"></div>
    </div>';
    ?>
    <script>
    WeldPress.wallet.subscribe(function(state) {
        document.getElementById('payment-form').style.display =
            state.isConnected ? 'block' : 'none';
    });

    document.getElementById('pay-btn').addEventListener('click', async function() {
        const state = WeldPress.wallet.getState();
        const status = document.getElementById('pay-status');

        status.textContent = 'Building transaction...';

        try {
            const build = await WeldPress.api.buildTransaction(
                state.addressHex,
                [{ address: 'addr_test1qz...YOUR_MERCHANT_ADDRESS...', lovelace: 10000000 }]
            );

            status.textContent = 'Please approve in your wallet...';
            const witness = await WeldPress.wallet.signTx(build.complete);

            status.textContent = 'Submitting...';
            const result = await WeldPress.api.submitTransaction(build.complete, [witness]);

            status.innerHTML = 'Payment confirmed! TX: ' + result.txHash;

            // Notify your backend
            await fetch('/wp-json/my-shop/v1/confirm-payment', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-WP-Nonce': weldpressConfig.nonce
                },
                body: JSON.stringify({ tx_hash: result.txHash, order_id: 123 })
            });
        } catch (e) {
            status.textContent = 'Error: ' + e.message;
        }
    });
    </script>
    <?php
}
```

### Automated Treasury Disbursement (Server-Side)

Use the custodial wallet from your own plugin code:

```php
// Enable custodial wallets in Settings first.
// Generate a wallet in the Wallet tab.

function disburse_rewards() {
    $settings = new WeldPress_Settings();
    $network  = $settings->get_network();
    $wallet   = WeldPress_Wallet_Model::get_active_wallet( $network );

    if ( ! $wallet ) return;

    // Decrypt signing key
    $skey_hex = WeldPress_Encryption::decrypt( $wallet['skey_encrypted'] );

    // Build transaction via Anvil
    $client = new WeldPress_Anvil_Client();
    $build  = $client->build( $wallet['payment_address'], array(
        array( 'address' => 'addr_test1qz...recipient1...', 'lovelace' => 2000000 ),
        array( 'address' => 'addr_test1qz...recipient2...', 'lovelace' => 3000000 ),
    ) );

    if ( is_wp_error( $build ) ) {
        error_log( 'Disbursement build failed: ' . $build->get_error_message() );
        return;
    }

    // Sign with PHP-Cardano (pure PHP, no external tools)
    require_once WELDPRESS_DIR . 'includes/cardano/Ed25519Compat.php';
    require_once WELDPRESS_DIR . 'includes/cardano/Ed25519Pure.php';
    require_once WELDPRESS_DIR . 'includes/cardano/CardanoTransactionSignerPHP.php';

    Ed25519Compat::init();
    $signed = CardanoTransactionSignerPHP::signTransaction( $build['complete'], $skey_hex );

    if ( ! $signed['success'] ) {
        error_log( 'Signing failed: ' . $signed['error'] );
        return;
    }

    // Submit
    $result = $client->submit( $build['complete'], array( $signed['witnessSetHex'] ) );

    if ( is_wp_error( $result ) ) {
        error_log( 'Submit failed: ' . $result->get_error_message() );
        return;
    }

    $tx_hash = $result['txHash'] ?? $result['hash'] ?? '';
    error_log( 'Disbursement sent: ' . $tx_hash );
}
```

### Gated Content by Wallet Connection

```php
function gated_content_shortcode( $atts, $content = '' ) {
    // This renders the connect button + the hidden content
    $output = do_shortcode( '[weldpress_connect]' );
    $output .= '<div id="gated-content" style="display:none;">' . wp_kses_post( $content ) . '</div>';
    $output .= '<script>
        WeldPress.wallet.subscribe(function(s) {
            document.getElementById("gated-content").style.display = s.isConnected ? "block" : "none";
        });
    </script>';
    return $output;
}
add_shortcode( 'cardano_gated', 'gated_content_shortcode' );
```

Usage: `[cardano_gated]This content is only visible to connected wallets.[/cardano_gated]`

---

## Custodial Wallet Dashboard

The admin wallet tab (Settings > Weld for WP > Wallet) provides:

- **Wallet generation** — creates a new wallet using PHP-Cardano's BIP39/CIP-1852 implementation
- **One-time seed phrase display** — shown once after generation, stored in a 5-minute transient
- **Live balance** — ADA balance and native tokens fetched from Blockfrost
- **Token/NFT grid** — visual tile grid with CIP-25 metadata, images (IPFS resolved), expandable details
- **Send ADA** — server-side build + sign + submit flow
- **Archive/restore** — soft-delete lifecycle for wallet rotation
- **Encrypted storage** — mnemonic and signing keys encrypted with AES-256-CBC

---

## Directory Structure

```
weldpress/
  weldpress.php                  Plugin entry point
  uninstall.php                  Clean removal of all data
  readme.txt                     WordPress.org readme
  LICENSE                        GPLv2 license

  includes/
    core/
      class-settings.php         Settings accessor, API key encryption (sodium)
      class-anvil-client.php     Anvil API client (build, submit, health)
      class-blockfrost-client.php  Blockfrost API client (balance, assets, CIP-25)
      class-encryption.php       AES-256-CBC encryption for wallet keys
      class-wallet-model.php     Custom DB table CRUD + archive lifecycle
    wp/
      class-plugin.php           Bootstrap, hooks, upgrade management
      class-admin.php            Admin menu page, settings form handlers
      class-assets.php           Script/style enqueue, wp_localize_script
      class-rest-api.php         REST route registration (config, build, submit)
      class-shortcodes.php       [weldpress_connect], [weldpress_wallet_badge], [weldpress_send]
      class-wallet-controller.php  Wallet AJAX handlers (generate, send, archive, balance)
    cardano/                     Vendored PHP-Cardano (MIT)
      CardanoWalletPHP.php       BIP39 mnemonic, CIP-1852 HD derivation, address generation
      CardanoTransactionSignerPHP.php  CBOR codec, Ed25519 signing, witness set
      Ed25519Compat.php          Crypto backend selector (sodium > FFI > pure PHP)
      Ed25519Pure.php            Pure PHP BCMath Ed25519 fallback
      bip39-wordlist.php         2048 BIP39 English words

  frontend/                      Vite source (compiled to assets/)
    main.js                      Entry point, window.WeldPress global
    wallet.js                    CIP-30 wrapper, state management, pub/sub
    modal.js                     Connect modal (vanilla DOM)
    badge.js                     Wallet badge component
    send.js                      Send ADA form component
    api.js                       REST API client
    bech32.js                    Hex-to-bech32 address encoding
    extensions.js                CIP-30 wallet detection
    styles.css                   Frontend component styles

  assets/                        Built output (git-tracked)
    js/weldpress.js              14 KB IIFE bundle (4.5 KB gzipped)
    js/weldpress-admin.js        Admin wallet dashboard JS
    css/weldpress.css            Frontend styles (1.5 KB gzipped)
    css/weldpress-admin.css      Admin dashboard styles

  templates/
    admin-page.php               Admin page template (Settings, Tools, Wallet tabs)
```

---

## Security

- All admin actions require `manage_options` capability
- All AJAX handlers verify nonces via `check_ajax_referer()`
- All REST mutations require authentication + WP nonce
- All input sanitized with `sanitize_text_field()`, `absint()`, regex validation
- All output escaped with `esc_html()`, `esc_attr()`, `esc_url()`
- API keys encrypted at rest with libsodium (XSalsa20-Poly1305)
- Wallet keys encrypted at rest with AES-256-CBC
- Encryption keys derived from WordPress security salts via SHA-256
- Optional `WELDPRESS_MASTER_KEY` constant for custom key derivation
- Mnemonic displayed once (5-minute transient), then permanently inaccessible in plaintext
- API keys never exposed to the browser — all external calls proxied server-side
- Custom database table with prepared statements throughout
- Uninstall removes all options, transients, and drops the custom table

---

## External Services

This plugin connects to third-party services only when configured by the admin:

**[Ada Anvil API](https://ada-anvil.io)** — Transaction building and submission. Wallet addresses and transaction parameters are sent to Anvil's servers. Requires an API key configured in Settings.

**[Blockfrost](https://blockfrost.io)** — Blockchain data queries (balances, native asset metadata). Wallet addresses are sent to Blockfrost's servers. Requires an API key configured in Settings.

**[IPFS Gateway](https://ipfs.io)** — NFT images from CIP-25 metadata are loaded from the public IPFS gateway. No user data is sent.

No data is transmitted to any external service until the admin configures API keys and a user initiates a blockchain operation.

---

## Requirements

- WordPress 5.8+
- PHP 7.4+
- OpenSSL extension (for wallet key encryption)
- BCMath extension (for pure-PHP Ed25519 fallback)
- Sodium extension recommended (for API key encryption, included in WP 5.2+)

---

## Credits

- [Weld](https://github.com/Cardano-Forge/weld) by [Anvil](https://ada-anvil.io) — wallet connector architecture inspiration (MIT)
- [PHP-Cardano](https://github.com/invalidcredentials/PHP-Cardano) by pb — pure PHP wallet generation and transaction signing (MIT)
- [Ada Anvil](https://ada-anvil.io) — transaction building and submission API
- [Blockfrost](https://blockfrost.io) — Cardano blockchain data API

---

## License

GPLv2 or later. See [LICENSE](LICENSE).

Vendored PHP-Cardano library is MIT licensed.
