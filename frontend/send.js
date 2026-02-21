/**
 * Send ADA form component.
 */
import { subscribe, getState, signTx } from './wallet.js';
import { buildTransaction, submitTransaction } from './api.js';

/**
 * Mount a send-ADA form into a container element.
 */
export function mountSendForm(container) {
    const prefillTo = container.getAttribute('data-to') || '';
    const prefillAmount = container.getAttribute('data-amount') || '';

    container.innerHTML = `
        <div class="weldpress-send__form">
            <div class="weldpress-send__field">
                <label class="weldpress-send__label" for="weldpress-send-to">Recipient Address</label>
                <input type="text" id="weldpress-send-to" class="weldpress-send__input"
                       placeholder="addr_test1..." value="${escapeAttr(prefillTo)}">
            </div>
            <div class="weldpress-send__field">
                <label class="weldpress-send__label" for="weldpress-send-amount">Amount (ADA)</label>
                <input type="number" id="weldpress-send-amount" class="weldpress-send__input"
                       placeholder="1.0" step="0.000001" min="1" value="${escapeAttr(prefillAmount)}">
            </div>
            <button class="weldpress-send__btn" disabled>Connect wallet to send</button>
            <div class="weldpress-send__status" style="display:none;"></div>
        </div>
    `;

    const btn = container.querySelector('.weldpress-send__btn');
    const statusEl = container.querySelector('.weldpress-send__status');
    const toInput = container.querySelector('#weldpress-send-to');
    const amountInput = container.querySelector('#weldpress-send-amount');

    // Track connection state.
    subscribe((state) => {
        if (state.isConnected) {
            btn.disabled = false;
            btn.textContent = 'Send ADA';
        } else {
            btn.disabled = true;
            btn.textContent = 'Connect wallet to send';
        }
    });

    btn.addEventListener('click', async () => {
        const state = getState();
        if (!state.isConnected || !state.address) return;

        const to = toInput.value.trim();
        const adaAmount = parseFloat(amountInput.value);

        if (!to || !adaAmount || adaAmount < 1) {
            showStatus(statusEl, 'Please enter a valid address and amount (min 1 ADA).', 'error');
            return;
        }

        const lovelace = Math.floor(adaAmount * 1_000_000);

        btn.disabled = true;
        btn.textContent = 'Building transaction...';
        showStatus(statusEl, 'Building transaction...', 'info');

        try {
            // Step 1: Build unsigned transaction.
            const buildResult = await buildTransaction(state.address, [
                { address: to, lovelace },
            ]);

            if (!buildResult.success) {
                throw new Error(buildResult.message || 'Build failed');
            }

            // Extract CBOR from response.
            const txCbor = buildResult.data?.complete || buildResult.data?.stripped || buildResult.data?.tx_cbor;
            if (!txCbor) {
                throw new Error('No transaction CBOR in build response');
            }

            btn.textContent = 'Signing...';
            showStatus(statusEl, 'Please sign the transaction in your wallet...', 'info');

            // Step 2: Sign with browser wallet.
            const witness = await signTx(txCbor, true);

            btn.textContent = 'Submitting...';
            showStatus(statusEl, 'Submitting transaction...', 'info');

            // Step 3: Submit.
            const submitResult = await submitTransaction(txCbor, [witness]);

            if (!submitResult.success) {
                throw new Error(submitResult.message || 'Submit failed');
            }

            const txHash = submitResult.data?.txHash || submitResult.data?.hash || 'submitted';
            showStatus(statusEl, `Transaction submitted! Hash: ${txHash}`, 'success');
            btn.textContent = 'Send ADA';
            btn.disabled = false;
        } catch (err) {
            console.error('[Weld for WP] Send error:', err);
            showStatus(statusEl, `Error: ${err.message}`, 'error');
            btn.textContent = 'Send ADA';
            btn.disabled = false;
        }
    });
}

function showStatus(el, message, type) {
    el.style.display = 'block';
    el.className = `weldpress-send__status weldpress-send__status--${type}`;
    el.textContent = message;
}

function escapeAttr(str) {
    return (str || '').replace(/&/g, '&amp;').replace(/"/g, '&quot;').replace(/</g, '&lt;').replace(/>/g, '&gt;');
}
