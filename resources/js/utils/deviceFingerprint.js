/**
 * Device Fingerprint Utility
 *
 * Generates a consistent SHA-256 fingerprint from stable browser properties.
 * Used to identify trusted devices for MFA bypass on recognized browsers.
 *
 * This is NOT a tracking fingerprint — it's a lightweight hash of
 * browser + OS + screen characteristics that changes when the user
 * switches devices or browsers.
 */

/**
 * Collect stable browser properties and hash them into a fingerprint.
 * @returns {Promise<string>} A hex-encoded SHA-256 fingerprint string.
 */
export async function generateDeviceFingerprint() {
    const components = [
        navigator.userAgent || '',
        navigator.language || '',
        `${screen.width}x${screen.height}`,
        String(screen.colorDepth || ''),
        Intl.DateTimeFormat().resolvedOptions().timeZone || '',
        String(navigator.hardwareConcurrency || ''),
        navigator.platform || '',
    ];

    const raw = components.join('|||');

    // Use the native Web Crypto API for SHA-256 hashing when available (secure contexts)
    if (typeof crypto !== 'undefined' && crypto.subtle) {
        const encoder = new TextEncoder();
        const data = encoder.encode(raw);
        const hashBuffer = await crypto.subtle.digest('SHA-256', data);

        // Convert ArrayBuffer to hex string
        const hashArray = Array.from(new Uint8Array(hashBuffer));
        return hashArray.map(b => b.toString(16).padStart(2, '0')).join('');
    }

    // Fallback hash for non-HTTPS local network origins where crypto.subtle is unavailable
    let h1 = 0xdeadbeef;
    let h2 = 0x41c6ce57;
    for (let i = 0; i < raw.length; i++) {
        const ch = raw.charCodeAt(i);
        h1 = Math.imul(h1 ^ ch, 2654435761);
        h2 = Math.imul(h2 ^ ch, 1597334677);
    }
    h1 = Math.imul(h1 ^ (h1 >>> 16), 2246822507) ^ Math.imul(h2 ^ (h2 >>> 13), 3266489909);
    h2 = Math.imul(h2 ^ (h2 >>> 16), 2246822507) ^ Math.imul(h1 ^ (h1 >>> 13), 3266489909);
    return (h2 >>> 0).toString(16).padStart(8, '0') + (h1 >>> 0).toString(16).padStart(8, '0');
}
