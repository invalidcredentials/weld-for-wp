/**
 * CIP-30 wallet extension detection.
 */

// Known Cardano wallet extension keys and metadata.
const KNOWN_WALLETS = {
    nami: { name: 'Nami', id: 'nami' },
    eternl: { name: 'Eternl', id: 'eternl' },
    lace: { name: 'Lace', id: 'lace' },
    flint: { name: 'Flint', id: 'flint' },
    typhoncip30: { name: 'Typhon', id: 'typhoncip30' },
    gerowallet: { name: 'GeroWallet', id: 'gerowallet' },
    nufi: { name: 'NuFi', id: 'nufi' },
    begin: { name: 'Begin', id: 'begin' },
    vespr: { name: 'VESPR', id: 'vespr' },
    yoroi: { name: 'Yoroi', id: 'yoroi' },
};

/**
 * Detect CIP-30 wallet extensions installed in the browser.
 *
 * @returns {Array<{key: string, name: string, icon: string|null, apiVersion: string|null}>}
 */
export function getWalletExtensions() {
    const cardano = window.cardano;
    if (!cardano) return [];

    const wallets = [];

    for (const key of Object.keys(cardano)) {
        const provider = cardano[key];

        // CIP-30 providers must have an `enable` method.
        if (!provider || typeof provider.enable !== 'function') {
            continue;
        }

        // Skip non-wallet properties.
        if (key === 'enable' || key === '_events') {
            continue;
        }

        const known = KNOWN_WALLETS[key];

        wallets.push({
            key,
            name: provider.name || (known ? known.name : key),
            icon: provider.icon || null,
            apiVersion: provider.apiVersion || null,
        });
    }

    return wallets;
}
