<?php

namespace App\Support;

/**
 * Ghana-focused mobile normalization / validation for student SMS flows.
 */
final class StudentPhone
{
    /**
     * Normalize to digits-only international form starting with 233 when possible.
     */
    public static function normalize(?string $input): ?string
    {
        if ($input === null) {
            return null;
        }

        $digits = preg_replace('/\D+/', '', trim($input)) ?? '';

        if ($digits === '') {
            return null;
        }

        if (str_starts_with($digits, '233') && strlen($digits) >= 12) {
            return $digits;
        }

        if (str_starts_with($digits, '0') && strlen($digits) === 10) {
            return '233'.substr($digits, 1);
        }

        if (strlen($digits) === 9) {
            return '233'.$digits;
        }

        return $digits;
    }

    public static function isGhanaMobile(?string $normalized): bool
    {
        if ($normalized === null || $normalized === '') {
            return false;
        }

        if (! str_starts_with($normalized, '233')) {
            return false;
        }

        $rest = substr($normalized, 3);

        return (bool) preg_match('/^[235][0-9]{8}$/', $rest);
    }
}
