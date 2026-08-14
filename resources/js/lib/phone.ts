/**
 * Russia-only phone helpers. Mirrors App\Support\Phone on the backend.
 *
 * The canonical form is +7XXXXXXXXXX (11 digits total). For display, we
 * format it as +7 (XXX) XXX-XX-XX while typing.
 */

/** Strips everything but digits. */
export function digitsOnly(raw: string): string {
    return raw.replace(/\D+/g, '');
}

/**
 * Take any user input and return a canonical +7XXXXXXXXXX string,
 * or null if it can't be salvaged into a valid Russian number.
 */
export function normalizePhone(raw: string | null | undefined): string | null {
    if (!raw) {
return null;
}

    let digits = digitsOnly(raw);

    if (digits.length === 10) {
        digits = '7' + digits;
    } else if (digits.length === 11 && digits.startsWith('8')) {
        digits = '7' + digits.slice(1);
    }

    if (digits.length !== 11 || !digits.startsWith('7')) {
        return null;
    }

    return '+' + digits;
}

export function isValidPhone(raw: string | null | undefined): boolean {
    return normalizePhone(raw) !== null;
}

/**
 * Format whatever digits the user has typed so far into the +7 (XXX) XXX-XX-XX
 * shape. Treats the first digit as the country code (so 8XXX is rewritten as
 * 7XXX automatically while typing).
 */
export function formatPhoneMask(raw: string): string {
    let d = digitsOnly(raw);

    if (d.length === 0) {
return '';
}

    // Rewrite leading "8" to "7" so the visible mask always starts with +7.
    if (d.startsWith('8')) {
d = '7' + d.slice(1);
}

    // If user starts typing without a country code, assume Russia.
    if (!d.startsWith('7')) {
d = '7' + d;
}

    // Cap at 11 digits total (1 country + 10 national).
    d = d.slice(0, 11);

    const national = d.slice(1);
    let out = '+7';

    if (national.length === 0) {
        return out + ' ';
    }

    out += ' (' + national.slice(0, 3);

    if (national.length < 3) {
return out;
}

    out += ')';

    if (national.length === 3) {
return out;
}

    out += ' ' + national.slice(3, 6);

    if (national.length <= 6) {
return out;
}

    out += '-' + national.slice(6, 8);

    if (national.length <= 8) {
return out;
}

    out += '-' + national.slice(8, 10);

    return out;
}
