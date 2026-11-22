<?php

namespace App\Services\Reports;

use App\Support\People\PersonName;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class ReportQueryService
{
    public function deliveries(array $filters)
    {
        return DB::table('entrega_detalle')
            ->join('entregas', 'entregas.entrega_id', '=', 'entrega_detalle.entrega_id')
            ->join('pacientes', 'pacientes.rut', '=', 'entregas.paciente_rut')
            ->join('productos', 'productos.producto_id', '=', 'entrega_detalle.producto_id')
            ->leftJoin('unidades_medida as base_units', 'base_units.unidad_medida_id', '=', 'productos.unidad_base_inventario_id')
            ->join('lotes', 'lotes.lote_id', '=', 'entrega_detalle.lote_id')
            ->join('usuarios', 'usuarios.rut', '=', 'entregas.usuario_rut')
            ->where('entregas.estado', 'CONFIRMADA')
            ->when(isset($filters['desde']), fn ($query) => $query->where('entregas.fecha_hora', '>=', $filters['desde']))
            ->when(isset($filters['hasta']), fn ($query) => $query->where('entregas.fecha_hora', '<', Carbon::parse($filters['hasta'])->addDay()->toDateString()))
            ->when($filters['buscar_entrega'] ?? null, function ($query, string $search) {
                $term = '%'.$search.'%';
                $query->where(function ($query) use ($term, $search) {
                    $query->where('productos.nombre', 'like', $term)
                        ->orWhere('productos.numero_serie', 'like', $term)
                        ->orWhere('lotes.numero_lote', 'like', $term)
                        ->orWhere('entregas.entrega_id', 'like', $term)
                        ->orWhere(function ($nameQuery) use ($search) {
                            PersonName::whereMatches($nameQuery, 'pacientes', $search);
                        });
                });
            })
            ->orderBy('entrega_detalle.entrega_detalle_id')
            ->select([
                'entregas.fecha_hora',
                'entregas.entrega_id',
                PersonName::expression('pacientes', 'paciente'),
                'productos.nombre as producto',
                'productos.numero_serie as codigo_producto',
                'lotes.numero_lote',
                'entrega_detalle.cantidad',
                'base_units.codigo as unidad_base_codigo',
                PersonName::expression('usuarios', 'responsable'),
            ]);
    }

    public function inventory()
    {
        return DB::table('inventario')
            ->join('lotes', 'lotes.lote_id', '=', 'inventario.lote_id')
            ->join('productos', 'productos.producto_id', '=', 'lotes.producto_id')
            ->join('categorias_producto', 'categorias_producto.categoria_id', '=', 'productos.categoria_id')
            ->leftJoin('unidades_medida as base_units', 'base_units.unidad_medida_id', '=', 'productos.unidad_base_inventario_id')
            ->join('ubicaciones', 'ubicaciones.ubicacion_id', '=', 'inventario.ubicacion_id')
            ->leftJoin('stock_minimos_ubicacion', function ($join) {
                $join->on('stock_minimos_ubicacion.producto_id', '=', 'lotes.producto_id')
                    ->on('stock_minimos_ubicacion.ubicacion_id', '=', 'inventario.ubicacion_id');
            })
            ->where('inventario.cantidad', '>', 0)
            ->orderBy('inventario.inventario_id')
            ->select([
                'inventario.inventario_id',
                'productos.nombre as producto',
                'productos.numero_serie as codigo_producto',
                'categorias_producto.nombre as categoria',
                'base_units.codigo as unidad_base_codigo',
                'lotes.numero_lote',
                'lotes.fecha_vencimiento',
                'lotes.estado',
                'ubicaciones.nombre as ubicacion',
                'inventario.cantidad',
                'stock_minimos_ubicacion.stock_minimo',
            ]);
    }

    public function expiring()
    {
        return DB::table('inventario')
            ->join('lotes', 'lotes.lote_id', '=', 'inventario.lote_id')
            ->join('productos', 'productos.producto_id', '=', 'lotes.producto_id')
            ->leftJoin('unidades_medida as base_units', 'base_units.unidad_medida_id', '=', 'productos.unidad_base_inventario_id')
            ->join('ubicaciones', 'ubicaciones.ubicacion_id', '=', 'inventario.ubicacion_id')
            ->where('productos.activo', true)
            ->where('lotes.estado', 'ACTIVO')
            ->where('inventario.cantidad', '>', 0)
            ->whereDate('lotes.fecha_vencimiento', '<=', now()->addDays(30)->toDateString())
            ->orderBy('lotes.fecha_vencimiento')
            ->orderBy('inventario.inventario_id')
            ->select([
                'inventario.inventario_id',
                'productos.nombre as producto',
                'productos.numero_serie as codigo_producto',
                'base_units.codigo as unidad_base_codigo',
                'lotes.numero_lote',
                'lotes.fecha_vencimiento',
                'ubicaciones.nombre as ubicacion',
                'inventario.cantidad',
            ]);
    }

    public function movements(array $filters)
    {
        return DB::table('movimientos_inventario')
            ->join('lotes', 'lotes.lote_id', '=', 'movimientos_inventario.lote_id')
            ->join('productos', 'productos.producto_id', '=', 'lotes.producto_id')
            ->join('ubicaciones', 'ubicaciones.ubicacion_id', '=', 'movimientos_inventario.ubicacion_id')
            ->join('usuarios', 'usuarios.rut', '=', 'movimientos_inventario.usuario_rut')
            ->when(isset($filters['desde']), fn ($query) => $query->whereDate('movimientos_inventario.fecha_hora', '>=', $filters['desde']))
            ->when(isset($filters['hasta']), fn ($query) => $query->whereDate('movimientos_inventario.fecha_hora', '<=', $filters['hasta']))
            ->when($filters['tipo_movimiento'] ?? null, fn ($query, $type) => $query->where('movimientos_inventario.tipo_movimiento', $type))
            ->when($filters['buscar_movimiento'] ?? null, function ($query, string $search) {
                $term = '%'.$search.'%';
                $query->where(function ($query) use ($search, $term) {
                    $query->where('productos.nombre', 'like', $term)
                        ->orWhere('productos.numero_serie', 'like', $term)
                        ->orWhere('lotes.numero_lote', 'like', $term)
                        ->orWhere('movimientos_inventario.tipo_movimiento', 'like', $term)
                        ->orWhere('ubicaciones.nombre', 'like', $term)
                        ->orWhere('movimientos_inventario.referencia_tipo', 'like', $term)
                        ->orWhere('movimientos_inventario.observacion', 'like', $term)
                        ->orWhere(function ($nameQuery) use ($search) {
                            PersonName::whereMatches($nameQuery, 'usuarios', $search);
                        });
                });
            })
            ->orderByDesc('movimientos_inventario.fecha_hora')
            ->orderByDesc('movimientos_inventario.movimiento_id')
            ->select([
                'movimientos_inventario.fecha_hora',
                'productos.nombre as producto',
                'productos.numero_serie as codigo_producto',
                'lotes.numero_lote',
                'movimientos_inventario.tipo_movimiento',
                'movimientos_inventario.cantidad',
                'movimientos_inventario.unidad_base_codigo',
                'movimientos_inventario.cantidad_original',
                'movimientos_inventario.unidad_ingresada',
                'movimientos_inventario.factor_conversion',
                'movimientos_inventario.cantidad_envases',
                'movimientos_inventario.unidades_sueltas',
                'movimientos_inventario.presentacion_nombre',
                'ubicaciones.nombre as ubicacion',
                PersonName::expression('usuarios', 'responsable'),
                'movimientos_inventario.referencia_tipo',
                'movimientos_inventario.referencia_id',
                'movimientos_inventario.observacion',
            ]);
    }

    public function controlledMovements(array $filters)
    {
        return DB::table('movimientos_controlados')
            ->join('productos', 'productos.producto_id', '=', 'movimientos_controlados.producto_id')
            ->leftJoin('unidades_medida as base_units', 'base_units.unidad_medida_id', '=', 'productos.unidad_base_inventario_id')
            ->join('lotes', 'lotes.lote_id', '=', 'movimientos_controlados.lote_id')
            ->join('usuarios as responsible', 'responsible.rut', '=', 'movimientos_controlados.usuario_responsable_rut')
            ->leftJoin('usuarios as authorizer', 'authorizer.rut', '=', 'movimientos_controlados.usuario_autoriza_rut')
            ->when(isset($filters['desde']), fn ($query) => $query->where('movimientos_controlados.fecha_hora', '>=', $filters['desde']))
            ->when(isset($filters['hasta']), fn ($query) => $query->where('movimientos_controlados.fecha_hora', '<', Carbon::parse($filters['hasta'])->addDay()->toDateString()))
            ->orderByDesc('movimientos_controlados.fecha_hora')->orderByDesc('movimientos_controlados.movimiento_controlado_id')
            ->select([
                'movimientos_controlados.fecha_hora', 'productos.nombre as producto', 'productos.numero_serie as codigo_producto',
                'lotes.numero_lote', 'movimientos_controlados.tipo_movimiento', 'movimientos_controlados.cantidad',
                'base_units.codigo as unidad_base_codigo',
                PersonName::expression('responsible', 'responsable'), PersonName::expression('authorizer', 'autorizador'),
                'movimientos_controlados.firmado', 'movimientos_controlados.referencia_tipo', 'movimientos_controlados.referencia_id',
                'movimientos_controlados.observacion',
            ]);
    }

    public function kardex(array $filters)
    {
        return DB::table('kardex_inventario')
            ->join('lotes', 'lotes.lote_id', '=', 'kardex_inventario.lote_id')
            ->join('productos', 'productos.producto_id', '=', 'lotes.producto_id')
            ->join('ubicaciones', 'ubicaciones.ubicacion_id', '=', 'kardex_inventario.ubicacion_id')
            ->leftJoin('usuarios', 'usuarios.rut', '=', 'kardex_inventario.usuario_rut')
            ->when(isset($filters['desde']), fn ($query) => $query->whereDate('kardex_inventario.fecha_hora', '>=', $filters['desde']))
            ->when(isset($filters['hasta']), fn ($query) => $query->whereDate('kardex_inventario.fecha_hora', '<=', $filters['hasta']))
            ->when($filters['buscar_kardex'] ?? null, function ($query, string $search) {
                $term = '%'.$search.'%';
                $query->where(function ($searchQuery) use ($term) {
                    $searchQuery->where('productos.nombre', 'like', $term)
                        ->orWhere('productos.numero_serie', 'like', $term)
                        ->orWhere('lotes.numero_lote', 'like', $term)
                        ->orWhere('ubicaciones.nombre', 'like', $term)
                        ->orWhere('kardex_inventario.documento_recepcion', 'like', $term)
                        ->orWhere('kardex_inventario.motivo', 'like', $term);
                });
            })
            ->orderByDesc('kardex_inventario.fecha_hora')
            ->orderByDesc('kardex_inventario.kardex_id')
            ->select([
                'kardex_inventario.kardex_id', 'kardex_inventario.fecha_hora', 'kardex_inventario.cantidad_final',
                'kardex_inventario.motivo', 'kardex_inventario.referencia_tipo', 'kardex_inventario.referencia_id',
                'kardex_inventario.documento_recepcion', 'kardex_inventario.observacion',
                'productos.nombre as producto', 'productos.numero_serie as codigo_producto', 'lotes.numero_lote',
                'ubicaciones.nombre as ubicacion', PersonName::expression('usuarios', 'responsable'),
            ]);
    }
}
