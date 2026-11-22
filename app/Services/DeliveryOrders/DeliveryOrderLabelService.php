<?php

namespace App\Services\DeliveryOrders;

use App\Support\People\PersonName;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use LogicException;

class DeliveryOrderLabelService
{
    public function forOrder(int $orderId, ?int $detailId = null): array
    {
        $order = DB::table('ordenes_entrega')
            ->join('entregas', 'entregas.entrega_id', '=', 'ordenes_entrega.entrega_id')
            ->join('pacientes', 'pacientes.rut', '=', 'entregas.paciente_rut')
            ->leftJoin('personas_autorizadas', 'personas_autorizadas.persona_autorizada_id', '=', 'entregas.persona_autorizada_id')
            ->where('ordenes_entrega.orden_entrega_id', $orderId)
            ->first([
                'ordenes_entrega.orden_entrega_id',
                'ordenes_entrega.numero_orden',
                'entregas.entrega_id',
                'entregas.estado',
                'entregas.fecha_hora',
                'entregas.paciente_rut',
                'entregas.paciente_nombre_snapshot',
                'entregas.persona_autorizada_nombre_snapshot',
                'entregas.persona_autorizada_rut_snapshot',
                'pacientes.nombre as paciente_nombre_actual',
                'pacientes.apellidoPaterno as paciente_apellido_paterno_actual',
                'pacientes.apellidoMaterno as paciente_apellido_materno_actual',
                'personas_autorizadas.nombre as persona_autorizada_nombre_actual',
                'personas_autorizadas.apellidoPaterno as persona_autorizada_apellido_paterno_actual',
                'personas_autorizadas.apellidoMaterno as persona_autorizada_apellido_materno_actual',
                'personas_autorizadas.rut as persona_autorizada_rut_actual',
            ]);

        abort_unless($order && $order->estado === 'CONFIRMADA', 404);

        $expectedDetails = DB::table('entrega_detalle')
            ->where('entrega_id', $order->entrega_id)
            ->count();
        abort_unless($expectedDetails > 0, 404);

        $rawDetails = DB::table('entrega_detalle')
            ->join('productos', 'productos.producto_id', '=', 'entrega_detalle.producto_id')
            ->join('lotes', function ($join) {
                $join->on('lotes.lote_id', '=', 'entrega_detalle.lote_id')
                    ->on('lotes.producto_id', '=', 'entrega_detalle.producto_id');
            })
            ->leftJoin('unidades_medida', 'unidades_medida.unidad_medida_id', '=', 'productos.unidad_base_inventario_id')
            ->where('entrega_detalle.entrega_id', $order->entrega_id)
            ->orderBy('productos.nombre')
            ->orderBy('lotes.numero_lote')
            ->orderBy('entrega_detalle.entrega_detalle_id')
            ->get([
                'entrega_detalle.entrega_detalle_id',
                'entrega_detalle.producto_id',
                'entrega_detalle.lote_id',
                'entrega_detalle.cantidad',
                'entrega_detalle.dosis',
                'entrega_detalle.horario',
                'entrega_detalle.indicaciones',
                'entrega_detalle.producto_nombre_snapshot',
                'entrega_detalle.unidad_base_codigo_snapshot',
                'entrega_detalle.numero_lote_snapshot',
                'entrega_detalle.fecha_vencimiento_lote_snapshot',
                'productos.nombre as producto_actual',
                'unidades_medida.codigo as unidad_base_codigo_actual',
                'lotes.numero_lote as numero_lote_actual',
                'lotes.fecha_vencimiento as fecha_vencimiento_actual',
            ]);

        abort_unless($rawDetails->count() === $expectedDetails, 409, 'El lote no corresponde al medicamento de la dispensación.');

        $rawDetails->each(function ($detail) {
            $detail->producto = $detail->producto_nombre_snapshot ?: $detail->producto_actual;
            $detail->unidad_base_codigo = $detail->unidad_base_codigo_snapshot ?: $detail->unidad_base_codigo_actual;
            $detail->numero_lote = $detail->numero_lote_snapshot ?: $detail->numero_lote_actual;
            $detail->fecha_vencimiento = $detail->fecha_vencimiento_lote_snapshot ?: $detail->fecha_vencimiento_actual;
        });

        $target = $detailId === null
            ? null
            : $rawDetails->firstWhere('entrega_detalle_id', $detailId);
        abort_if($detailId !== null && $target === null, 404);

        $details = $rawDetails
            ->groupBy(fn (object $detail): string => $detail->producto_id.':'.$detail->lote_id)
            ->map(function (Collection $allocations) {
                $label = clone $allocations->first();
                foreach ($allocations->skip(1) as $allocation) {
                    foreach (['producto', 'unidad_base_codigo', 'numero_lote', 'fecha_vencimiento', 'dosis', 'horario', 'indicaciones'] as $field) {
                        abort_unless($allocation->{$field} === $label->{$field}, 409, 'Los datos del medicamento y lote no son consistentes.');
                    }
                }
                $label->cantidad = $allocations->sum(static fn (object $allocation): float => (float) $allocation->cantidad);

                return $label;
            })
            ->values();

        if ($target !== null) {
            $targetKey = $target->producto_id.':'.$target->lote_id;
            $details = $details
                ->filter(fn (object $detail): bool => $detail->producto_id.':'.$detail->lote_id === $targetKey)
                ->values();
        }

        $order->paciente = $order->paciente_nombre_snapshot ?: PersonName::format((object) [
            'nombre' => $order->paciente_nombre_actual,
            'apellidoPaterno' => $order->paciente_apellido_paterno_actual,
            'apellidoMaterno' => $order->paciente_apellido_materno_actual,
        ]);
        $order->persona_autorizada = $order->persona_autorizada_nombre_snapshot
            ?: PersonName::format((object) [
                'nombre' => $order->persona_autorizada_nombre_actual,
                'apellidoPaterno' => $order->persona_autorizada_apellido_paterno_actual,
                'apellidoMaterno' => $order->persona_autorizada_apellido_materno_actual,
            ]);
        $order->persona_autorizada_rut = $order->persona_autorizada_rut_snapshot
            ?: $order->persona_autorizada_rut_actual;

        return [
            'order' => $order,
            'details' => $details,
            'printSettings' => $this->printSettings(),
        ];
    }

    private function printSettings(): array
    {
        $width = $this->dimension(config('labels.width_mm'), 'LABEL_WIDTH_MM', 10, 500);
        $height = $this->dimension(config('labels.height_mm'), 'LABEL_HEIGHT_MM', 10, 500);
        $margin = $this->dimension(config('labels.margin_mm', 2), 'LABEL_MARGIN_MM', 0, 50) ?? 2;
        $minimumFontSize = $this->dimension(config('labels.font.minimum_size_pt', 8), 'LABEL_MINIMUM_FONT_SIZE_PT', 8, 20) ?? 8;
        $fontFamily = trim((string) config('labels.font.family', 'Arial, sans-serif'));
        $fontFamily = $fontFamily !== '' ? $fontFamily : 'Arial, sans-serif';

        if (! preg_match('/^[A-Za-z0-9 ,._-]+$/', $fontFamily)) {
            throw new LogicException('LABEL_FONT_FAMILY contains unsupported characters.');
        }
        if ($width !== null && $height !== null && $margin * 2 >= min($width, $height)) {
            throw new LogicException('LABEL_MARGIN_MM must be less than half the label dimensions.');
        }

        return [
            'configured' => $width !== null && $height !== null,
            'provisional' => (bool) config('labels.provisional_dimensions', false),
            'width_mm' => $width,
            'height_mm' => $height,
            'margin_mm' => $margin,
            'font_family' => $fontFamily,
            'minimum_font_size_pt' => $minimumFontSize,
            'copies' => 1,
        ];
    }

    private function dimension(mixed $value, string $environmentKey, float $minimum, float $maximum): ?float
    {
        if ($value === null || $value === '') {
            return null;
        }
        if (! is_numeric($value)) {
            throw new LogicException("{$environmentKey} must be a number.");
        }

        $dimension = (float) $value;
        if (! is_finite($dimension) || $dimension < $minimum || $dimension > $maximum) {
            throw new LogicException("{$environmentKey} must be between {$minimum} and {$maximum}.");
        }

        return $dimension;
    }
}
