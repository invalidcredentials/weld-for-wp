/**
 * Bech32 encoding for Cardano addresses.
 * Converts CIP-30 hex addresses to human-readable addr/addr_test format.
 */

const CHARSET = 'qpzry9x8gf2tvdw0s3jn54khce6mua7l';
const GENERATOR = [0x3b6a57b2, 0x26508e6d, 0x1ea119fa, 0x3d4233dd, 0x2a1462b3];

function polymod(values) {
    let chk = 1;
    for (const v of values) {
        const top = chk >> 25;
        chk = ((chk & 0x1ffffff) << 5) ^ v;
        for (let i = 0; i < 5; i++) {
            if ((top >> i) & 1) {
                chk ^= GENERATOR[i];
            }
        }
    }
    return chk;
}

function hrpExpand(hrp) {
    const result = [];
    for (let i = 0; i < hrp.length; i++) {
        result.push(hrp.charCodeAt(i) >> 5);
    }
    result.push(0);
    for (let i = 0; i < hrp.length; i++) {
        result.push(hrp.charCodeAt(i) & 31);
    }
    return result;
}

function createChecksum(hrp, data) {
    const values = hrpExpand(hrp).concat(data).concat([0, 0, 0, 0, 0, 0]);
    const mod = polymod(values) ^ 1;
    const result = [];
    for (let p = 0; p < 6; p++) {
        result.push((mod >> (5 * (5 - p))) & 31);
    }
    return result;
}

/**
 * Convert 8-bit byte array to 5-bit groups for bech32.
 */
function convertBits(data, fromBits, toBits, pad) {
    let acc = 0;
    let bits = 0;
    const result = [];
    const maxv = (1 << toBits) - 1;

    for (const value of data) {
        if (value < 0 || value >> fromBits !== 0) {
            return null;
        }
        acc = (acc << fromBits) | value;
        bits += fromBits;
        while (bits >= toBits) {
            bits -= toBits;
            result.push((acc >> bits) & maxv);
        }
    }

    if (pad) {
        if (bits > 0) {
            result.push((acc << (toBits - bits)) & maxv);
        }
    } else if (bits >= fromBits || ((acc << (toBits - bits)) & maxv)) {
        return null;
    }

    return result;
}

/**
 * Encode data as bech32.
 */
function bech32Encode(hrp, data5bit) {
    const checksum = createChecksum(hrp, data5bit);
    const combined = data5bit.concat(checksum);
    let result = hrp + '1';
    for (const d of combined) {
        result += CHARSET.charAt(d);
    }
    return result;
}

/**
 * Convert a hex string to a byte array.
 */
function hexToBytes(hex) {
    const bytes = [];
    for (let i = 0; i < hex.length; i += 2) {
        bytes.push(parseInt(hex.substring(i, i + 2), 16));
    }
    return bytes;
}

/**
 * Convert a CIP-30 hex address to bech32 (addr1.../addr_test1...).
 *
 * @param {string} hexAddress - Raw hex address from CIP-30 API.
 * @returns {string} Bech32-encoded address.
 */
export function hexAddressToBech32(hexAddress) {
    if (!hexAddress || typeof hexAddress !== 'string') {
        return hexAddress;
    }

    // Already bech32? Return as-is.
    if (hexAddress.startsWith('addr')) {
        return hexAddress;
    }

    // Must be valid hex.
    if (!/^[0-9a-fA-F]+$/.test(hexAddress)) {
        return hexAddress;
    }

    const bytes = hexToBytes(hexAddress);
    if (bytes.length === 0) {
        return hexAddress;
    }

    // Determine network from header byte.
    // Bits 0-3 of first byte = network id.
    // 0 = testnet, 1 = mainnet.
    const headerByte = bytes[0];
    const networkId = headerByte & 0x0f;
    const hrp = networkId === 1 ? 'addr' : 'addr_test';

    // For stake/reward addresses (header type 0xe or 0xf), use different prefix.
    const addressType = (headerByte >> 4) & 0x0f;
    let prefix = hrp;
    if (addressType === 0x0e || addressType === 0x0f) {
        prefix = networkId === 1 ? 'stake' : 'stake_test';
    }

    // Convert bytes to 5-bit groups.
    const data5bit = convertBits(bytes, 8, 5, true);
    if (!data5bit) {
        return hexAddress;
    }

    return bech32Encode(prefix, data5bit);
}
