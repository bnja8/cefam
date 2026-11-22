<?php

namespace App\Actions\Carts;

use App\Models\Usuario;
use App\Rules\FourDigitDate;
use App\Support\People\PersonName;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class CartIndexAction
{
    public function index(Request $request): View|JsonResponse
    {
        $user = Auth::user();
        abort_unless($user instanceof Usuario, 401);
        /** @var Usuario $user */
        $screen = $request->route('screen', 'stock');

        $stockFilters = $screen === 'stock' ? $request->validate([
            'buscar_stock' => ['nullable', 'string', 'max:100'],
            'tipo_carro' => ['nullable', Rule::in(['PARO', 'UNIDOSIS'])],
            'estado_producto' => ['nullable', Rule::in(['activo', 'inactivo', 'todos'])],
            'vence_desde' => ['nullable', 'bail', new FourDigitDate()],
            'vence_hasta' => ['nullable', 'bail', new FourDigitDate(), 'after_or_equal:vence_desde'],
        ]) : [];
        $usageFilters = $screen === 'usage' ? $request->validate([
            'buscar_uso' => ['nullable', 'string', 'max:100'],
            'uso_desde' => ['nullable', 'bail', new FourDigitDate()],
            'uso_hasta' => ['nullable', 'bail', new FourDigitDate(), 'after_or_equal:uso_desde'],
        ]) : [];
        $preparationFilters = $screen === 'preparations' ? $request->validate([
            'buscar_preparacion' => ['nullable', 'string', 'max:100'],
            'estado_preparacion' => ['nullable', Rule::in(['PREPARANDO', 'CONFIRMADA', 'ANULADA'])],
            'preparacion_desde' => ['nullable', 'bail', new FourDigitDate()],
            'preparacion_hasta' => ['nullable', 'bail', new FourDigitDate(), 'after_or_equal:preparacion_desde'],
        ]) : [];
        $needsStockOptions = in_array($screen, ['paro-create', 'unidosis-create'], true);
        $carts = $needsStockOptions ? DB::table('carros')
            ->join('ubicaciones', 'ubicaciones.ubicacion_id', '=', 'carros.ubicacion_id')
            ->orderBy('carros.tipo_carro')
            ->get(['carros.carro_id', 'carros.tipo_carro', 'carros.nombre', 'ubicaciones.ubicacion_id', 'ubicaciones.nombre as ubicacion']) : collect();
        $stockQuery = DB::table('inventario')
            ->join('lotes', 'lotes.lote_id', '=', 'inventario.lote_id')
            ->join('productos', 'productos.producto_id', '=', 'lotes.producto_id')
            ->join('ubicaciones', 'ubicaciones.ubicacion_id', '=', 'inventario.ubicacion_id')
            ->join('carros', 'carros.ubicacion_id', '=', 'ubicaciones.ubicacion_id')
            ->where('inventario.cantidad', '>', 0)
            ->where('lotes.estado', 'ACTIVO')
            ->whereDate('lotes.fecha_vencimiento', '>=', now()->toDateString())
            ->when($stockFilters['tipo_carro'] ?? null, fn ($query, $type) => $query->where('carros.tipo_carro', $type))
            ->when($stockFilters['vence_desde'] ?? null, fn ($query, $date) => $query->whereDate('lotes.fecha_vencimiento', '>=', $date))
            ->when($stockFilters['vence_hasta'] ?? null, fn ($query, $date) => $query->whereDate('lotes.fecha_vencimiento', '<=', $date))
            ->when($stockFilters['buscar_stock'] ?? null, function ($query, string $search) {
                $term = '%'.$search.'%';
                $query->where(fn ($query) => $query->where('productos.nombre', 'like', $term)
                    ->orWhere('productos.numero_serie', 'like', $term)
                    ->orWhere('lotes.numero_lote', 'like', $term)
                    ->orWhere('carros.nombre', 'like', $term));
            })
            ->orderBy('carros.tipo_carro')
            ->orderBy('productos.nombre')
            ->orderBy('lotes.fecha_vencimiento');
        $stockColumns = [
            'carros.tipo_carro',
            'carros.nombre as carro',
            'productos.nombre as producto',
            'productos.producto_id',
            'productos.es_controlado',
            'lotes.lote_id',
            'lotes.numero_lote',
            'lotes.fecha_vencimiento',
            'inventario.cantidad',
            'productos.activo as producto_activo',
        ];
        $availableStock = $needsStockOptions
            ? (clone $stockQuery)->where('productos.activo', true)->get($stockColumns)
            : collect();
        $stock = $screen === 'stock'
            ? (clone $stockQuery)
                ->when(($stockFilters['estado_producto'] ?? 'activo') !== 'todos', fn ($query) => $query
                    ->where('productos.activo', ($stockFilters['estado_producto'] ?? 'activo') === 'activo'))
                ->paginate(15, $stockColumns, 'stock_page')
                ->withQueryString()
            : null;
        $preparations = $screen === 'preparations' ? DB::table('preparaciones_unidosis')
            ->join('tratamientos', 'tratamientos.tratamiento_id', '=', 'preparaciones_unidosis.tratamiento_id')
            ->join('pacientes', 'pacientes.rut', '=', 'tratamientos.paciente_rut')
            ->join('carros', 'carros.carro_id', '=', 'preparaciones_unidosis.carro_id')
            ->join('usuarios as preparador', 'preparador.rut', '=', 'preparaciones_unidosis.usuario_prepara_rut')
            ->leftJoin('usuarios as confirmador', 'confirmador.rut', '=', 'preparaciones_unidosis.usuario_confirma_rut')
            ->when($preparationFilters['preparacion_desde'] ?? null, fn ($query, $date) => $query->where('preparaciones_unidosis.fecha_preparacion', '>=', $date))
            ->when($preparationFilters['preparacion_hasta'] ?? null, fn ($query, $date) => $query->where('preparaciones_unidosis.fecha_preparacion', '<=', $date))
            ->when($preparationFilters['estado_preparacion'] ?? null, fn ($query, $state) => $query->where('preparaciones_unidosis.estado', $state))
            ->when($preparationFilters['buscar_preparacion'] ?? null, function ($query, string $search) {
                $term = '%'.$search.'%';
                $query->where(function ($query) use ($search, $term) {
                    $query->where('tratamientos.descripcion', 'like', $term)
                        ->orWhere('carros.nombre', 'like', $term)
                        ->orWhere('preparaciones_unidosis.periodo', 'like', $term)
                        ->orWhere(function ($nameQuery) use ($search) {
                            PersonName::whereMatches($nameQuery, 'pacientes', $search);
                        })
                        ->orWhere(function ($nameQuery) use ($search) {
                            PersonName::whereMatches($nameQuery, 'preparador', $search);
                        });
                });
            })
            ->orderByDesc('preparaciones_unidosis.fecha_preparacion')
            ->paginate(15, [
                'preparaciones_unidosis.preparacion_id',
                'preparaciones_unidosis.fecha_preparacion',
                'preparaciones_unidosis.periodo',
                'preparaciones_unidosis.estado',
                'tratamientos.descripcion as tratamiento',
                PersonName::expression('pacientes', 'paciente'),
                'carros.nombre as carro',
                PersonName::expression('preparador', 'preparador'),
                PersonName::expression('confirmador', 'confirmador'),
            ], 'preparaciones_page')
            ->withQueryString() : null;
        $paroUsage = $screen === 'usage' ? DB::table('movimientos_inventario')
            ->join('lotes', 'lotes.lote_id', '=', 'movimientos_inventario.lote_id')
            ->join('productos', 'productos.producto_id', '=', 'lotes.producto_id')
            ->join('ubicaciones', 'ubicaciones.ubicacion_id', '=', 'movimientos_inventario.ubicacion_id')
            ->join('carros', 'carros.ubicacion_id', '=', 'ubicaciones.ubicacion_id')
            ->join('usuarios', 'usuarios.rut', '=', 'movimientos_inventario.usuario_rut')
            ->where('carros.tipo_carro', 'PARO')
            ->where('movimientos_inventario.referencia_tipo', 'CARRO_PARO')
            ->when($usageFilters['uso_desde'] ?? null, fn ($query, $date) => $query->whereDate('movimientos_inventario.fecha_hora', '>=', $date))
            ->when($usageFilters['uso_hasta'] ?? null, fn ($query, $date) => $query->whereDate('movimientos_inventario.fecha_hora', '<=', $date))
            ->when($usageFilters['buscar_uso'] ?? null, function ($query, string $search) {
                $term = '%'.$search.'%';
                $query->where(fn ($query) => $query->where('productos.nombre', 'like', $term)
                    ->orWhere('productos.numero_serie', 'like', $term)
                    ->orWhere('lotes.numero_lote', 'like', $term)
                    ->orWhere('movimientos_inventario.observacion', 'like', $term)
                    ->orWhere('carros.nombre', 'like', $term)
                    ->orWhere(function ($nameQuery) use ($search) {
                        PersonName::whereMatches($nameQuery, 'usuarios', $search);
                    }));
            })
            ->orderByDesc('movimientos_inventario.fecha_hora')
            ->paginate(15, [
                'movimientos_inventario.movimiento_id',
                'movimientos_inventario.fecha_hora',
                'movimientos_inventario.cantidad',
                'movimientos_inventario.observacion',
                'productos.nombre as producto',
                'productos.numero_serie as codigo',
                'lotes.numero_lote',
                'carros.nombre as carro',
                PersonName::expression('usuarios', 'responsable'),
            ], 'uso_page')
            ->withQueryString() : null;
        $preparationIds = $preparations ? $preparations->getCollection()->pluck('preparacion_id')->filter()->values()->all() : [];
        $labels = $screen === 'preparations' ? DB::table('rotulos_unidosis')
            ->join('preparacion_unidosis_detalle', 'preparacion_unidosis_detalle.preparacion_detalle_id', '=', 'rotulos_unidosis.preparacion_detalle_id')
            ->join('preparaciones_unidosis', 'preparaciones_unidosis.preparacion_id', '=', 'preparacion_unidosis_detalle.preparacion_id')
            ->when($preparationIds !== [], fn ($query) => $query->whereIn('preparaciones_unidosis.preparacion_id', $preparationIds))
            ->orderByDesc('rotulos_unidosis.fecha_generacion')
            ->get([
                'preparaciones_unidosis.preparacion_id',
                'rotulos_unidosis.identificador',
                'rotulos_unidosis.contenido_generado',
                'rotulos_unidosis.fecha_generacion',
            ])
            ->groupBy('preparacion_id') : collect();
        if ($preparations) {
            $preparations->getCollection()->each(function ($preparation) use ($labels) {
                $preparation->labels = $labels->get($preparation->preparacion_id, collect());
            });
        }

        $data = [
            'screen' => $screen,
            'carts' => $carts,
            'stock' => $stock,
            'stockFilters' => $stockFilters,
            'usageFilters' => $usageFilters,
            'preparationFilters' => $preparationFilters,
            'paroUsage' => $paroUsage,
            'preparations' => $preparations,
            'activePatientCount' => $screen === 'treatment-create' ? DB::table('pacientes')->where('activo', true)->count() : 0,
            'selectedPatient' => $screen === 'treatment-create' ? DB::table('pacientes')
                ->where('rut', old('paciente_rut'))
                ->where('activo', true)
                ->first(['rut', PersonName::expression('pacientes', 'nombre_completo')]) : null,
            'unidosisProducts' => $availableStock->where('tipo_carro', 'UNIDOSIS')->where('es_controlado', false)->unique('producto_id')->values(),
            'paroLots' => $availableStock->where('tipo_carro', 'PARO')->where('es_controlado', false)->values(),
            'treatments' => $screen === 'unidosis-create' ? DB::table('tratamientos')->join('pacientes', 'pacientes.rut', '=', 'tratamientos.paciente_rut')
                ->where('tratamientos.activo', true)
                ->where('pacientes.activo', true)
                ->where(function ($query) {
                    $query->whereNull('tratamientos.fecha_termino')
                        ->orWhereDate('tratamientos.fecha_termino', '>=', now()->toDateString());
                })
                ->orderBy('pacientes.apellidoPaterno')->orderBy('pacientes.apellidoMaterno')->orderBy('pacientes.nombre')
                ->get(['tratamientos.tratamiento_id', 'tratamientos.descripcion', PersonName::expression('pacientes', 'paciente')]) : collect(),
            'unidosisCart' => $carts->firstWhere('tipo_carro', 'UNIDOSIS'),
            'paroCart' => $carts->firstWhere('tipo_carro', 'PARO'),
            'isPharmacist' => $user->hasAnyRole(['Químico Farmacéutico']),
        ];

        return ($request->routeIs('api.v1.*') || $request->expectsJson())
            ? response()->json(['data' => $data])
            : view('carts.index', $data);
    }
}
