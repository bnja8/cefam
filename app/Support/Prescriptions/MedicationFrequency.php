<?php


namespace App\Support\Prescriptions;

class MedicationFrequency
{
    // Convierte el valor en una etiqueta legible.
    public static function label(?string $frequency): string
    {
        $frequency = trim((string) $frequency);
        if ($frequency === '') {
            return '—';
        }
        if (strcasecmp($frequency, 'SOS') === 0) {
            return 'SOS';
        }

        if (preg_match('/^(?:cada\s*)?(\d+(?:[.,]\d+)?)\s*(?:h|horas?)?$/iu', $frequency, $matches)) {
            return 'Cada '.str_replace(',', '.', $matches[1]).' horas';
        }

        return preg_match('/^cada\b/iu', $frequency) ? $frequency : 'Cada '.$frequency;
    }
}
