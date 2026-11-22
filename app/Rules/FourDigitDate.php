<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class FourDigitDate implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value)
            || preg_match('/\A(\d{4})-(\d{2})-(\d{2})\z/', $value, $parts) !== 1
            || ! checkdate((int) $parts[2], (int) $parts[3], (int) $parts[1])) {
            $fail('Ingresa una fecha válida con día y mes de dos dígitos y año de cuatro dígitos.');
        }
    }
}
