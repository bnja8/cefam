<?php

namespace App\Actions\Supplies;

use App\Models\Usuario;
use App\Rules\FourDigitDate;
use App\Services\Audit\AuditLogger;
use App\Support\People\PersonName;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class SupplyIndexAction
{
    public function index(Request $request, AuditLogger $audit): View|JsonResponse
    {
        $screen = $request->route('screen', 'shipments');
        if ($screen === 'request-create' || $screen === 'receive') {
            $this->authorizeSupplyOperation(
                $audit,
                $screen === 'receive' ? 'supplies.receive' : 'supplies.request',
                $screen === 'receive' ? 'remesas' : 'solicitudes_reposicion'
            );
        }

        if ($screen === 'request-create') {
            if ($request->expectsJson()) {
                return response()->json(['productos' => $this->receiptProductOptions()]);
            }

            return view('supplies.index', [
                'screen' => 'request-create',
                'products' => $this->receiptProductOptions(),
            ]);
        }

        if ($screen === 'receive') {
            if ($request->expectsJson()) {
                return response()->json([
                    'productos' => $this->receiptProductOptions(),
                    'ubicaciones' => DB::table('ubicaciones')->orderBy('nombre')->get(['ubicacion_id', 'nombre']),
                    'solicitudes' => DB::table('solicitudes_reposicion')
                        ->whereIn('estado', ['PENDIENTE', 'SOLICITADA'])
                        ->orderByDesc('fecha_solicitud')
                        ->get(['solicitud_id', 'estado']),
                ]);
            }

            return view('supplies.index', [
                'screen' => 'receive',
                'products' => $this->receiptProductOptions(),
                'locations' => DB::table('ubicaciones')->orderBy('nombre')->get(['ubicacion_id', 'nombre']),
                'requests' => DB::table('solicitudes_reposicion')->whereIn('estado', ['PENDIENTE', 'SOLICITADA'])->orderByDesc('fecha_solicitud')->get(['solicitud_id', 'estado']),
            ]);
        }

        if ($screen === 'requests') {
            $requestFilters = $request->validate([
                'buscar_solicitud' => ['nullable', 'string', 'max:100'],
                'desde_solicitud' => ['nullable', 'bail', new FourDigitDate()],
                'hasta_solicitud' => ['nullable', 'bail', new FourDigitDate(), 'after_or_equal:desde_solicitud'],
                'estado_solicitud_lista' => ['nullable', Rule::in(['PENDIENTE', 'SOLICITADA', 'RECIBIDA', 'CANCELADA'])],
            ]);
            $detailSummary = DB::table('solicitud_reposicion_detalle')
                ->groupBy('solicitud_id')
                ->select('solicitud_id', DB::raw('COUNT(*) as lineas'), DB::raw('SUM(cantidad_solicitada) as unidades'));
            $reorderRequests = DB::table('solicitudes_reposicion')
                ->leftJoinSub($detailSummary, 'detalle_resumen', 'detalle_resumen.solicitud_id', '=', 'solicitudes_reposicion.solicitud_id')
                ->join('usuarios', 'usuarios.rut', '=', 'solicitudes_reposicion.usuario_rut')
                ->when($requestFilters['desde_solicitud'] ?? null, fn ($query, $date) => $query->where('solicitudes_reposicion.fecha_solicitud', '>=', $date))
                ->when($requestFilters['hasta_solicitud'] ?? null, fn ($query, $date) => $query->where('solicitudes_reposicion.fecha_solicitud', '<', Carbon::parse($date)->addDay()->toDateString()))
                ->when($requestFilters['estado_solicitud_lista'] ?? null, fn ($query, $state) => $query->where('solicitudes_reposicion.estado', $state))
                ->when($requestFilters['buscar_solicitud'] ?? null, function ($query, string $search) {
                    $term = '%'.$search.'%';
                    $query->where(function ($query) use ($search, $term) {
                        $query->where('solicitudes_reposicion.solicitud_id', 'like', $term)
                            ->orWhere('solicitudes_reposicion.estado', 'like', $term)
                            ->orWhere(fn ($nameQuery) => PersonName::whereMatches($nameQuery, 'usuarios', $search))
                            ->orWhereExists(function ($details) use ($term) {
                                $details->selectRaw('1')
                                    ->from('solicitud_reposicion_detalle')
                                    ->join('productos', 'productos.producto_id', '=', 'solicitud_reposicion_detalle.producto_id')
                                    ->whereColumn('solicitud_reposicion_detalle.solicitud_id', 'solicitudes_reposicion.solicitud_id')
                                    ->where('productos.nombre', 'like', $term);
                            });
                    });
                })
                ->orderByDesc('solicitudes_reposicion.fecha_solicitud')
                ->paginate(15, [
                    'solicitudes_reposicion.solicitud_id',
                    'solicitudes_reposicion.fecha_solicitud',
                    'solicitudes_reposicion.estado',
                    PersonName::expression('usuarios', 'responsable'),
                    DB::raw('COALESCE(detalle_resumen.lineas, 0) as lineas'),
                    DB::raw('COALESCE(detalle_resumen.unidades, 0) as unidades'),
                ], 'solicitudes_page')
                ->withQueryString();

            if ($request->expectsJson()) {
                return response()->json(['data' => $reorderRequests, 'filters' => $requestFilters]);
            }

            return view('supplies.index', [
                'screen' => 'requests',
                'requestFilters' => $requestFilters,
                'reorderRequests' => $reorderRequests,
                'canRequest' => Auth::user()?->hasPermission('supplies.request') ?? false,
            ]);
        }

        $shipmentFilters = $request->validate([
            'buscar_remesa' => ['nullable', 'string', 'max:100'],
            'desde_remesa' => ['nullable', 'bail', new FourDigitDate()],
            'hasta_remesa' => ['nullable', 'bail', new FourDigitDate(), 'after_or_equal:desde_remesa'],
            'estado_solicitud' => ['nullable', Rule::in(['PENDIENTE', 'SOLICITADA', 'RECIBIDA', 'CANCELADA', 'sin_solicitud'])],
        ]);
        $shipments = DB::table('remesas')
            ->join('usuarios', 'usuarios.rut', '=', 'remesas.usuario_rut')
            ->leftJoin('solicitudes_reposicion', 'solicitudes_reposicion.solicitud_id', '=', 'remesas.solicitud_id')
            ->when($shipmentFilters['desde_remesa'] ?? null, fn ($query, $date) => $query->whereDate('remesas.fecha_recepcion', '>=', $date))
            ->when($shipmentFilters['hasta_remesa'] ?? null, fn ($query, $date) => $query->whereDate('remesas.fecha_recepcion', '<=', $date))
            ->when(($shipmentFilters['estado_solicitud'] ?? null) === 'sin_solicitud', fn ($query) => $query->whereNull('remesas.solicitud_id'))
            ->when(($shipmentFilters['estado_solicitud'] ?? null) && ($shipmentFilters['estado_solicitud'] ?? null) !== 'sin_solicitud', fn ($query) => $query->where('solicitudes_reposicion.estado', $shipmentFilters['estado_solicitud']))
            ->when($shipmentFilters['buscar_remesa'] ?? null, function ($query, string $search) {
                $term = '%'.$search.'%';
                $query->where(function ($query) use ($search, $term) {
                    $query->where('remesas.codigo_remesa', 'like', $term)
                        ->orWhere('remesas.origen', 'like', $term)
                        ->orWhere('solicitudes_reposicion.estado', 'like', $term)
                        ->orWhere(function ($nameQuery) use ($search) {
                            PersonName::whereMatches($nameQuery, 'usuarios', $search);
                        })
                        ->orWhereExists(function ($details) use ($term) {
                            $details->selectRaw('1')
                                ->from('remesa_detalle')
                                ->join('productos', 'productos.producto_id', '=', 'remesa_detalle.producto_id')
                                ->whereColumn('remesa_detalle.remesa_id', 'remesas.remesa_id')
                                ->where('productos.nombre', 'like', $term);
                        });
                });
            })
            ->orderByDesc('remesas.fecha_recepcion')
            ->paginate(15, [
                'remesas.remesa_id',
                'remesas.codigo_remesa',
                'remesas.origen',
                'remesas.fecha_recepcion',
                'solicitudes_reposicion.estado as estado_solicitud',
                PersonName::expression('usuarios', 'responsable'),
            ], 'remesas_page')
            ->withQueryString();
        $shipmentIds = $shipments->getCollection()->pluck('remesa_id')->filter()->values()->all();
        $shipmentDetails = DB::table('remesa_detalle')
            ->join('productos', 'productos.producto_id', '=', 'remesa_detalle.producto_id')
            ->when($shipmentIds !== [], fn ($query) => $query->whereIn('remesa_detalle.remesa_id', $shipmentIds))
            ->get(['remesa_detalle.remesa_id', 'productos.nombre'])
            ->groupBy('remesa_id');
        $shipments->getCollection()->each(function ($shipment) use ($shipmentDetails) {
            $details = $shipmentDetails->get($shipment->remesa_id, collect());
            $shipment->lineas = $details->count();
            $shipment->productos = $details->pluck('nombre')->unique()->join(', ');
        });

        if ($request->expectsJson()) {
            return response()->json(['data' => $shipments, 'filters' => $shipmentFilters]);
        }

        return view('supplies.index', [
            'screen' => $screen,
            'shipments' => $shipments,
            'shipmentFilters' => $shipmentFilters,
            'canReceive' => Auth::user()?->hasPermission('supplies.receive') ?? false,
        ]);
    }

    private function receiptProductOptions()
    {
        $products = DB::table('productos')
            ->join('unidades_medida as base_units', 'base_units.unidad_medida_id', '=', 'productos.unidad_base_inventario_id')
            ->where('productos.activo', true)
            ->orderBy('productos.nombre')
            ->get([
                'productos.producto_id',
                'productos.nombre',
                'base_units.codigo as unidad_base_codigo',
            ]);

        return $products;
    }

    private function authorizeSupplyOperation(AuditLogger $audit, string $permission, string $entity): void
    {
        $user = Auth::user();
        if (! $user instanceof Usuario || ! $user->hasPermission($permission)) {
            $audit->record('ACCESO_DENEGADO', $entity, null, 'RECHAZADO');
            abort(403);
        }
    }
}
