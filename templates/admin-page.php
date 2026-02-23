<?php
/**
 * Weld Cardano admin page template.
 *
 * @var string              $tab      Current tab slug.
 * @var WeldPress_Settings  $settings Settings instance.
 */

defined( 'ABSPATH' ) || exit;

$tabs = array(
    'settings' => 'Settings',
    'tools'    => 'Tools',
    'wallet'   => 'Wallet',
    'docs'     => 'Docs',
);

// Show admin notices.
$weldpress_notice      = get_transient( 'weldpress_admin_notice' );
$weldpress_notice_type = get_transient( 'weldpress_admin_notice_type' ) ?: 'success';
if ( $weldpress_notice ) {
    delete_transient( 'weldpress_admin_notice' );
    delete_transient( 'weldpress_admin_notice_type' );
}
?>
<div class="wrap">
    <h1><?php echo esc_html( 'Weld Cardano' ); ?></h1>
    <p class="description">Cardano wallet connectivity &amp; transaction signing for WordPress. A pb project.</p>

    <?php if ( $weldpress_notice ) : ?>
        <div class="notice notice-<?php echo esc_attr( $weldpress_notice_type ); ?> is-dismissible">
            <p><?php echo esc_html( $weldpress_notice ); ?></p>
        </div>
    <?php endif; ?>

    <nav class="nav-tab-wrapper">
        <?php foreach ( $tabs as $weldpress_slug => $weldpress_label ) : ?>
            <a href="<?php echo esc_url( admin_url( "admin.php?page=weldpress&tab={$weldpress_slug}" ) ); ?>"
               class="nav-tab <?php echo $tab === $weldpress_slug ? 'nav-tab-active' : ''; ?>">
                <?php echo esc_html( $weldpress_label ); ?>
            </a>
        <?php endforeach; ?>
    </nav>

    <div class="weldpress-admin-content" style="margin-top: 20px;">

    <?php if ( 'tools' === $tab ) : ?>

        <h3>Shortcodes</h3>
        <p>Use these shortcodes on any page or post:</p>
        <table class="widefat" style="max-width: 600px;">
            <thead>
                <tr><th>Shortcode</th><th>Description</th></tr>
            </thead>
            <tbody>
                <tr>
                    <td><code>[weldpress_connect]</code></td>
                    <td>Wallet connect button with modal</td>
                </tr>
                <tr>
                    <td><code>[weldpress_wallet_badge]</code></td>
                    <td>Connected wallet address &amp; balance</td>
                </tr>
                <tr>
                    <td><code>[weldpress_send]</code></td>
                    <td>Send ADA form (requires wallet connection)</td>
                </tr>
            </tbody>
        </table>

        <h3 style="margin-top: 24px;">REST API Endpoints</h3>
        <table class="widefat" style="max-width: 600px;">
            <thead>
                <tr><th>Method</th><th>Endpoint</th><th>Auth</th></tr>
            </thead>
            <tbody>
                <tr>
                    <td><code>GET</code></td>
                    <td><code>/wp-json/weldpress/v1/config</code></td>
                    <td>Public</td>
                </tr>
                <tr>
                    <td><code>POST</code></td>
                    <td><code>/wp-json/weldpress/v1/tx/build</code></td>
                    <td>Nonce + auth</td>
                </tr>
                <tr>
                    <td><code>POST</code></td>
                    <td><code>/wp-json/weldpress/v1/tx/submit</code></td>
                    <td>Nonce + auth</td>
                </tr>
            </tbody>
        </table>

        <h3 style="margin-top: 24px;">System Info</h3>
        <table class="widefat" style="max-width: 600px;">
            <tbody>
                <tr><td>Plugin Version</td><td><?php echo esc_html( WELDPRESS_VERSION ); ?></td></tr>
                <tr><td>PHP Version</td><td><?php echo esc_html( PHP_VERSION ); ?></td></tr>
                <tr><td>OpenSSL Extension</td><td><?php echo function_exists( 'openssl_encrypt' ) ? '<span style="color:#00a32a;">Available</span>' : '<span style="color:#d63638;">Not available</span>'; ?></td></tr>
                <tr><td>Sodium Extension</td><td><?php echo function_exists( 'sodium_crypto_secretbox' ) ? '<span style="color:#00a32a;">Available</span>' : '<span style="color:#d63638;">Not available</span>'; ?></td></tr>
                <tr><td>BCMath Extension</td><td><?php echo function_exists( 'bcadd' ) ? '<span style="color:#00a32a;">Available</span>' : '<span style="color:#d63638;">Not available</span>'; ?></td></tr>
                <tr><td>Active Network</td><td><?php echo esc_html( $settings->get_network() ); ?></td></tr>
            </tbody>
        </table>

    <?php elseif ( 'wallet' === $tab ) : ?>

        <div class="wfwp-wallet-tab">

        <?php if ( ! $settings->is_custodial_enabled() ) : ?>
            <div class="notice notice-info inline">
                <p>Custodial wallet support is currently <strong>disabled</strong>.
                <a href="<?php echo esc_url( admin_url( 'admin.php?page=weldpress&tab=settings' ) ); ?>">Enable it in Settings</a> to use server-side wallets.</p>
            </div>

        <?php else :
            $weldpress_network          = $settings->get_network();
            $weldpress_active_wallet    = WeldPress_Wallet_Model::get_active_wallet( $weldpress_network );
            $weldpress_archived_wallets = WeldPress_Wallet_Model::get_archived( $weldpress_network );
            $weldpress_show_mnemonic    = get_transient( 'weldpress_wallet_mnemonic_' . get_current_user_id() );
            if ( $weldpress_show_mnemonic ) {
                delete_transient( 'weldpress_wallet_mnemonic_' . get_current_user_id() );
            }
        ?>

            <?php // ── One-Time Seed Phrase Display ── ?>
            <?php if ( $weldpress_show_mnemonic ) : ?>
                <div class="wfwp-mnemonic-card">
                    <h3>SAVE YOUR SEED PHRASE NOW</h3>
                    <p><strong>This will only be shown ONCE.</strong> Write it down and store it securely offline.</p>
                    <div class="wfwp-mnemonic-words"><?php echo esc_html( $weldpress_show_mnemonic ); ?></div>
                    <p>
                        <button type="button" class="button wfwp-copy-btn" data-copy="<?php echo esc_attr( $weldpress_show_mnemonic ); ?>">Copy to Clipboard</button>
                    </p>
                </div>
            <?php endif; ?>

            <?php if ( $weldpress_active_wallet ) : ?>

                <?php // ── Active Wallet Dashboard ── ?>
                <div class="wfwp-wallet-card" data-wallet-id="<?php echo esc_attr( $weldpress_active_wallet['id'] ); ?>">
                    <h3>
                        <?php echo esc_html( $weldpress_active_wallet['wallet_name'] ); ?>
                        <span class="wfwp-network-badge wfwp-<?php echo esc_attr( $weldpress_active_wallet['network'] ); ?>">
                            <?php echo esc_html( $weldpress_active_wallet['network'] ); ?>
                        </span>
                    </h3>

                    <?php // ── Balance (loaded via AJAX) ── ?>
                    <div class="wfwp-balance-wrap">
                        <div id="wfwp-balance">
                            <span class="wfwp-loading">
                                <span class="spinner is-active" style="float:none;margin:0 8px 0 0;"></span>
                                Loading balance...
                            </span>
                        </div>
                    </div>

                    <?php // ── Wallet Details ── ?>
                    <table class="wfwp-details-table">
                        <tr>
                            <th>Payment Address</th>
                            <td>
                                <code><?php echo esc_html( $weldpress_active_wallet['payment_address'] ); ?></code>
                                <button type="button" class="button button-small wfwp-copy-btn" data-copy="<?php echo esc_attr( $weldpress_active_wallet['payment_address'] ); ?>">Copy</button>
                            </td>
                        </tr>
                        <tr>
                            <th>Key Hash</th>
                            <td>
                                <code><?php echo esc_html( $weldpress_active_wallet['payment_keyhash'] ); ?></code>
                                <button type="button" class="button button-small wfwp-copy-btn" data-copy="<?php echo esc_attr( $weldpress_active_wallet['payment_keyhash'] ); ?>">Copy</button>
                            </td>
                        </tr>
                        <tr>
                            <th>Created</th>
                            <td><?php echo esc_html( $weldpress_active_wallet['created_at'] ); ?></td>
                        </tr>
                    </table>

                    <?php // ── Token / NFT Grid (loaded via AJAX) ── ?>
                    <div id="wfwp-assets-container" class="wfwp-assets-container"></div>

                    <?php // ── Funding Reminder ── ?>
                    <div class="wfwp-funding-reminder">
                        <span class="dashicons dashicons-info"></span>
                        Keep this wallet funded with at least 3-5 ADA to cover transaction fees.
                    </div>

                    <?php // ── Action Buttons ── ?>
                    <div class="wfwp-actions">
                        <button type="button" class="button button-primary" id="wfwp-send-toggle">Send ADA</button>
                        <button type="button" class="button" id="wfwp-archive-wallet" data-id="<?php echo esc_attr( $weldpress_active_wallet['id'] ); ?>">Archive</button>
                        <span class="wfwp-actions-right">
                            <form method="post" style="display:inline;" onsubmit="return confirm('Permanently delete this wallet? This cannot be undone.');">
                                <?php wp_nonce_field( 'weldpress_delete_wallet' ); ?>
                                <input type="hidden" name="weldpress_wallet_action" value="delete">
                                <input type="hidden" name="wallet_id" value="<?php echo esc_attr( $weldpress_active_wallet['id'] ); ?>">
                                <a href="#" class="wfwp-delete-link" onclick="this.closest('form').submit(); return false;">Delete Wallet</a>
                            </form>
                        </span>
                    </div>

                    <?php // ── Send ADA Form (slide toggle) ── ?>
                    <div id="wfwp-send-form" class="wfwp-send-form">
                        <h4>Send ADA</h4>
                        <div class="wfwp-send-fields">
                            <label for="wfwp-send-recipient">Recipient</label>
                            <input type="text" id="wfwp-send-recipient" class="regular-text" placeholder="<?php echo 'mainnet' === $weldpress_network ? 'addr1...' : 'addr_test1...'; ?>">

                            <label for="wfwp-send-amount">Amount</label>
                            <input type="number" id="wfwp-send-amount" step="0.1" min="1" placeholder="ADA">
                        </div>
                        <div class="wfwp-send-actions">
                            <button type="button" class="button button-primary" id="wfwp-send-confirm">Send</button>
                            <a href="#" id="wfwp-send-cancel">Cancel</a>
                        </div>
                        <div id="wfwp-send-status" class="wfwp-send-status"></div>
                    </div>
                </div>

            <?php else : ?>

                <?php // ── Generate Wallet Form ── ?>
                <div class="wfwp-generate-card">
                    <h3>Create Wallet (<?php echo esc_html( $weldpress_network ); ?>)</h3>
                    <p>No active wallet for <strong><?php echo esc_html( $weldpress_network ); ?></strong>. Generate one to enable server-side signing.</p>
                    <form method="post">
                        <?php wp_nonce_field( 'weldpress_generate_wallet' ); ?>
                        <input type="hidden" name="weldpress_wallet_action" value="generate">
                        <table class="wfwp-details-table">
                            <tr>
                                <th><label for="wallet_name">Wallet Name</label></th>
                                <td><input type="text" name="wallet_name" id="wallet_name" value="Weld Cardano Wallet" class="regular-text"></td>
                            </tr>
                        </table>
                        <?php submit_button( 'Generate Wallet', 'primary', 'submit', true ); ?>
                    </form>
                </div>

            <?php endif; ?>

            <?php // ── Archived Wallets ── ?>
            <?php if ( ! empty( $weldpress_archived_wallets ) ) : ?>
                <div class="wfwp-archived-card">
                    <h3 class="wfwp-archived-toggle" id="wfwp-archived-toggle">
                        Archived Wallets (<?php echo count( $weldpress_archived_wallets ); ?>)
                        <span class="dashicons dashicons-arrow-down-alt2"></span>
                    </h3>
                    <div id="wfwp-archived-list" class="wfwp-slide">
                        <div class="wfwp-archived-grid">
                            <?php foreach ( $weldpress_archived_wallets as $weldpress_aw ) : ?>
                                <div class="wfwp-archived-item">
                                    <div class="wfwp-archived-info">
                                        <strong>
                                            <?php echo esc_html( $weldpress_aw['wallet_name'] ); ?>
                                            <span class="wfwp-network-badge wfwp-<?php echo esc_attr( $weldpress_aw['network'] ); ?>" style="font-size:10px;padding:1px 6px;">
                                                <?php echo esc_html( $weldpress_aw['network'] ); ?>
                                            </span>
                                        </strong>
                                        <br>
                                        <code><?php echo esc_html( substr( $weldpress_aw['payment_address'], 0, 24 ) . '...' . substr( $weldpress_aw['payment_address'], -8 ) ); ?></code>
                                        <br>
                                        <small>Archived: <?php echo esc_html( $weldpress_aw['archived_at'] ); ?></small>
                                    </div>
                                    <div class="wfwp-archived-actions">
                                        <button type="button" class="button button-small wfwp-unarchive" data-id="<?php echo esc_attr( $weldpress_aw['id'] ); ?>">Restore</button>
                                        <a href="#" class="wfwp-delete-archived wfwp-delete-link" data-id="<?php echo esc_attr( $weldpress_aw['id'] ); ?>" data-name="<?php echo esc_attr( $weldpress_aw['wallet_name'] ); ?>">Delete</a>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
            <?php endif; ?>

        <?php endif; ?>

        </div><?php // .wfwp-wallet-tab ?>

    <?php elseif ( 'docs' === $tab ) : ?>

        <div class="wfwp-docs">

            <p class="wfwp-docs-meta">
                Built using the <a href="https://ada-anvil.io" target="_blank" rel="noopener">Anvil API</a>. Not an official Anvil product.<br>
                Frontend wallet architecture inspired by <a href="https://github.com/Cardano-Forge/weld" target="_blank" rel="noopener">Weld</a> v0.6.0 by <a href="https://ada-anvil.io" target="_blank" rel="noopener">Anvil</a>.<br>
                Server-side signing powered by <a href="https://github.com/invalidcredentials/PHP-Cardano" target="_blank" rel="noopener">PHP-Cardano</a>.
            </p>

            <!-- ── Overview ── -->

            <h2>What is Weld Cardano?</h2>

            <p>Weld Cardano is a WordPress plugin that connects your site to the <strong>Cardano blockchain</strong>. It lets visitors connect their Cardano wallets (like Eternl, Lace, or Nami) directly in the browser, and it lets you &mdash; the site admin &mdash; manage a server-side wallet for automated transactions. Everything runs through WordPress: shortcodes for the frontend, a REST API for developers, and an admin dashboard for wallet management.</p>

            <p>There is no React, no Next.js, no WebAssembly, and no Node.js runtime required. The entire frontend is a <strong>6 KB</strong> vanilla JavaScript bundle. The server-side transaction signer is <strong>18 KB of pure PHP</strong>. That&rsquo;s it.</p>

            <h3>How Cardano Wallets Work (Quick Primer)</h3>

            <p>On Cardano, a <strong>wallet</strong> is really just a pair of cryptographic keys: a <em>signing key</em> (private, used to authorize transactions) and a <em>verification key</em> (public, used to derive your address). When you &ldquo;send ADA,&rdquo; you&rsquo;re constructing a <strong>transaction</strong> &mdash; a structured message that says &ldquo;move X lovelace from address A to address B&rdquo; &mdash; and then <strong>signing</strong> it with your private key to prove you own the funds. That signed transaction is then <strong>submitted</strong> to the Cardano network, where validators confirm it and update the ledger.</p>

            <p>Browser wallets like Eternl and Lace implement <strong>CIP-30</strong>, a standard that lets websites request wallet actions (connect, get address, sign a transaction) without ever seeing the user&rsquo;s private keys. The keys stay inside the browser extension. This plugin uses CIP-30 for all user-facing wallet interactions.</p>

            <h3>The Three Services</h3>

            <p>This plugin doesn&rsquo;t talk to the Cardano network directly. Instead, it uses two specialized APIs that handle the heavy lifting, plus a chain indexer for reading data:</p>

            <table class="widefat wfwp-docs-table">
                <thead><tr><th>Service</th><th>What it does</th><th>Why you need it</th></tr></thead>
                <tbody>
                    <tr>
                        <td><strong><a href="https://ada-anvil.io" target="_blank" rel="noopener">Anvil</a></strong></td>
                        <td>Builds unsigned transactions and submits signed ones to the network. Handles UTxO selection, fee calculation, and CBOR encoding &mdash; the complex parts of Cardano transaction construction.</td>
                        <td>Without a transaction builder, you&rsquo;d need a full Cardano node or a heavy client-side library. Anvil does it with a single REST call.</td>
                    </tr>
                    <tr>
                        <td><strong><a href="https://blockfrost.io" target="_blank" rel="noopener">Blockfrost</a></strong></td>
                        <td>Reads data from the Cardano blockchain: address balances, native token holdings, NFT metadata (CIP-25). It&rsquo;s a chain indexer &mdash; it watches the blockchain and lets you query it.</td>
                        <td>Used in the admin Wallet dashboard to show your balance and tokens. The free tier is more than enough for most sites.</td>
                    </tr>
                    <tr>
                        <td><strong><a href="https://github.com/invalidcredentials/PHP-Cardano" target="_blank" rel="noopener">PHP-Cardano</a></strong></td>
                        <td>A pure PHP library that generates Cardano wallets (BIP39 mnemonics, CIP-1852 key derivation) and signs transactions using Ed25519 cryptography. No compiled binaries, no WASM, no external processes.</td>
                        <td>This is what makes server-side signing possible on a standard PHP host. Most Cardano signing libraries require Node.js or Haskell &mdash; this one runs on any server with PHP 7.4+.</td>
                    </tr>
                </tbody>
            </table>

            <h3>Two Ways to Sign Transactions</h3>

            <p>The plugin supports two distinct signing paths, and understanding which to use is important:</p>

            <div class="wfwp-docs-paths">
                <div class="wfwp-docs-path">
                    <h4>Browser-Side Signing (CIP-30)</h4>
                    <p>This is the standard dApp flow. A visitor to your site connects their own wallet (Eternl, Lace, etc.) via the <code>[weldpress_connect]</code> shortcode. When they want to send ADA or interact with a contract, the plugin:</p>
                    <ol>
                        <li><strong>Builds</strong> an unsigned transaction by calling the Anvil API from your server</li>
                        <li><strong>Returns</strong> the unsigned transaction to the browser</li>
                        <li><strong>Asks</strong> the user&rsquo;s wallet extension to sign it (the user sees a confirmation popup)</li>
                        <li><strong>Submits</strong> the signed transaction back through your server to Anvil</li>
                    </ol>
                    <p>The user&rsquo;s private keys <strong>never touch your server</strong>. This is the right path for any user-facing feature: payments, donations, token-gated content, marketplace purchases.</p>
                </div>

                <div class="wfwp-docs-path">
                    <h4>Server-Side Signing (Custodial)</h4>
                    <p>This is for wallets that <em>your server</em> controls. You generate a wallet in the admin Wallet tab, and the plugin stores the encrypted signing key in your database. When you send ADA from this wallet, the plugin:</p>
                    <ol>
                        <li><strong>Decrypts</strong> the signing key from the database</li>
                        <li><strong>Builds</strong> an unsigned transaction via Anvil</li>
                        <li><strong>Signs</strong> it with PHP-Cardano&rsquo;s pure-PHP Ed25519 implementation</li>
                        <li><strong>Submits</strong> the signed transaction to Anvil</li>
                    </ol>
                    <p>No browser is involved. This is the right path for automated flows: treasury disbursements, reward payouts, batch transfers, cron-based payments, or any operation that needs to happen without human interaction.</p>
                </div>
            </div>

            <h3>What You Can Build</h3>

            <p>With these two signing paths, you can build a wide range of Cardano-powered features on any WordPress site:</p>
            <ul>
                <li><strong>Accept ADA payments</strong> &mdash; drop a shortcode on a page, wire up a payment confirmation to your backend</li>
                <li><strong>Token-gated content</strong> &mdash; show pages or sections only to users who connect a wallet</li>
                <li><strong>Automated payouts</strong> &mdash; use the custodial wallet to send rewards or refunds from PHP code or a cron job</li>
                <li><strong>Treasury management</strong> &mdash; monitor your project wallet balance and native tokens from the admin dashboard</li>
                <li><strong>Multi-output transactions</strong> &mdash; send to multiple recipients in a single transaction (Anvil supports batching)</li>
                <li><strong>Custom dApp UIs</strong> &mdash; use the JavaScript API (<code>window.WeldPress</code>) to build completely custom flows in your theme</li>
            </ul>

            <p>The sections below cover every tool the plugin gives you: shortcodes, the REST API, the JavaScript API, and full integration examples with working code.</p>

            <hr>

            <h2>Shortcodes</h2>

            <table class="widefat wfwp-docs-table">
                <thead><tr><th>Shortcode</th><th>Description</th><th>Attributes</th></tr></thead>
                <tbody>
                    <tr>
                        <td><code>[weldpress_connect]</code></td>
                        <td>Wallet connect button with modal. Lists all detected CIP-30 wallets.</td>
                        <td><code>label</code> (default: "Connect Wallet"), <code>theme</code> ("light" or "dark")</td>
                    </tr>
                    <tr>
                        <td><code>[weldpress_wallet_badge]</code></td>
                        <td>Displays connected wallet icon, name, truncated address, and ADA balance.</td>
                        <td>None</td>
                    </tr>
                    <tr>
                        <td><code>[weldpress_send]</code></td>
                        <td>Send ADA form. Build &rarr; Sign (browser wallet) &rarr; Submit.</td>
                        <td><code>to</code> (pre-fill address), <code>amount</code> (pre-fill ADA)</td>
                    </tr>
                </tbody>
            </table>

            <hr>

            <h2>REST API</h2>
            <p>All endpoints under <code>/wp-json/weldpress/v1/</code></p>

            <table class="widefat wfwp-docs-table">
                <thead><tr><th>Method</th><th>Endpoint</th><th>Auth</th><th>Description</th></tr></thead>
                <tbody>
                    <tr>
                        <td><code>GET</code></td>
                        <td><code>/config</code></td>
                        <td>Public</td>
                        <td>Returns <code>{ network, features }</code></td>
                    </tr>
                    <tr>
                        <td><code>POST</code></td>
                        <td><code>/tx/build</code></td>
                        <td>Nonce + logged-in</td>
                        <td>Build unsigned TX via Anvil. Send <code>{ change_address, outputs: [{ address, lovelace }] }</code></td>
                    </tr>
                    <tr>
                        <td><code>POST</code></td>
                        <td><code>/tx/submit</code></td>
                        <td>Nonce + logged-in</td>
                        <td>Submit signed TX via Anvil. Send <code>{ transaction, witnesses: [...] }</code></td>
                    </tr>
                </tbody>
            </table>

            <hr>

            <h2>JavaScript API</h2>
            <p>The frontend exposes <code>window.WeldPress</code> for custom integrations:</p>

            <pre class="wfwp-code"><span class="wfwp-code-comment">// Connect a wallet</span>
await WeldPress.wallet.connect('eternl');

<span class="wfwp-code-comment">// Get wallet state</span>
const state = WeldPress.wallet.getState();
console.log(state.address);     <span class="wfwp-code-comment">// addr_test1qr...</span>
console.log(state.balanceAda);  <span class="wfwp-code-comment">// "142.567890"</span>

<span class="wfwp-code-comment">// Subscribe to changes</span>
WeldPress.wallet.subscribe((state) =&gt; {
  if (state.isConnected) {
    console.log('Connected:', state.walletName);
  }
});

<span class="wfwp-code-comment">// List installed wallets</span>
const wallets = WeldPress.wallet.getInstalledWallets();

<span class="wfwp-code-comment">// Build + sign + submit a transaction</span>
const build = await WeldPress.api.buildTransaction(
  state.addressHex,
  [{ address: 'addr_test1qz...', lovelace: 5000000 }]
);
const witness = await WeldPress.wallet.signTx(build.complete);
const result = await WeldPress.api.submitTransaction(build.complete, [witness]);
console.log('TX Hash:', result.txHash);</pre>

            <h3>Available Methods</h3>
            <table class="widefat wfwp-docs-table">
                <thead><tr><th>Namespace</th><th>Method</th><th>Description</th></tr></thead>
                <tbody>
                    <tr><td><code>wallet</code></td><td><code>connect(walletKey)</code></td><td>Connect a CIP-30 wallet by key (e.g. 'eternl', 'lace', 'nami')</td></tr>
                    <tr><td><code>wallet</code></td><td><code>disconnect()</code></td><td>Disconnect and reset state</td></tr>
                    <tr><td><code>wallet</code></td><td><code>getState()</code></td><td>Returns current state: address, balance, walletName, isConnected, etc.</td></tr>
                    <tr><td><code>wallet</code></td><td><code>subscribe(fn)</code></td><td>Subscribe to state changes. Returns unsubscribe function.</td></tr>
                    <tr><td><code>wallet</code></td><td><code>getInstalledWallets()</code></td><td>Returns array of detected CIP-30 wallets</td></tr>
                    <tr><td><code>wallet</code></td><td><code>signTx(cbor, partial)</code></td><td>Sign a transaction via the connected wallet</td></tr>
                    <tr><td><code>wallet</code></td><td><code>getUtxos()</code></td><td>Get UTxOs from the connected wallet</td></tr>
                    <tr><td><code>api</code></td><td><code>fetchConfig()</code></td><td>GET /weldpress/v1/config</td></tr>
                    <tr><td><code>api</code></td><td><code>buildTransaction(addr, outputs)</code></td><td>POST /weldpress/v1/tx/build</td></tr>
                    <tr><td><code>api</code></td><td><code>submitTransaction(tx, witnesses)</code></td><td>POST /weldpress/v1/tx/submit</td></tr>
                    <tr><td><code>ui</code></td><td><code>openModal()</code></td><td>Open the wallet connect modal</td></tr>
                    <tr><td><code>ui</code></td><td><code>closeModal()</code></td><td>Close the modal</td></tr>
                </tbody>
            </table>

            <hr>

            <h2>Integration Examples</h2>

            <h3>Payment Gate</h3>
            <p>Accept ADA payments on any page:</p>
            <pre class="wfwp-code"><span class="wfwp-code-comment">// In your theme or custom plugin</span>
function my_payment_page() {
    echo do_shortcode('[weldpress_connect]');
    echo '&lt;button id="pay-btn"&gt;Pay 10 ADA&lt;/button&gt;
          &lt;div id="pay-status"&gt;&lt;/div&gt;';
    ?&gt;
    &lt;script&gt;
    document.getElementById('pay-btn').addEventListener('click', async () =&gt; {
        const state = WeldPress.wallet.getState();
        if (!state.isConnected) return alert('Connect wallet first');

        const build = await WeldPress.api.buildTransaction(
            state.addressHex,
            [{ address: 'addr_test1qz...MERCHANT...', lovelace: 10000000 }]
        );
        const witness = await WeldPress.wallet.signTx(build.complete);
        const result = await WeldPress.api.submitTransaction(
            build.complete, [witness]
        );

        document.getElementById('pay-status').textContent =
            'Paid! TX: ' + result.txHash;

        <span class="wfwp-code-comment">// Notify your backend</span>
        await fetch('/wp-json/my-shop/v1/confirm', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json',
                       'X-WP-Nonce': weldpressConfig.nonce },
            body: JSON.stringify({ tx_hash: result.txHash })
        });
    });
    &lt;/script&gt;
    &lt;?php
}
</pre>

            <h3>Server-Side Treasury Disbursement</h3>
            <p>Use the custodial wallet from PHP for automated payouts:</p>
            <pre class="wfwp-code"><span class="wfwp-code-comment">// Enable custodial wallets in Settings, generate a wallet in the Wallet tab</span>

function disburse_rewards() {
    $settings = new WeldPress_Settings();
    $wallet   = WeldPress_Wallet_Model::get_active_wallet( $settings-&gt;get_network() );
    if ( ! $wallet ) return;

    <span class="wfwp-code-comment">// Decrypt signing key</span>
    $skey = WeldPress_Encryption::decrypt( $wallet['skey_encrypted'] );

    <span class="wfwp-code-comment">// Build via Anvil (multiple outputs supported)</span>
    $client = new WeldPress_Anvil_Client();
    $build  = $client-&gt;build( $wallet['payment_address'], array(
        array( 'address' =&gt; 'addr_test1qz...user1...', 'lovelace' =&gt; 2000000 ),
        array( 'address' =&gt; 'addr_test1qz...user2...', 'lovelace' =&gt; 3000000 ),
    ) );
    if ( is_wp_error( $build ) ) return;

    <span class="wfwp-code-comment">// Sign with PHP-Cardano (pure PHP, no WASM, no CLI)</span>
    require_once WELDPRESS_DIR . 'includes/cardano/WeldPress_Ed25519Compat.php';
    require_once WELDPRESS_DIR . 'includes/cardano/WeldPress_Ed25519Pure.php';
    require_once WELDPRESS_DIR . 'includes/cardano/WeldPress_CardanoTransactionSignerPHP.php';
    WeldPress_Ed25519Compat::init();

    $signed = WeldPress_CardanoTransactionSignerPHP::signTransaction(
        $build['complete'], $skey
    );
    if ( ! $signed['success'] ) return;

    <span class="wfwp-code-comment">// Submit</span>
    $result = $client-&gt;submit(
        $build['complete'], array( $signed['witnessSetHex'] )
    );
    $tx_hash = $result['txHash'] ?? '';
    error_log( 'Disbursed: ' . $tx_hash );
}</pre>

            <h3>Gated Content</h3>
            <p>Show content only to connected wallets:</p>
            <pre class="wfwp-code">function gated_shortcode( $atts, $content = '' ) {
    $out  = do_shortcode( '[weldpress_connect]' );
    $out .= '&lt;div id="gated" style="display:none"&gt;'
          . wp_kses_post( $content ) . '&lt;/div&gt;';
    $out .= '&lt;script&gt;WeldPress.wallet.subscribe(function(s) {
        document.getElementById("gated").style.display =
            s.isConnected ? "block" : "none";
    });&lt;/script&gt;';
    return $out;
}
add_shortcode( 'cardano_gated', 'gated_shortcode' );

<span class="wfwp-code-comment">// Usage:</span>
[cardano_gated]Secret content for wallet holders.[/cardano_gated]</pre>

            <hr>

            <h2>Security</h2>
            <ul>
                <li>All admin actions require <code>manage_options</code> capability</li>
                <li>All AJAX handlers verify nonces via <code>check_ajax_referer()</code></li>
                <li>All REST mutations require authentication + WP nonce</li>
                <li>All input sanitized with <code>sanitize_text_field()</code>, <code>absint()</code>, regex validation</li>
                <li>All output escaped with <code>esc_html()</code>, <code>esc_attr()</code>, <code>esc_url()</code></li>
                <li>API keys encrypted at rest with libsodium (XSalsa20-Poly1305)</li>
                <li>Wallet keys encrypted at rest with AES-256-CBC</li>
                <li>Encryption keys derived from WordPress security salts via SHA-256</li>
                <li>Optional <code>WELDPRESS_MASTER_KEY</code> constant for custom key derivation</li>
                <li>Mnemonic displayed once (5-minute transient), then permanently inaccessible in plaintext</li>
                <li>API keys never exposed to the browser &mdash; all external calls proxied server-side</li>
                <li>Uninstall removes all options, transients, and drops the custom table</li>
            </ul>

            <hr>

            <h2>External Services</h2>
            <p>This plugin connects to third-party services <strong>only when configured</strong> by the admin. No data is transmitted until API keys are added and a user initiates a blockchain operation.</p>

            <table class="widefat wfwp-docs-table">
                <thead><tr><th>Service</th><th>Purpose</th><th>Data Sent</th></tr></thead>
                <tbody>
                    <tr>
                        <td><a href="https://ada-anvil.io" target="_blank" rel="noopener">Ada Anvil API</a></td>
                        <td>Transaction building &amp; submission</td>
                        <td>Wallet addresses, transaction outputs, signed TX data</td>
                    </tr>
                    <tr>
                        <td><a href="https://blockfrost.io" target="_blank" rel="noopener">Blockfrost</a></td>
                        <td>Blockchain data queries</td>
                        <td>Wallet addresses (balance lookups), asset identifiers</td>
                    </tr>
                    <tr>
                        <td><a href="https://ipfs.io" target="_blank" rel="noopener">IPFS Gateway</a></td>
                        <td>NFT image loading</td>
                        <td>None (browser fetches images directly)</td>
                    </tr>
                </tbody>
            </table>

            <hr>

            <h2>Credits</h2>
            <ul>
                <li><a href="https://github.com/Cardano-Forge/weld" target="_blank" rel="noopener">Weld</a> by <a href="https://ada-anvil.io" target="_blank" rel="noopener">Anvil</a> &mdash; wallet connector architecture inspiration (MIT)</li>
                <li><a href="https://github.com/invalidcredentials/PHP-Cardano" target="_blank" rel="noopener">PHP-Cardano</a> by pb &mdash; pure PHP wallet generation and transaction signing (MIT)</li>
                <li><a href="https://ada-anvil.io" target="_blank" rel="noopener">Ada Anvil</a> &mdash; transaction building and submission API</li>
                <li><a href="https://blockfrost.io" target="_blank" rel="noopener">Blockfrost</a> &mdash; Cardano blockchain data API</li>
            </ul>

            <p class="wfwp-docs-footer">
                <strong>Weld Cardano</strong> v<?php echo esc_html( WELDPRESS_VERSION ); ?> &mdash; GPLv2 or later &mdash;
                <a href="https://github.com/invalidcredentials/weld-for-wp" target="_blank" rel="noopener">GitHub</a>
            </p>

        </div>

    <?php else : /* settings tab */ ?>

        <?php
        $weldpress_network   = $settings->get_network();
        $weldpress_custodial = $settings->is_custodial_enabled();
        $weldpress_key_preprod_set     = ! empty( get_option( 'weldpress_anvil_key_preprod', '' ) );
        $weldpress_key_mainnet_set     = ! empty( get_option( 'weldpress_anvil_key_mainnet', '' ) );
        $weldpress_bf_key_preprod_set  = ! empty( get_option( 'weldpress_blockfrost_key_preprod', '' ) );
        $weldpress_bf_key_mainnet_set  = ! empty( get_option( 'weldpress_blockfrost_key_mainnet', '' ) );
        ?>
        <form method="post" action="">
            <?php wp_nonce_field( 'weldpress_save_settings' ); ?>
            <input type="hidden" name="weldpress_action" value="save_settings">

            <h3>Network</h3>
            <table class="form-table">
                <tr>
                    <th scope="row"><label for="weldpress_network">Active Network</label></th>
                    <td>
                        <select name="weldpress_network" id="weldpress_network">
                            <option value="preprod" <?php selected( $weldpress_network, 'preprod' ); ?>>Preprod (Testnet)</option>
                            <option value="mainnet" <?php selected( $weldpress_network, 'mainnet' ); ?>>Mainnet</option>
                        </select>
                        <p class="description">Select the Cardano network. Use Preprod for testing.</p>
                    </td>
                </tr>
            </table>

            <h3>Anvil API</h3>
            <p class="description">Transaction building &amp; submission. <a href="https://ada-anvil.io" target="_blank" rel="noopener">Get API keys</a></p>
            <table class="form-table">
                <tr>
                    <th scope="row"><label for="weldpress_anvil_key_preprod">Preprod Key</label></th>
                    <td>
                        <input type="password" name="weldpress_anvil_key_preprod" id="weldpress_anvil_key_preprod"
                               class="regular-text" placeholder="<?php echo $weldpress_key_preprod_set ? '••••••••' : 'Enter API key'; ?>"
                               autocomplete="off">
                        <?php if ( $weldpress_key_preprod_set ) : ?>
                            <span class="dashicons dashicons-yes-alt" style="color: #00a32a; vertical-align: middle;"></span>
                        <?php endif; ?>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><label for="weldpress_anvil_key_mainnet">Mainnet Key</label></th>
                    <td>
                        <input type="password" name="weldpress_anvil_key_mainnet" id="weldpress_anvil_key_mainnet"
                               class="regular-text" placeholder="<?php echo $weldpress_key_mainnet_set ? '••••••••' : 'Enter API key'; ?>"
                               autocomplete="off">
                        <?php if ( $weldpress_key_mainnet_set ) : ?>
                            <span class="dashicons dashicons-yes-alt" style="color: #00a32a; vertical-align: middle;"></span>
                        <?php endif; ?>
                    </td>
                </tr>
            </table>

            <h3>Blockfrost API</h3>
            <p class="description">Balance queries &amp; chain data. <a href="https://blockfrost.io" target="_blank" rel="noopener">Get free API keys</a></p>
            <table class="form-table">
                <tr>
                    <th scope="row"><label for="weldpress_blockfrost_key_preprod">Preprod Key</label></th>
                    <td>
                        <input type="password" name="weldpress_blockfrost_key_preprod" id="weldpress_blockfrost_key_preprod"
                               class="regular-text" placeholder="<?php echo $weldpress_bf_key_preprod_set ? '••••••••' : 'preprodXXXXXXXXXXXX'; ?>"
                               autocomplete="off">
                        <?php if ( $weldpress_bf_key_preprod_set ) : ?>
                            <span class="dashicons dashicons-yes-alt" style="color: #00a32a; vertical-align: middle;"></span>
                        <?php endif; ?>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><label for="weldpress_blockfrost_key_mainnet">Mainnet Key</label></th>
                    <td>
                        <input type="password" name="weldpress_blockfrost_key_mainnet" id="weldpress_blockfrost_key_mainnet"
                               class="regular-text" placeholder="<?php echo $weldpress_bf_key_mainnet_set ? '••••••••' : 'mainnetXXXXXXXXXXXX'; ?>"
                               autocomplete="off">
                        <?php if ( $weldpress_bf_key_mainnet_set ) : ?>
                            <span class="dashicons dashicons-yes-alt" style="color: #00a32a; vertical-align: middle;"></span>
                        <?php endif; ?>
                    </td>
                </tr>
            </table>

            <h3>Features</h3>
            <table class="form-table">
                <tr>
                    <th scope="row">Custodial Wallets</th>
                    <td>
                        <label>
                            <input type="checkbox" name="weldpress_custodial_enabled" value="1" <?php checked( $weldpress_custodial ); ?>>
                            Enable server-side custodial wallet support
                        </label>
                        <p class="description"><strong>Advanced.</strong> Allows the server to generate and sign with custodial keys.</p>
                    </td>
                </tr>
            </table>

            <?php submit_button( 'Save Settings' ); ?>
        </form>

        <hr>
        <h3>Test Connection</h3>
        <form method="post" action="" style="display: inline;">
            <?php wp_nonce_field( 'weldpress_test_connection' ); ?>
            <input type="hidden" name="weldpress_action" value="test_connection">
            <?php submit_button( 'Test Anvil API Connection', 'secondary', 'submit', false ); ?>
        </form>

    <?php endif; ?>

    </div>
</div>
