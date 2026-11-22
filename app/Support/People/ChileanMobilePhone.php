<?php

namespace App\Support\People;

class ChileanMobilePhone
{
    public static function normalize(mixed $value): mixed
    {
        if ($value === null || (is_string($value) && trim($value) === '')) {
            return null;
        }

        if (! is_string($value)) {
            return $value;
        }

        $digits = preg_replace('/[\s().-]/u', '', trim($value));
        if (! is_string($digits)) {
            return $value;
        }

        if (preg_match('/^\+?569(\d{8})$/', $digits, $matches)
            || preg_match('/^9(\d{8})$/', $digits, $matches)
            || preg_match('/^(\d{8})$/', $digits, $matches)) {
            return '+56 9 '.$matches[1];
        }

        return $value;
    }

    public static function inputValue(mixed $value): string
    {
        $normalized = self::normalize($value);
        if (! is_string($normalized)) {
            return '';
        }

        if (preg_match('/^\+56 9 (\d{8})$/', $normalized, $matches)) {
            return $matches[1];
        }

        return preg_replace('/\D/', '', $normalized) ?? '';
    }
}
