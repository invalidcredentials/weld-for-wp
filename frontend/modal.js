/**
 * Wallet connect modal — vanilla DOM component.
 */
import { getInstalledWallets, connect, disconnect, subscribe, getState } from './wallet.js';

let modalEl = null;

/**
 * Create and inject the modal into the DOM.
 */
function ensureModal() {
    if (modalEl) return modalEl;

    modalEl = document.createElement('div');
    modalEl.className = 'weldpress-modal-overlay';
    modalEl.setAttribute('role', 'dialog');
    modalEl.setAttribute('aria-label', 'Connect Wallet');
    modalEl.innerHTML = `
        <div class="weldpress-modal">
            <div class="weldpress-modal__header">
                <h3 class="weldpress-modal__title">Connect Wallet</h3>
                <button class="weldpress-modal__close" aria-label="Close">&times;</button>
            </div>
            <div class="weldpress-modal__body">
                <div class="weldpress-modal__wallets"></div>
                <div class="weldpress-modal__empty" style="display:none;">
                    <p>No Cardano wallets detected.</p>
                    <p class="weldpress-modal__hint">Install a CIP-30 compatible wallet extension like Eternl, Lace, or Nami.</p>
                </div>
                <div class="weldpress-modal__connected" style="display:none;">
                    <div class="weldpress-modal__connected-info"></div>
                    <button class="weldpress-modal__disconnect">Disconnect</button>
                </div>
                <div class="weldpress-modal__loading" style="display:none;">
                    <div class="weldpress-modal__spinner"></div>
                    <p>Connecting...</p>
                </div>
            </div>
        </div>
    `;

    document.body.appendChild(modalEl);

    // Close handlers.
    modalEl.querySelector('.weldpress-modal__close').addEventListener('click', closeModal);
    modalEl.addEventListener('click', (e) => {
        if (e.target === modalEl) closeModal();
    });

    // Disconnect handler.
    modalEl.querySelector('.weldpress-modal__disconnect').addEventListener('click', () => {
        disconnect();
        closeModal();
    });

    // Escape key.
    document.addEventListener('keydown', (e) => {
        if (e.key === 'Escape' && modalEl.classList.contains('weldpress-modal-overlay--open')) {
            closeModal();
        }
    });

    return modalEl;
}

/**
 * Render the wallet list in the modal.
 */
function renderWalletList() {
    const modal = ensureModal();
    const walletsEl = modal.querySelector('.weldpress-modal__wallets');
    const emptyEl = modal.querySelector('.weldpress-modal__empty');
    const connectedEl = modal.querySelector('.weldpress-modal__connected');
    const loadingEl = modal.querySelector('.weldpress-modal__loading');
    const wallets = getInstalledWallets();

    // Hide all states first.
    walletsEl.style.display = 'none';
    emptyEl.style.display = 'none';
    connectedEl.style.display = 'none';
    loadingEl.style.display = 'none';

    if (wallets.length === 0) {
        emptyEl.style.display = 'block';
        return;
    }

    walletsEl.style.display = 'block';
    walletsEl.innerHTML = '';

    wallets.forEach((wallet) => {
        const btn = document.createElement('button');
        btn.className = 'weldpress-modal__wallet-btn';
        btn.innerHTML = `
            ${wallet.icon ? `<img src="${wallet.icon}" alt="" class="weldpress-modal__wallet-icon">` : '<span class="weldpress-modal__wallet-icon-placeholder"></span>'}
            <span class="weldpress-modal__wallet-name">${escapeHtml(wallet.name)}</span>
        `;
        btn.addEventListener('click', () => handleConnect(wallet.key));
        walletsEl.appendChild(btn);
    });
}

/**
 * Handle wallet connection attempt.
 */
async function handleConnect(walletKey) {
    const modal = ensureModal();
    const walletsEl = modal.querySelector('.weldpress-modal__wallets');
    const loadingEl = modal.querySelector('.weldpress-modal__loading');

    walletsEl.style.display = 'none';
    loadingEl.style.display = 'flex';

    try {
        await connect(walletKey);
        closeModal();
    } catch (err) {
        loadingEl.style.display = 'none';
        walletsEl.style.display = 'block';
        console.error('[Weld for WP] Connection error:', err);
    }
}

/**
 * Show the modal with the connected state.
 */
function showConnectedState(state) {
    const modal = ensureModal();
    const walletsEl = modal.querySelector('.weldpress-modal__wallets');
    const emptyEl = modal.querySelector('.weldpress-modal__empty');
    const connectedEl = modal.querySelector('.weldpress-modal__connected');
    const loadingEl = modal.querySelector('.weldpress-modal__loading');
    const infoEl = modal.querySelector('.weldpress-modal__connected-info');

    // Hide all states first.
    walletsEl.style.display = 'none';
    emptyEl.style.display = 'none';
    loadingEl.style.display = 'none';
    connectedEl.style.display = 'block';

    const truncAddr = state.address
        ? state.address.substring(0, 12) + '...' + state.address.substring(state.address.length - 8)
        : 'Unknown';

    infoEl.innerHTML = `
        <div class="weldpress-modal__connected-wallet">
            ${state.walletIcon ? `<img src="${state.walletIcon}" alt="" class="weldpress-modal__wallet-icon">` : ''}
            <strong>${escapeHtml(state.walletName || 'Wallet')}</strong>
        </div>
        <div class="weldpress-modal__connected-address" title="${escapeHtml(state.address || '')}">${escapeHtml(truncAddr)}</div>
        ${state.balanceAda !== null ? `<div class="weldpress-modal__connected-balance">${escapeHtml(state.balanceAda)} ADA</div>` : ''}
    `;
}

/**
 * Open the modal.
 */
export function openModal() {
    const modal = ensureModal();

    const state = getState();

    if (state.isConnected) {
        showConnectedState(state);
    } else {
        renderWalletList();
    }

    modal.classList.add('weldpress-modal-overlay--open');
    document.body.style.overflow = 'hidden';
}

/**
 * Close the modal.
 */
export function closeModal() {
    if (modalEl) {
        modalEl.classList.remove('weldpress-modal-overlay--open');
        document.body.style.overflow = '';
    }
}

/**
 * Mount a connect button into a container element.
 */
export function mountConnectButton(container) {
    const label = container.getAttribute('data-label') || 'Connect Wallet';
    const theme = container.getAttribute('data-theme') || 'light';

    const btn = document.createElement('button');
    btn.className = `weldpress-connect-btn weldpress-connect-btn--${theme}`;
    btn.textContent = label;
    btn.addEventListener('click', openModal);

    container.appendChild(btn);

    // Update button text when connected.
    subscribe((state) => {
        if (state.isConnected) {
            const truncAddr = state.address
                ? state.address.substring(0, 8) + '...' + state.address.substring(state.address.length - 4)
                : 'Connected';
            btn.textContent = truncAddr;
            btn.classList.add('weldpress-connect-btn--connected');
        } else {
            btn.textContent = label;
            btn.classList.remove('weldpress-connect-btn--connected');
        }
    });
}

function escapeHtml(str) {
    const div = document.createElement('div');
    div.textContent = str;
    return div.innerHTML;
}
