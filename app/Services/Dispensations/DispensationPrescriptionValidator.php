<?php

namespace App\Services\Dispensations;

use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

class DispensationPrescriptionValidator
{
    public function validate(
        array $data,
        Collection $prescribedDetails,
        Collection $alreadyDispensed,
        Collection $alreadyDispensedThisMonth,
        bool $monthlyLimitApplies
    ): void {
        foreach ($data['detalles'] as $index => $detail) {
            $prescriptionDetail = $prescribedDetails->get($detail['producto_id']);
            if (! $prescriptionDetail) {
                throw ValidationException::withMessages([
                    "detalles.{$index}.producto_id" => 'El producto seleccionado no corresponde a la receta vigente.',
                ]);
            }

            $dispensedQuantities = $monthlyLimitApplies ? $alreadyDispensedThisMonth : $alreadyDispensed;
            $remaining = max(0, (float) $prescriptionDetail->cantidad_indicada - (float) ($dispensedQuantities->get($detail['producto_id']) ?? 0));
            if ((float) $detail['cantidad'] > $remaining + 0.00001) {
                throw ValidationException::withMessages([
                    "detalles.{$index}.cantidad" => $monthlyLimitApplies
                        ? 'La cantidad a entregar supera el saldo disponible para este mes: '.$remaining.'.'
                        : 'La cantidad a entregar no puede superar el saldo pendiente de la receta: '.$remaining.'.',
                ]);
            }

            if (! empty($data['transcripcion_manual'])) {
                $isSos = filter_var($detail['es_sos'] ?? false, FILTER_VALIDATE_BOOLEAN);
                if (! $isSos && empty($detail['duracion_dias'])) {
                    throw ValidationException::withMessages([
                        "detalles.{$index}.duracion_dias" => 'La duración debe coincidir con la receta vigente.',
                    ]);
                }
                if (! $isSos && empty($detail['frecuencia'])) {
                    throw ValidationException::withMessages([
                        "detalles.{$index}.frecuencia" => 'La frecuencia debe coincidir con la receta vigente.',
                    ]);
                }

                if (abs((float) $detail['cantidad_indicada'] - (float) $prescriptionDetail->cantidad_indicada) > 0.00001) {
                    throw ValidationException::withMessages([
                        "detalles.{$index}.cantidad_indicada" => 'La cantidad prescrita debe coincidir con la receta vigente.',
                    ]);
                }
                if ((bool) ($prescriptionDetail->es_sos ?? false) !== $isSos) {
                    throw ValidationException::withMessages([
                        "detalles.{$index}.es_sos" => 'La indicación SOS debe coincidir con la receta vigente.',
                    ]);
                }
            }

            $this->validatePrescriptionFields($data, $detail, $index, $prescriptionDetail);
        }
    }

    private function validatePrescriptionFields(array $data, array &$detail, int $index, object $prescriptionDetail): void
    {
        foreach ([
            'dosis' => 'dosis',
            'unidades_por_toma' => 'unidades_por_toma',
            'duracion_dias' => 'duracion_dias',
            'frecuencia' => 'frecuencia',
            'indicaciones' => 'indicaciones',
        ] as $input => $prescriptionField) {
            if ($input === 'dosis' && ! empty($data['transcripcion_manual'])) {
                continue;
            }
            if (! empty($data['transcripcion_manual']) && ! array_key_exists($input, $detail)) {
                $detail[$input] = $input === 'frecuencia'
                    && filter_var($detail['es_sos'] ?? false, FILTER_VALIDATE_BOOLEAN)
                    ? 'SOS'
                    : null;
            }
            if (! array_key_exists($input, $detail)) {
                continue;
            }

            $expected = $prescriptionDetail->{$prescriptionField} ?? null;
            if (! empty($data['transcripcion_manual']) && $expected === null) {
                continue;
            }
            $matches = in_array($input, ['unidades_por_toma', 'duracion_dias'], true)
                ? (($detail[$input] === null || $detail[$input] === '') && $expected === null)
                    || ($detail[$input] !== null && $detail[$input] !== '' && abs((float) $detail[$input] - (float) $expected) <= 0.00001)
                : trim((string) ($detail[$input] ?? '')) === trim((string) $expected);
            if (! $matches) {
                $fieldLabel = match ($input) {
                    'dosis' => 'La dosis',
                    'unidades_por_toma' => 'La dosis por toma',
                    'duracion_dias' => 'La duración',
                    'frecuencia' => 'La frecuencia',
                    default => 'Las indicaciones',
                };
                throw ValidationException::withMessages([
                    "detalles.{$index}.{$input}" => "{$fieldLabel} debe coincidir con la receta vigente.",
                ]);
            }
        }
    }
}
