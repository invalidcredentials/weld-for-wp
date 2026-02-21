/**
 * Weld for WP — Admin wallet dashboard JS.
 * Handles balance display, token grid, send ADA, copy, archive/restore.
 */
(function () {
    'use strict';

    var nonce = typeof weldpressAdmin !== 'undefined' ? weldpressAdmin.nonce : '';
    var ajax  = typeof weldpressAdmin !== 'undefined' ? weldpressAdmin.ajaxurl : (typeof ajaxurl !== 'undefined' ? ajaxurl : '');

    // ── Helpers ──────────────────────────────────────────

    function $(sel, ctx) { return (ctx || document).querySelector(sel); }
    function $$(sel, ctx) { return Array.prototype.slice.call((ctx || document).querySelectorAll(sel)); }

    function post(action, data, callback) {
        var fd = new FormData();
        fd.append('action', action);
        fd.append('nonce', nonce);
        if (data) {
            Object.keys(data).forEach(function (k) { fd.append(k, data[k]); });
        }
        fetch(ajax, { method: 'POST', body: fd })
            .then(function (r) { return r.json(); })
            .then(callback)
            .catch(function (e) { callback({ success: false, data: { message: e.message } }); });
    }

    function copyText(text, btn) {
        if (navigator.clipboard && window.isSecureContext) {
            navigator.clipboard.writeText(text).then(function () {
                flashButton(btn, 'Copied!');
            });
        } else {
            // Fallback for non-HTTPS (Local dev).
            var ta = document.createElement('textarea');
            ta.value = text;
            ta.style.cssText = 'position:fixed;left:-9999px';
            document.body.appendChild(ta);
            ta.select();
            document.execCommand('copy');
            document.body.removeChild(ta);
            flashButton(btn, 'Copied!');
        }
    }

    function flashButton(btn, text) {
        var orig = btn.textContent;
        btn.textContent = text;
        btn.classList.add('wfwp-copied');
        setTimeout(function () {
            btn.textContent = orig;
            btn.classList.remove('wfwp-copied');
        }, 1500);
    }

    function slideToggle(el) {
        if (el.classList.contains('wfwp-open')) {
            el.style.maxHeight = '0';
            el.classList.remove('wfwp-open');
        } else {
            el.style.maxHeight = el.scrollHeight + 'px';
            el.classList.add('wfwp-open');
        }
    }

    function formatAda(lovelace) {
        return (lovelace / 1000000).toFixed(6);
    }

    function truncate(str, front, back) {
        front = front || 12;
        back = back || 8;
        if (str.length <= front + back + 3) return str;
        return str.substring(0, front) + '...' + str.substring(str.length - back);
    }

    function isUrl(str) {
        return /^https?:\/\//i.test(str);
    }

    function escHtml(str) {
        var div = document.createElement('div');
        div.textContent = str;
        return div.innerHTML;
    }

    // ── Balance ─────────────────────────────────────────

    function fetchBalance() {
        var balanceEl = $('#wfwp-balance');
        var assetsEl  = $('#wfwp-assets-container');
        if (!balanceEl) return;

        balanceEl.innerHTML = '<span class="wfwp-loading"><span class="spinner is-active" style="float:none;margin:0 8px 0 0;"></span>Loading balance...</span>';
        if (assetsEl) assetsEl.innerHTML = '';

        post('weldpress_get_wallet_balance', {}, function (r) {
            if (!r.success) {
                var msg = r.data && r.data.message ? r.data.message : 'Unable to fetch balance.';
                var isNoKey = r.data && r.data.code === 'weldpress_no_blockfrost_key';
                if (isNoKey) {
                    balanceEl.innerHTML = '<div class="wfwp-balance-error wfwp-no-key">' +
                        '<span class="dashicons dashicons-warning"></span> Blockfrost API key not configured. ' +
                        '<a href="' + escHtml(weldpressAdmin.settingsUrl) + '">Add it in Settings</a></div>';
                } else {
                    balanceEl.innerHTML = '<div class="wfwp-balance-error">' + escHtml(msg) +
                        ' <a href="#" id="wfwp-retry-balance">Retry</a></div>';
                    var retry = $('#wfwp-retry-balance');
                    if (retry) retry.addEventListener('click', function (e) { e.preventDefault(); fetchBalance(); });
                }
                return;
            }

            var ada = r.data.ada;
            var assets = r.data.assets || [];

            balanceEl.innerHTML =
                '<div class="wfwp-balance-amount">' +
                    '<span class="wfwp-ada-value">' + escHtml(ada) + '</span>' +
                    '<span class="wfwp-ada-label">ADA</span>' +
                    '<button type="button" class="button button-small wfwp-refresh-btn" title="Refresh balance">' +
                        '<span class="dashicons dashicons-update"></span>' +
                    '</button>' +
                '</div>';

            var refreshBtn = $('.wfwp-refresh-btn', balanceEl);
            if (refreshBtn) refreshBtn.addEventListener('click', function () { fetchBalance(); });

            if (assets.length > 0 && assetsEl) {
                renderAssets(assetsEl, assets);
            }
        });
    }

    // ── Token / NFT Grid ────────────────────────────────

    function renderAssets(container, assets) {
        var header = '<div class="wfwp-assets-header">' +
            '<span class="wfwp-asset-count">' + assets.length + ' token' + (assets.length !== 1 ? 's' : '') + ' / NFTs</span>' +
            ' <a href="#" class="wfwp-toggle-assets">Show</a>' +
            '</div>';

        var grid = '<div class="wfwp-assets-grid wfwp-slide">';
        assets.forEach(function (a, i) {
            var name = a.asset_name || truncate(a.unit, 12, 8);
            var policyShort = a.policy_id ? truncate(a.policy_id, 12, 0) : '';
            var imgHtml = a.image
                ? '<img src="' + escHtml(a.image) + '" alt="" class="wfwp-asset-thumb" onerror="this.style.display=\'none\';this.nextElementSibling.style.display=\'flex\'">' +
                  '<div class="wfwp-asset-thumb-fallback" style="display:none">NFT</div>'
                : '<div class="wfwp-asset-thumb-fallback">NFT</div>';
            var qtyBadge = a.quantity > 1 ? '<span class="wfwp-qty-badge">x' + a.quantity + '</span>' : '';

            grid += '<div class="wfwp-asset-tile" data-index="' + i + '">' +
                '<div class="wfwp-asset-thumb-wrap">' + imgHtml + qtyBadge + '</div>' +
                '<div class="wfwp-asset-name" title="' + escHtml(name) + '">' + escHtml(name) + '</div>' +
                '<div class="wfwp-asset-policy">' + escHtml(policyShort) + '</div>' +
                '</div>';
        });
        grid += '</div>';

        var detail = '<div id="wfwp-asset-detail" class="wfwp-asset-detail wfwp-slide"></div>';

        container.innerHTML = header + grid + detail;

        // Toggle show/hide.
        var toggleLink = $('.wfwp-toggle-assets', container);
        var gridEl = $('.wfwp-assets-grid', container);
        if (toggleLink && gridEl) {
            toggleLink.addEventListener('click', function (e) {
                e.preventDefault();
                slideToggle(gridEl);
                this.textContent = gridEl.classList.contains('wfwp-open') ? 'Hide' : 'Show';
            });
        }

        // Tile click → expand detail.
        $$('.wfwp-asset-tile', container).forEach(function (tile) {
            tile.addEventListener('click', function () {
                var idx = parseInt(this.dataset.index, 10);
                showAssetDetail(assets[idx], container);

                // Highlight active tile.
                $$('.wfwp-asset-tile', container).forEach(function (t) { t.classList.remove('wfwp-active'); });
                this.classList.add('wfwp-active');
            });
        });
    }

    function showAssetDetail(asset, container) {
        var detailEl = $('#wfwp-asset-detail', container);
        if (!detailEl) return;

        var name = asset.asset_name || 'Unknown';
        var imgHtml = asset.image
            ? '<img src="' + escHtml(asset.image) + '" alt="" class="wfwp-detail-img" onerror="this.style.display=\'none\'">'
            : '';

        var metaRows = '';
        if (asset.fingerprint) {
            metaRows += '<tr><td class="wfwp-meta-key">Fingerprint</td><td class="wfwp-meta-val"><code>' + escHtml(asset.fingerprint) + '</code></td></tr>';
        }
        metaRows += '<tr><td class="wfwp-meta-key">Policy ID</td><td class="wfwp-meta-val"><code>' + escHtml(asset.policy_id) + '</code></td></tr>';
        if (asset.asset_name_hex) {
            metaRows += '<tr><td class="wfwp-meta-key">Asset Name (hex)</td><td class="wfwp-meta-val"><code>' + escHtml(asset.asset_name_hex) + '</code></td></tr>';
        }
        metaRows += '<tr><td class="wfwp-meta-key">Quantity Held</td><td class="wfwp-meta-val">' + asset.quantity + '</td></tr>';
        if (asset.mint_quantity) {
            metaRows += '<tr><td class="wfwp-meta-key">Total Minted</td><td class="wfwp-meta-val">' + asset.mint_quantity + '</td></tr>';
        }

        // CIP-25 on-chain metadata.
        if (asset.metadata && typeof asset.metadata === 'object') {
            Object.keys(asset.metadata).forEach(function (key) {
                var val = asset.metadata[key];
                var valHtml;
                if (isUrl(val)) {
                    valHtml = '<a href="' + escHtml(val) + '" target="_blank" rel="noopener">' + escHtml(truncate(val, 40, 10)) + '</a>';
                } else if (val.length > 80) {
                    valHtml = '<div class="wfwp-meta-long">' + escHtml(val) + '</div>';
                } else {
                    valHtml = escHtml(val);
                }
                metaRows += '<tr><td class="wfwp-meta-key">' + escHtml(key) + '</td><td class="wfwp-meta-val">' + valHtml + '</td></tr>';
            });
        }

        detailEl.innerHTML =
            '<div class="wfwp-detail-inner">' +
                '<div class="wfwp-detail-header">' +
                    '<a href="#" class="wfwp-detail-close">&times;</a>' +
                    (imgHtml ? '<div class="wfwp-detail-img-wrap">' + imgHtml + '</div>' : '') +
                    '<div class="wfwp-detail-title">' +
                        '<h4>' + escHtml(name) + '</h4>' +
                    '</div>' +
                '</div>' +
                '<table class="wfwp-meta-table">' + metaRows + '</table>' +
            '</div>';

        if (!detailEl.classList.contains('wfwp-open')) {
            slideToggle(detailEl);
        } else {
            detailEl.style.maxHeight = detailEl.scrollHeight + 'px';
        }

        var closeBtn = $('.wfwp-detail-close', detailEl);
        if (closeBtn) {
            closeBtn.addEventListener('click', function (e) {
                e.preventDefault();
                slideToggle(detailEl);
                $$('.wfwp-asset-tile', container).forEach(function (t) { t.classList.remove('wfwp-active'); });
            });
        }
    }

    // ── Copy Buttons ────────────────────────────────────

    function initCopyButtons() {
        $$('.wfwp-copy-btn').forEach(function (btn) {
            btn.addEventListener('click', function (e) {
                e.preventDefault();
                var text = this.dataset.copy || '';
                copyText(text, this);
            });
        });
    }

    // ── Send ADA ────────────────────────────────────────

    function initSendForm() {
        var toggle = $('#wfwp-send-toggle');
        var form   = $('#wfwp-send-form');
        var cancel = $('#wfwp-send-cancel');
        var btn    = $('#wfwp-send-confirm');
        var status = $('#wfwp-send-status');

        if (!toggle || !form) return;

        toggle.addEventListener('click', function () { slideToggle(form); });
        if (cancel) cancel.addEventListener('click', function (e) {
            e.preventDefault();
            if (form.classList.contains('wfwp-open')) slideToggle(form);
        });

        if (!btn) return;

        btn.addEventListener('click', function () {
            var recipient = ($('#wfwp-send-recipient') || {}).value || '';
            var amount    = parseFloat(($('#wfwp-send-amount') || {}).value || 0);
            recipient = recipient.trim();

            if (!recipient || !amount || amount < 1) {
                showStatus(status, 'error', 'Enter a valid address and amount (min 1 ADA).');
                return;
            }

            // Confirmation dialog.
            var truncAddr = truncate(recipient, 16, 8);
            if (!confirm('Send ' + amount + ' ADA to ' + truncAddr + '?')) return;

            btn.disabled = true;
            btn.textContent = 'Sending...';
            showStatus(status, 'info', 'Building and signing transaction...');

            post('weldpress_send_ada', {
                recipient_address: recipient,
                ada_amount: amount
            }, function (r) {
                btn.disabled = false;
                btn.textContent = 'Send';
                if (r.success) {
                    showStatus(status, 'success',
                        r.data.message + ' <a href="' + escHtml(r.data.explorer_url) + '" target="_blank">View on explorer</a>');
                    // Refresh balance after 3s.
                    setTimeout(fetchBalance, 3000);
                } else {
                    showStatus(status, 'error', r.data.message || 'Send failed.');
                }
            });
        });
    }

    function showStatus(el, type, html) {
        if (!el) return;
        el.className = 'wfwp-send-status wfwp-status-' + type;
        el.innerHTML = html;
        el.style.display = 'block';
    }

    // ── Archive / Restore / Delete ──────────────────────

    function initArchive() {
        var archiveBtn = $('#wfwp-archive-wallet');
        if (archiveBtn) {
            archiveBtn.addEventListener('click', function () {
                if (!confirm('Archive this wallet? You can restore it later.')) return;
                post('weldpress_archive_wallet', { wallet_id: this.dataset.id }, function () {
                    location.reload();
                });
            });
        }
    }

    function initArchivedList() {
        var toggle = $('#wfwp-archived-toggle');
        var list   = $('#wfwp-archived-list');
        var chevron = toggle ? $('.dashicons', toggle) : null;

        if (toggle && list) {
            toggle.addEventListener('click', function () {
                slideToggle(list);
                if (chevron) {
                    chevron.classList.toggle('dashicons-arrow-down-alt2');
                    chevron.classList.toggle('dashicons-arrow-up-alt2');
                }
            });
        }

        $$('.wfwp-unarchive').forEach(function (btn) {
            btn.addEventListener('click', function () {
                post('weldpress_unarchive_wallet', { wallet_id: this.dataset.id }, function (r) {
                    if (r.success) location.reload();
                    else alert(r.data.message || 'Failed to restore wallet.');
                });
            });
        });

        $$('.wfwp-delete-archived').forEach(function (link) {
            link.addEventListener('click', function (e) {
                e.preventDefault();
                var name = this.dataset.name || 'this wallet';
                if (!confirm('Permanently delete "' + name + '"? This cannot be undone.')) return;
                post('weldpress_delete_archived_wallet', { wallet_id: this.dataset.id }, function () {
                    location.reload();
                });
            });
        });
    }

    // ── Init ────────────────────────────────────────────

    document.addEventListener('DOMContentLoaded', function () {
        if (!$('.wfwp-wallet-tab')) return;

        initCopyButtons();
        initSendForm();
        initArchive();
        initArchivedList();

        // Fetch balance if we have an active wallet.
        if ($('#wfwp-balance')) {
            fetchBalance();
        }
    });

})();
