/**
 * WeldPress — main entry point.
 *
 * Initializes wallet connectivity, scans the DOM for WeldPress components,
 * and exposes the window.WeldPress API.
 */
import * as wallet from './wallet.js';
import { mountConnectButton, openModal, closeModal } from './modal.js';
import { mountBadge } from './badge.js';
import { mountSendForm } from './send.js';
import * as api from './api.js';
import './styles.css';

/**
 * Scan the DOM and mount components into WeldPress containers.
 */
function mountComponents() {
    // Connect buttons.
    document.querySelectorAll('[data-weldpress="connect"]').forEach((el) => {
        if (!el.dataset.weldpressMounted) {
            mountConnectButton(el);
            el.dataset.weldpressMounted = 'true';
        }
    });

    // Wallet badges.
    document.querySelectorAll('[data-weldpress="badge"]').forEach((el) => {
        if (!el.dataset.weldpressMounted) {
            mountBadge(el);
            el.dataset.weldpressMounted = 'true';
        }
    });

    // Send forms.
    document.querySelectorAll('[data-weldpress="send"]').forEach((el) => {
        if (!el.dataset.weldpressMounted) {
            mountSendForm(el);
            el.dataset.weldpressMounted = 'true';
        }
    });
}

/**
 * Initialize WeldPress.
 */
async function init() {
    console.log('[Weld for WP] Initializing v' + (window.weldpressConfig?.version || '0.1.0'));

    // Mount components.
    mountComponents();

    // Try to reconnect to last wallet.
    await wallet.tryReconnect();

    console.log('[Weld for WP] Ready.');
}

// Expose global API.
window.WeldPress = {
    // Wallet operations.
    wallet: {
        connect: wallet.connect,
        disconnect: wallet.disconnect,
        subscribe: wallet.subscribe,
        getState: wallet.getState,
        getInstalledWallets: wallet.getInstalledWallets,
        signTx: wallet.signTx,
        getUtxos: wallet.getUtxos,
    },

    // UI.
    ui: {
        openModal,
        closeModal,
        mountComponents,
    },

    // REST API.
    api: {
        fetchConfig: api.fetchConfig,
        buildTransaction: api.buildTransaction,
        submitTransaction: api.submitTransaction,
    },
};

// Auto-init on DOM ready.
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init);
} else {
    init();
}
