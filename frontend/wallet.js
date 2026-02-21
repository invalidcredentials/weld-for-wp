/**
 * Weld wallet wrapper — abstracts the Weld vanilla JS API.
 */
import { getWalletExtensions } from './extensions.js';
import { hexAddressToBech32 } from './bech32.js';

let walletState = {
    isConnected: false,
    address: null,       // Bech32 (display): addr_test1...
    addressHex: null,    // Raw hex (for CIP-30 API calls)
    stakeAddress: null,
    balanceLovelace: null,
    balanceAda: null,
    walletKey: null,
    walletName: null,
    walletIcon: null,
    handler: null,
};

const listeners = new Set();

/**
 * Notify all state listeners.
 */
function notify() {
    listeners.forEach((fn) => {
        try {
            fn({ ...walletState });
        } catch (e) {
            console.error('[Weld for WP] Listener error:', e);
        }
    });
}

/**
 * Subscribe to wallet state changes.
 */
export function subscribe(fn) {
    listeners.add(fn);
    // Immediately call with current state.
    fn({ ...walletState });
    return () => listeners.delete(fn);
}

/**
 * Get current wallet state.
 */
export function getState() {
    return { ...walletState };
}

/**
 * Detect installed CIP-30 wallet extensions.
 */
export function getInstalledWallets() {
    return getWalletExtensions();
}

/**
 * Connect to a CIP-30 wallet.
 */
export async function connect(walletKey) {
    const cardano = window.cardano;
    if (!cardano || !cardano[walletKey]) {
        throw new Error(`Wallet "${walletKey}" not found. Is the extension installed?`);
    }

    const provider = cardano[walletKey];

    try {
        const api = await provider.enable();

        // Get addresses (CIP-30 returns hex-encoded).
        const usedAddresses = await api.getUsedAddresses();
        const unusedAddresses = await api.getUnusedAddresses();
        const addressHex = usedAddresses[0] || unusedAddresses[0] || null;

        // Convert hex to bech32 for display.
        const address = addressHex ? hexAddressToBech32(addressHex) : null;

        // Get balance (CBOR-encoded).
        const balanceCbor = await api.getBalance();
        const balanceLovelace = parseBalanceCbor(balanceCbor);

        // Get reward/stake addresses.
        let stakeAddressHex = null;
        if (typeof api.getRewardAddresses === 'function') {
            const rewardAddresses = await api.getRewardAddresses();
            stakeAddressHex = rewardAddresses[0] || null;
        }
        const stakeAddress = stakeAddressHex ? hexAddressToBech32(stakeAddressHex) : null;

        walletState = {
            isConnected: true,
            address,
            addressHex,
            stakeAddress,
            balanceLovelace,
            balanceAda: balanceLovelace !== null ? (Number(balanceLovelace) / 1_000_000).toFixed(6) : null,
            walletKey,
            walletName: provider.name || walletKey,
            walletIcon: provider.icon || null,
            handler: api,
        };

        // Persist for reconnection.
        try {
            localStorage.setItem('weldpress_last_wallet', walletKey);
        } catch (e) {
            // localStorage may be unavailable.
        }

        notify();
        return walletState;
    } catch (err) {
        // User rejected or wallet error.
        console.warn('[Weld for WP] Connect failed:', err);
        throw err;
    }
}

/**
 * Disconnect the current wallet.
 */
export function disconnect() {
    walletState = {
        isConnected: false,
        address: null,
        addressHex: null,
        stakeAddress: null,
        balanceLovelace: null,
        balanceAda: null,
        walletKey: null,
        walletName: null,
        walletIcon: null,
        handler: null,
    };

    try {
        localStorage.removeItem('weldpress_last_wallet');
    } catch (e) {
        // Ignore.
    }

    notify();
}

/**
 * Try to reconnect to the last used wallet.
 */
export async function tryReconnect() {
    try {
        const lastWallet = localStorage.getItem('weldpress_last_wallet');
        if (lastWallet && window.cardano && window.cardano[lastWallet]) {
            await connect(lastWallet);
        }
    } catch (e) {
        // Reconnection failed silently — user may need to approve again.
        localStorage.removeItem('weldpress_last_wallet');
    }
}

/**
 * Sign a transaction using the connected wallet.
 *
 * @param {string} txCbor  - Unsigned transaction CBOR hex.
 * @param {boolean} partialSign - Whether this is a partial signature.
 * @returns {Promise<string>} Witness set hex.
 */
export async function signTx(txCbor, partialSign = false) {
    if (!walletState.handler) {
        throw new Error('No wallet connected.');
    }
    return walletState.handler.signTx(txCbor, partialSign);
}

/**
 * Get UTXOs from the connected wallet.
 */
export async function getUtxos() {
    if (!walletState.handler) {
        throw new Error('No wallet connected.');
    }
    return walletState.handler.getUtxos();
}

/**
 * Parse a CBOR-encoded balance to lovelace string.
 * CIP-30 balance is CBOR: either a simple uint or a 2-element array [coin, multiasset].
 */
function parseBalanceCbor(hex) {
    if (!hex) return null;

    try {
        // Simple approach: try to parse as integer directly.
        // CBOR unsigned int encoding:
        // 0x00-0x17 = value 0-23
        // 0x18 XX = 1-byte uint
        // 0x19 XXXX = 2-byte uint
        // 0x1a XXXXXXXX = 4-byte uint
        // 0x1b XXXXXXXXXXXXXXXX = 8-byte uint
        // 0x82 = array of 2 items (coin + multiasset)
        const firstByte = parseInt(hex.substring(0, 2), 16);

        let coinHex = hex;

        // If it's an array (0x82), the coin value starts at byte 1.
        if (firstByte === 0x82) {
            coinHex = hex.substring(2);
        }

        return decodeCborUint(coinHex);
    } catch (e) {
        console.warn('[Weld for WP] Balance parse failed:', e);
        return null;
    }
}

function decodeCborUint(hex) {
    const first = parseInt(hex.substring(0, 2), 16);
    const major = first >> 5;

    if (major !== 0) return '0'; // Not an unsigned int.

    const additional = first & 0x1f;

    if (additional <= 23) {
        return String(additional);
    }
    if (additional === 24) {
        return String(parseInt(hex.substring(2, 4), 16));
    }
    if (additional === 25) {
        return String(parseInt(hex.substring(2, 6), 16));
    }
    if (additional === 26) {
        return String(parseInt(hex.substring(2, 10), 16));
    }
    if (additional === 27) {
        // 8-byte uint — use BigInt for safety.
        return BigInt('0x' + hex.substring(2, 18)).toString();
    }

    return '0';
}
