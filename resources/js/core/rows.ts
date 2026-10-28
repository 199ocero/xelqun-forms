/**
 * Stable keys for repeater rows. crypto.randomUUID() needs a secure context,
 * so fall back to getRandomValues() on plain-http dev domains.
 */
export function newRowKey(): string {
    const cryptoApi = globalThis.crypto;

    if (typeof cryptoApi?.randomUUID === 'function' && globalThis.isSecureContext !== false) {
        return cryptoApi.randomUUID();
    }

    const bytes = new Uint8Array(16);

    if (cryptoApi?.getRandomValues) {
        cryptoApi.getRandomValues(bytes);
    } else {
        bytes.forEach((_, index) => (bytes[index] = Math.floor(Math.random() * 256)));
    }

    bytes[6] = (bytes[6] & 0x0f) | 0x40;
    bytes[8] = (bytes[8] & 0x3f) | 0x80;

    const hex = [...bytes].map((byte) => byte.toString(16).padStart(2, '0')).join('');

    return `${hex.slice(0, 8)}-${hex.slice(8, 12)}-${hex.slice(12, 16)}-${hex.slice(16, 20)}-${hex.slice(20)}`;
}
