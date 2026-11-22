<?php


namespace App\Support\Formatting;

class NumberFormat
{
    // Formatea el valor para presentarlo de forma consistente.
    public static function format(int|float|string|null $value): string
    {
        if ($value === null || $value === '') {
            return '—';
        }

        $formatted = number_format((float) $value, 2, ',', '.');

        return rtrim(rtrim($formatted, '0'), ',');
    }
}
