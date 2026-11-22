<?php


namespace App\Support\People;

class Rut
{
    // Limpia el RUT y conserva sus caracteres válidos.
    public static function limpiarRut(string $rut): string
    {
        return strtoupper((string) preg_replace('/[\s.-]+/u', '', trim($rut)));
    }

    // Valida el RUT mediante su dígito verificador.
    public static function validarRut(string $rut): bool
    {
        $cleanRut = self::limpiarRut($rut);
        if (! preg_match('/^\d{7,8}[0-9K]$/', $cleanRut)) {
            return false;
        }

        $body = substr($cleanRut, 0, -1);
        $expectedVerifier = substr($cleanRut, -1);
        $sum = 0;
        $weight = 2;

        for ($index = strlen($body) - 1; $index >= 0; $index--) {
            $sum += (int) $body[$index] * $weight;
            $weight = $weight === 7 ? 2 : $weight + 1;
        }

        $modulo = $sum % 11;
        $verifier = match ($modulo) {
            0 => '0',
            1 => 'K',
            default => (string) (11 - $modulo),
        };

        return $verifier === $expectedVerifier;
    }
}
