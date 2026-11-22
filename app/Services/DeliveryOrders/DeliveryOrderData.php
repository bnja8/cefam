<?php

namespace App\Services\DeliveryOrders;

use App\Support\People\PersonName;
use Illuminate\Support\Facades\DB;

class DeliveryOrderData
{
    public function forOrder(int $orderId): array
    {
        $order = DB::table('ordenes_entrega')
            ->join('entregas', 'entregas.entrega_id', '=', 'ordenes_entrega.entrega_id')
            ->join('pacientes', 'pacientes.rut', '=', 'entregas.paciente_rut')
            ->join('usuarios', 'usuarios.rut', '=', 'entregas.usuario_rut')
            ->leftJoin('personas_autorizadas', 'personas_autorizadas.persona_autorizada_id', '=', 'entregas.persona_autorizada_id')
            ->leftJoin('recetas', 'recetas.receta_id', '=', 'entregas.receta_id')
            ->where('ordenes_entrega.orden_entrega_id', $orderId)
            ->first([
                'ordenes_entrega.orden_entrega_id',
                'ordenes_entrega.numero_orden',
                'ordenes_entrega.fecha_generacion',
                'ordenes_entrega.plantilla',
                'entregas.entrega_id',
                'entregas.receta_id',
                'entregas.fecha_hora',
                'entregas.observacion',
                'entregas.paciente_nombre_snapshot',
                'entregas.persona_autorizada_nombre_snapshot',
                'entregas.persona_autorizada_rut_snapshot',
                'pacientes.rut',
                PersonName::expression('pacientes', 'paciente'),
                PersonName::expression('usuarios', 'responsable'),
                'personas_autorizadas.rut as persona_autorizada_rut',
                PersonName::expression('personas_autorizadas', 'persona_autorizada'),
                'recetas.numero_receta',
            ]);

        abort_unless($order, 404);
        $order->paciente = $order->paciente_nombre_snapshot ?: $order->paciente;
        $order->persona_autorizada = $order->persona_autorizada_nombre_snapshot ?: $order->persona_autorizada;
        $order->persona_autorizada_rut = $order->persona_autorizada_rut_snapshot ?: $order->persona_autorizada_rut;

        $totalDispensedByProduct = DB::table('entrega_detalle')
            ->join('entregas', 'entregas.entrega_id', '=', 'entrega_detalle.entrega_id')
            ->where('entregas.estado', 'CONFIRMADA')
            ->select('entregas.receta_id', 'entrega_detalle.producto_id')
            ->selectRaw('SUM(entrega_detalle.cantidad) as cantidad_dispensada')
            ->groupBy('entregas.receta_id', 'entrega_detalle.producto_id');
        $details = DB::table('entrega_detalle')
            ->join('productos', 'productos.producto_id', '=', 'entrega_detalle.producto_id')
            ->join('lotes', 'lotes.lote_id', '=', 'entrega_detalle.lote_id')
            ->leftJoin('unidades_medida', 'unidades_medida.unidad_medida_id', '=', 'productos.unidad_base_inventario_id')
            ->leftJoin('receta_detalle', function ($join) use ($order) {
                $join->on('receta_detalle.producto_id', '=', 'entrega_detalle.producto_id')
                    ->where('receta_detalle.receta_id', '=', $order->receta_id);
            })
            ->leftJoinSub($totalDispensedByProduct, 'total_dispensado', function ($join) use ($order) {
                $join->on('total_dispensado.producto_id', '=', 'entrega_detalle.producto_id')
                    ->where('total_dispensado.receta_id', '=', $order->receta_id);
            })
            ->where('entrega_detalle.entrega_id', $order->entrega_id)
            ->orderBy('productos.nombre')
            ->orderBy('lotes.numero_lote')
            ->orderBy('entrega_detalle.entrega_detalle_id')
            ->get([
                'entrega_detalle.entrega_detalle_id',
                'entrega_detalle.producto_id',
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
                'receta_detalle.cantidad_indicada as cantidad_prescrita',
                DB::raw('COALESCE(total_dispensado.cantidad_dispensada, 0) as cantidad_dispensada_total'),
            ]);
        $details->each(function ($detail) {
            $detail->producto = $detail->producto_nombre_snapshot ?: $detail->producto_actual;
            $detail->unidad_base_codigo = $detail->unidad_base_codigo_snapshot ?: $detail->unidad_base_codigo_actual;
            $detail->numero_lote = $detail->numero_lote_snapshot ?: $detail->numero_lote_actual;
            $detail->fecha_vencimiento = $detail->fecha_vencimiento_lote_snapshot ?: $detail->fecha_vencimiento_actual;
            $detail->cantidad_pendiente = $detail->cantidad_prescrita === null
                ? null
                : max(0, (float) $detail->cantidad_prescrita - (float) $detail->cantidad_dispensada_total);
        });

        return compact('order', 'details');
    }
}
