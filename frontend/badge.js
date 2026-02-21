/**
 * Wallet badge component — shows connected wallet info.
 */
import { subscribe } from './wallet.js';

/**
 * Mount a wallet badge into a container element.
 */
export function mountBadge(container) {
    container.innerHTML = '';

    const badge = document.createElement('div');
    badge.className = 'weldpress-badge__inner';
    container.appendChild(badge);

    subscribe((state) => {
        if (!state.isConnected) {
            badge.innerHTML = '<span class="weldpress-badge__disconnected">No wallet connected</span>';
            badge.classList.remove('weldpress-badge__inner--connected');
            return;
        }

        badge.classList.add('weldpress-badge__inner--connected');

        const truncAddr = state.address
            ? state.address.substring(0, 12) + '...' + state.address.substring(state.address.length - 6)
            : 'Connected';

        let html = '<div class="weldpress-badge__row">';

        if (state.walletIcon) {
            html += `<img src="${escapeAttr(state.walletIcon)}" alt="" class="weldpress-badge__icon">`;
        }

        html += `<span class="weldpress-badge__name">${escapeHtml(state.walletName || 'Wallet')}</span>`;
        html += '</div>';

        html += `<div class="weldpress-badge__address" title="${escapeAttr(state.address || '')}">${escapeHtml(truncAddr)}</div>`;

        if (state.balanceAda !== null) {
            html += `<div class="weldpress-badge__balance">${escapeHtml(state.balanceAda)} <span class="weldpress-badge__ada">ADA</span></div>`;
        }

        badge.innerHTML = html;
    });
}

function escapeHtml(str) {
    const div = document.createElement('div');
    div.textContent = str || '';
    return div.innerHTML;
}

function escapeAttr(str) {
    return (str || '').replace(/"/g, '&quot;').replace(/</g, '&lt;').replace(/>/g, '&gt;');
}
