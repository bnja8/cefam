<?php

namespace App\Services\Alerts;

use App\Support\People\PersonName;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class AlertQueryService
{
    public function alerts(array $filters, bool $isTechnician, ?string $userRut)
    {
        $query = DB::table('alertas')
            ->leftJoin('lotes', 'lotes.lote_id', '=', 'alertas.lote_id')
            ->leftJoin('productos as stock_product', 'stock_product.producto_id', '=', 'alertas.producto_id')
            ->leftJoin('productos as lot_product', 'lot_product.producto_id', '=', 'lotes.producto_id')
            ->leftJoin('ubicaciones', 'ubicaciones.ubicacion_id', '=', 'alertas.ubicacion_id')
            ->leftJoin('stock_minimos_ubicacion', function ($join) {
                $join->on('stock_minimos_ubicacion.producto_id', '=', 'alertas.producto_id')
                    ->on('stock_minimos_ubicacion.ubicacion_id', '=', 'alertas.ubicacion_id');
            })
            ->leftJoin('usuarios', 'usuarios.rut', '=', 'alertas.atendida_por_rut')
            ->leftJoin('usuarios as responsable', 'responsable.rut', '=', 'alertas.responsable_rut')
            ->whereNull('alertas.resuelta_at')
            ->when($isTechnician, fn ($query) => $query->where('alertas.responsable_rut', $userRut))
            ->select([
                'alertas.alerta_id',
                'alertas.tipo_alerta',
                'alertas.prioridad',
                'alertas.estado',
                'alertas.responsable_rut',
                'alertas.reconocida_en',
                'alertas.detectada_en',
                'alertas.resuelta_at',
                DB::raw('COALESCE(alertas.producto_id, lotes.producto_id) as producto_id'),
                'alertas.lote_id',
                'alertas.mensaje',
                'alertas.nivel_actual',
                'alertas.fecha_generacion',
                'alertas.atendida',
                'alertas.atendida_at',
                'stock_minimos_ubicacion.stock_minimo',
                DB::raw('COALESCE(stock_product.nombre, lot_product.nombre) as producto'),
                'lotes.numero_lote',
                'lotes.fecha_vencimiento',
                'ubicaciones.nombre as ubicacion',
                PersonName::expression('usuarios', 'atendida_por'),
                PersonName::expression('responsable', 'responsable'),
            ]);

        return $query
            ->orderByRaw('CASE WHEN alertas.resuelta_at IS NULL THEN 0 ELSE 1 END')
            ->orderByRaw("CASE alertas.tipo_alerta WHEN 'STOCK_CRITICO' THEN 0 WHEN 'VENCIMIENTO' THEN 1 ELSE 2 END")
            ->orderByRaw('CASE WHEN alertas.atendida = 1 THEN 1 ELSE 0 END')
            ->orderByDesc('alertas.fecha_generacion')
            ->orderByDesc('alertas.alerta_id')
            ->when($filters['buscar_alerta'] ?? null, function ($query, string $search) {
                $term = '%'.$search.'%';
                $query->where(function ($query) use ($term) {
                    $query->where('stock_product.nombre', 'like', $term)
                        ->orWhere('lot_product.nombre', 'like', $term)
                        ->orWhere('lotes.numero_lote', 'like', $term);
                });
            })
            ->when($filters['prioridad'] ?? null, function ($query, string $priority) {
                if ($priority === 'CRITICA') {
                    $query->whereIn('alertas.tipo_alerta', ['SIN_EXISTENCIAS', 'VENCIDO']);
                } elseif ($priority === 'VENCIDO') {
                    $query->where('alertas.tipo_alerta', 'VENCIMIENTO')
                        ->whereDate('lotes.fecha_vencimiento', '<', now()->toDateString());
                } elseif ($priority === 'VENCIMIENTO') {
                    $query->where('alertas.tipo_alerta', 'VENCIMIENTO')
                        ->where(function ($query) {
                            $query->whereNull('lotes.fecha_vencimiento')
                                ->orWhereDate('lotes.fecha_vencimiento', '>=', now()->toDateString());
                        });
                } elseif ($priority === 'ALTA') {
                    $query->where('alertas.tipo_alerta', 'STOCK_CRITICO');
                } elseif ($priority === 'MEDIA') {
                    $query->where('alertas.tipo_alerta', 'STOCK_BAJO');
                } else {
                    $query->where('alertas.tipo_alerta', 'DESCUADRE_CONTROLADO');
                }
            })
            ->when($filters['tipo_alerta'] ?? null, fn ($query, $type) => $query->where('alertas.tipo_alerta', $type))
            ->when(($filters['estado'] ?? null) === 'pendientes', fn ($query) => $query->whereNull('alertas.resuelta_at'))
            ->when(($filters['estado'] ?? null) === 'atendidas', fn ($query) => $query
                ->where(fn ($query) => $query->whereNotNull('alertas.resuelta_at')->orWhere('alertas.atendida', true)))
            ->when($filters['ubicacion_id'] ?? null, fn ($query, $locationId) => $query->where('alertas.ubicacion_id', $locationId))
            ->when($filters['fecha_desde'] ?? null, fn ($query, $date) => $query->where('alertas.fecha_generacion', '>=', $date))
            ->when($filters['fecha_hasta'] ?? null, fn ($query, $date) => $query->where('alertas.fecha_generacion', '<', Carbon::parse($date)->addDay()->toDateString()))
            ->when($filters['responsable_rut'] ?? null, fn ($query, $rut) => $query->where('alertas.responsable_rut', $rut))
            ->when($filters['estado_operativo'] ?? null, fn ($query, $state) => $query->where('alertas.estado', $state))
            ->paginate(25)
            ->withQueryString();
    }

    public function pendingCount(bool $isTechnician, ?string $userRut): int
    {
        return (int) DB::table('alertas')
            ->whereNull('resuelta_at')
            ->when($isTechnician, fn ($query) => $query->where('responsable_rut', $userRut))
            ->count();
    }

    public function history()
    {
        return DB::table('historial_alertas')
            ->join('alertas', 'alertas.alerta_id', '=', 'historial_alertas.alerta_id')
            ->leftJoin('lotes', 'lotes.lote_id', '=', 'alertas.lote_id')
            ->leftJoin('productos as stock_product', 'stock_product.producto_id', '=', 'alertas.producto_id')
            ->leftJoin('productos as lot_product', 'lot_product.producto_id', '=', 'lotes.producto_id')
            ->leftJoin('usuarios as actor', 'actor.rut', '=', 'historial_alertas.usuario_rut')
            ->leftJoin('usuarios as previous_responsible', 'previous_responsible.rut', '=', 'historial_alertas.responsable_anterior_rut')
            ->leftJoin('usuarios as new_responsible', 'new_responsible.rut', '=', 'historial_alertas.responsable_nuevo_rut')
            ->leftJoin('usuarios as current_responsible', 'current_responsible.rut', '=', 'alertas.responsable_rut')
            ->select([
                'historial_alertas.historial_id',
                'historial_alertas.alerta_id',
                'historial_alertas.usuario_rut',
                'historial_alertas.tipo_evento',
                'historial_alertas.estado_anterior',
                'historial_alertas.estado_nuevo',
                'historial_alertas.responsable_anterior_rut',
                'historial_alertas.responsable_nuevo_rut',
                'historial_alertas.descripcion',
                'historial_alertas.created_at',
                'alertas.responsable_rut as responsable_actual_rut',
                DB::raw('COALESCE(stock_product.nombre, lot_product.nombre) as producto'),
                PersonName::expression('actor', 'usuario'),
                PersonName::expression('previous_responsible', 'responsable_anterior'),
                PersonName::expression('new_responsible', 'responsable_nuevo'),
                PersonName::expression('current_responsible', 'responsable_actual'),
            ])
            ->orderByDesc('historial_alertas.created_at')
            ->orderByDesc('historial_alertas.historial_id')
            ->paginate(20)
            ->withQueryString();
    }

    public function alert(int $alertId)
    {
        return DB::table('alertas')
            ->leftJoin('lotes', 'lotes.lote_id', '=', 'alertas.lote_id')
            ->leftJoin('productos as stock_product', 'stock_product.producto_id', '=', 'alertas.producto_id')
            ->leftJoin('productos as lot_product', 'lot_product.producto_id', '=', 'lotes.producto_id')
            ->leftJoin('ubicaciones', 'ubicaciones.ubicacion_id', '=', 'alertas.ubicacion_id')
            ->leftJoin('stock_minimos_ubicacion', function ($join) {
                $join->on('stock_minimos_ubicacion.producto_id', '=', 'alertas.producto_id')
                    ->on('stock_minimos_ubicacion.ubicacion_id', '=', 'alertas.ubicacion_id');
            })
            ->leftJoin('usuarios as responsable', 'responsable.rut', '=', 'alertas.responsable_rut')
            ->where('alertas.alerta_id', $alertId)
            ->first([
                'alertas.alerta_id',
                'alertas.tipo_alerta',
                'alertas.prioridad',
                'alertas.estado',
                'alertas.responsable_rut',
                'alertas.reconocida_en',
                'alertas.resuelta_at',
                'alertas.mensaje',
                'alertas.nivel_actual',
                'alertas.fecha_generacion',
                'alertas.detectada_en',
                'alertas.lote_id',
                DB::raw('COALESCE(alertas.producto_id, lotes.producto_id) as producto_id'),
                DB::raw('COALESCE(stock_product.nombre, lot_product.nombre) as producto'),
                'lotes.numero_lote',
                'lotes.fecha_vencimiento',
                'ubicaciones.nombre as ubicacion',
                'stock_minimos_ubicacion.stock_minimo',
                PersonName::expression('responsable', 'responsable'),
            ]);
    }

    public function responsibles(array $roles)
    {
        return DB::table('usuarios')
            ->join('usuario_rol', 'usuario_rol.usuario_rut', '=', 'usuarios.rut')
            ->join('roles', 'roles.rol_id', '=', 'usuario_rol.rol_id')
            ->whereIn('roles.nombre', $roles)
            ->where('usuarios.activo', true)
            ->distinct()
            ->orderBy('usuarios.apellidoPaterno')
            ->get(['usuarios.rut', 'usuarios.nombre', 'usuarios.apellidoPaterno', 'usuarios.apellidoMaterno']);
    }
}
