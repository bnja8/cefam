<?php

namespace App\Services\Dispensations;

use App\Services\Inventory\FefoInventoryAllocator;
use App\Services\Inventory\InventoryKardexService;
use App\Services\Alerts\StockAlertService;
use App\Support\Inventory\InventoryQuantitySnapshot;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class DispensationProductWriter
{
    public function __construct(
        private readonly FefoInventoryAllocator $allocator,
        private readonly InventoryKardexService $kardex,
        private readonly StockAlertService $alerts
    ) {}

    public function write(
        array $detail,
        int $index,
        int $deliveryId,
        object $patient,
        object $recipe,
        Collection $prescribedDetails,
        Collection $alreadyDispensed,
        Collection $alreadyDispensedThisMonth,
        bool $monthlyLimitApplies,
        array $locationIds,
        ?string $authorizerRut,
        string $userRut,
        string $today
    ): array {
        $product = DB::table('productos')->where('producto_id', $detail['producto_id'])
            ->where('activo', true)->lockForUpdate()->first();
        if (! $product) {
            throw ValidationException::withMessages(["detalles.{$index}.producto_id" => 'El producto no está disponible.']);
        }
        $prescriptionDetail = $prescribedDetails->get($product->producto_id);
        if (! $prescriptionDetail) {
            throw ValidationException::withMessages(["detalles.{$index}.producto_id" => 'La receta no contiene el producto seleccionado.']);
        }

        $unitBaseCode = $product->unidad_base_inventario_id === null
            ? null
            : DB::table('unidades_medida')
                ->where('unidad_medida_id', $product->unidad_base_inventario_id)
                ->value('codigo');
        $dispensedQuantities = $monthlyLimitApplies ? $alreadyDispensedThisMonth : $alreadyDispensed;
        $remaining = max(0, (float) $prescriptionDetail->cantidad_indicada - (float) ($dispensedQuantities->get($product->producto_id) ?? 0));
        if ((float) $detail['cantidad'] > $remaining + 0.00001) {
            throw ValidationException::withMessages([
                "detalles.{$index}.cantidad" => $monthlyLimitApplies
                    ? 'La cantidad a entregar supera el saldo disponible para este mes: '.$remaining.'.'
                    : 'La cantidad solicitada supera lo que queda indicado en la receta.',
            ]);
        }

        $quota = DB::table('cuotas_paciente')
            ->join('paciente_programa', 'paciente_programa.paciente_programa_id', '=', 'cuotas_paciente.paciente_programa_id')
            ->join('programas_salud', 'programas_salud.programa_id', '=', 'paciente_programa.programa_id')
            ->where('paciente_programa.paciente_rut', $patient->rut)
            ->where('paciente_programa.activo', true)
            ->where('programas_salud.activo', true)
            ->whereDate('paciente_programa.fecha_inicio', '<=', $today)
            ->where(function ($query) use ($today) {
                $query->whereNull('paciente_programa.fecha_termino')->orWhereDate('paciente_programa.fecha_termino', '>=', $today);
            })
            ->where('cuotas_paciente.producto_id', $product->producto_id)
            ->whereDate('cuotas_paciente.periodo_inicio', '<=', $today)
            ->whereDate('cuotas_paciente.periodo_termino', '>=', $today)
            ->orderBy('cuotas_paciente.cuota_id')
            ->lockForUpdate()
            ->get(['cuotas_paciente.cuota_id', 'cuotas_paciente.cantidad_autorizada', 'cuotas_paciente.cantidad_utilizada']);
        $quotaRow = $quota->first(fn ($row) => (float) $row->cantidad_autorizada - (float) $row->cantidad_utilizada >= (float) $detail['cantidad']);
        if ($quota->isNotEmpty() && ! $quotaRow) {
            throw ValidationException::withMessages(["detalles.{$index}.cantidad" => 'La cantidad solicitada supera la cuota disponible del paciente.']);
        }

        $allocations = $this->allocator->consume(
            $product->producto_id,
            $locationIds,
            (float) $detail['cantidad'],
            $today,
            'Stock insuficiente para la dispensación.',
            "detalles.{$index}.cantidad"
        );
        foreach ($allocations as $lot) {
            DB::table('entrega_detalle')->insert([
                'entrega_id' => $deliveryId,
                'lote_id' => $lot->lote_id,
                'producto_id' => $product->producto_id,
                'cantidad' => $lot->cantidad_asignada,
                'producto_nombre_snapshot' => $product->nombre,
                'unidad_base_codigo_snapshot' => $unitBaseCode,
                'numero_lote_snapshot' => $lot->numero_lote,
                'fecha_vencimiento_lote_snapshot' => $lot->fecha_vencimiento,
                'cuota_id' => $quotaRow->cuota_id ?? null,
                'dosis' => $prescriptionDetail->dosis,
                'horario' => $prescriptionDetail->frecuencia,
                'indicaciones' => $prescriptionDetail->indicaciones,
            ]);
            DB::table('movimientos_inventario')->insert([
                'lote_id' => $lot->lote_id,
                'ubicacion_id' => $lot->ubicacion_id,
                'tipo_movimiento' => 'SALIDA',
                'cantidad' => $lot->cantidad_asignada,
                ...InventoryQuantitySnapshot::forBaseQuantity((int) $product->producto_id, (float) $lot->cantidad_asignada),
                'usuario_rut' => $userRut,
                'referencia_tipo' => 'ENTREGA',
                'referencia_id' => $deliveryId,
                'observacion' => 'Dispensación a paciente '.$patient->rut,
            ]);
            if ((float) $lot->cantidad - (float) $lot->cantidad_asignada <= 0.00001) {
                $this->kardex->archiveEmptyInventoryIfAnotherLotHasStock(
                    (int) $lot->inventario_id,
                    $userRut,
                    'ENTREGA',
                    $deliveryId,
                    null,
                    'DISPENSACION_LOTE_AGOTADO',
                    'Fila de inventario archivada automáticamente al agotarse el lote durante una dispensación.'
                );
            }
            if ($product->es_controlado) {
                DB::table('movimientos_controlados')->insert([
                    'producto_id' => $product->producto_id,
                    'lote_id' => $lot->lote_id,
                    'tipo_movimiento' => 'SALIDA',
                    'cantidad' => $lot->cantidad_asignada,
                    'usuario_responsable_rut' => $userRut,
                    'usuario_autoriza_rut' => $authorizerRut,
                    'firma_autorizacion' => 'Validación de identidad mediante credenciales del Químico Farmacéutico; sin firma digital certificada.',
                    'referencia_tipo' => 'ENTREGA',
                    'referencia_id' => $deliveryId,
                    'observacion' => 'Dispensación de receta '.$recipe->numero_receta.' a paciente '.$patient->rut,
                    'firmado' => true,
                ]);
            }
            $this->alerts->check($lot->lote_id, $lot->ubicacion_id);
        }
        if ($quotaRow) {
            DB::table('cuotas_paciente')->where('cuota_id', $quotaRow->cuota_id)->increment('cantidad_utilizada', $detail['cantidad']);
        }
        $this->alerts->checkForReplenishment($product->producto_id, $userRut);
        foreach ($allocations->pluck('ubicacion_id')->unique() as $allocationLocationId) {
            $this->alerts->checkForReplenishment($product->producto_id, $userRut, (int) $allocationLocationId);
        }

        $alreadyDispensed->put($product->producto_id, (float) ($alreadyDispensed->get($product->producto_id) ?? 0) + (float) $detail['cantidad']);
        if ($monthlyLimitApplies) {
            $alreadyDispensedThisMonth->put(
                $product->producto_id,
                (float) ($alreadyDispensedThisMonth->get($product->producto_id) ?? 0) + (float) $detail['cantidad']
            );
        }

        return [
            'producto_id' => $product->producto_id,
            'cantidad' => $detail['cantidad'],
            'lotes' => $allocations->pluck('lote_id')->all(),
        ];
    }
}
