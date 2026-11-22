<?php

namespace App\Services\Reports;

class ReportCsvValueSanitizer
{
    public static function spreadsheetSafe(mixed $value): mixed
    {
        if ($value === null) {
            return '';
        }

        if (! is_string($value) && ! is_numeric($value)) {
            return $value;
        }

        $safe = (string) $value;
        $safe = strip_tags($safe);
        $safe = str_replace(["\r\n", "\r", "\n", "\t"], ' ', $safe);
        $safe = preg_replace('/[\x00-\x1F\x7F]+/', ' ', $safe) ?? $safe;
        $safe = preg_replace('/\s+/u', ' ', $safe);
        $safe = trim($safe);

        if ($safe === '') {
            return '';
        }

        if (preg_match('/^(?:[=+\-@])/', $safe)) {
            return "'".$safe;
        }

        return $safe;
    }
}
