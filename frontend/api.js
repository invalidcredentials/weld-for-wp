/**
 * WP REST API client for WeldPress endpoints.
 */

function getConfig() {
    return window.weldpressConfig || {};
}

/**
 * Make an authenticated request to the WeldPress REST API.
 */
async function request(endpoint, options = {}) {
    const config = getConfig();
    const url = (config.restUrl || '/wp-json/weldpress/v1/') + endpoint;

    const headers = {
        'Content-Type': 'application/json',
        'X-WP-Nonce': config.nonce || '',
    };

    const response = await fetch(url, {
        ...options,
        headers: {
            ...headers,
            ...(options.headers || {}),
        },
    });

    const data = await response.json();

    if (!response.ok) {
        const message = data?.message || `Request failed with status ${response.status}`;
        throw new Error(message);
    }

    return data;
}

/**
 * Fetch plugin configuration.
 */
export async function fetchConfig() {
    return request('config');
}

/**
 * Build an unsigned transaction via the Anvil API.
 *
 * @param {string} changeAddress - Bech32 change address.
 * @param {Array<{address: string, lovelace: number}>} outputs - Transaction outputs.
 * @returns {Promise<{success: boolean, data: object}>}
 */
export async function buildTransaction(changeAddress, outputs) {
    return request('tx/build', {
        method: 'POST',
        body: JSON.stringify({
            change_address: changeAddress,
            outputs,
        }),
    });
}

/**
 * Submit a signed transaction via the Anvil API.
 *
 * @param {string} transaction - Signed transaction CBOR hex.
 * @param {string[]} witnesses - Witness set hex strings.
 * @returns {Promise<{success: boolean, data: object}>}
 */
export async function submitTransaction(transaction, witnesses = []) {
    return request('tx/submit', {
        method: 'POST',
        body: JSON.stringify({
            transaction,
            witnesses,
        }),
    });
}
