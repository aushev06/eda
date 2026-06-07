<?php

namespace App\Support;

class Phone
{
    /**
     * Strip everything but digits and normalize to E.164 Russia format:
     * +7XXXXXXXXXX (12 chars total, 11 digits).
     *
     * Accepts:
     *  - 10-digit input (no country code): "9991234567" → "+79991234567"
     *  - 11-digit starting with 7: "79991234567" → "+79991234567"
     *  - 11-digit starting with 8: "89991234567" → "+79991234567"
     *
     * Returns null if the input cannot be normalized into a valid number.
     */
    public static function normalize(?string $raw): ?string
    {
        if ($raw === null) {
            return null;
        }

        $digits = preg_replace('/\D+/', '', $raw) ?? '';
        if ($digits === '') {
            return null;
        }

        if (strlen($digits) === 10) {
            $digits = '7'.$digits;
        } elseif (strlen($digits) === 11 && $digits[0] === '8') {
            $digits = '7'.substr($digits, 1);
        }

        if (strlen($digits) !== 11 || $digits[0] !== '7') {
            return null;
        }

        return '+'.$digits;
    }

    public static function isValid(?string $raw): bool
    {
        return self::normalize($raw) !== null;
    }
}
