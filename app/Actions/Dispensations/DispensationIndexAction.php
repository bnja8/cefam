<?php

namespace App\Actions\Dispensations;

use App\Rules\FourDigitDate;
use App\Services\DeliveryOrders\DeliveryOrderData;
use App\Services\Patients\PatientRecordArchiver;
use App\Support\People\PersonName;
use App\Support\People\Rut;
use Illuminate\Database\Query\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class DispensationIndexAction
{
    public function index(Request $request, DeliveryOrderData $deliveryOrderData, PatientRecordArchiver $recordArchiver): View|JsonResponse
    {
        $screen = $request->route('screen', 'create');
        if ($screen === 'create') {
            $detailRows = old('detalles', []);
            if (! is_array($detailRows)) {
                $detailRows = [];
            }
            $detailRows = array_map(
                // Transforma cada elemento de la colección.
                fn ($detail) => is_array($detail) ? $detail : [],
                array_slice(array_values($detailRows), 0, 30)
            );
            $selectedProductIds = collect($detailRows)
                ->pluck('producto_id')
                ->filter()
                ->unique()
                ->values();
            $requestedOrderId = $request->query('comprobante');
            if (is_string($requestedOrderId) && ctype_digit($requestedOrderId) && (int) $requestedOrderId > 0) {
                $orderId = (int) $requestedOrderId;
                $request->session()->forget('delivery_order_id');
            } else {
                $orderId = $request->session()->pull('delivery_order_id');
            }

            $data = [
                'screen' => 'create',
                'deliveryOrder' => $orderId === null ? null : $deliveryOrderData->forOrder((int) $orderId),
                'detailRows' => $detailRows,
                'selectedProducts' => $this->dispensationProductsQuery()
                    ->whereIn('productos.producto_id', $selectedProductIds)
                    ->get()
                    ->keyBy('producto_id'),
                'selectedPatient' => DB::table('pacientes')
                    ->where('rut', old('paciente_rut', $request->query('paciente_rut')))
                    ->where('activo', true)
                    ->first(['rut', PersonName::expression('pacientes', 'nombre_completo')]),
            ];

            return ($request->routeIs('api.v1.*') || $request->expectsJson())
                ? response()->json(['data' => $data])
                : view('dispensations.index', $data);
        }

        $recordArchiver->archiveExpired();
        $filters = $request->validate([
            'buscar' => ['nullable', 'string', 'max:100'],
            'fecha' => ['nullable', 'bail', new FourDigitDate()],
            'desde' => ['nullable', 'bail', new FourDigitDate()],
            'hasta' => ['nullable', 'bail', new FourDigitDate(), 'after_or_equal:desde'],
            'estado' => ['nullable', Rule::in(['PENDIENTE', 'CONFIRMADA', 'RECHAZADA', 'ANULADA'])],
        ]);
        $filters += ['estado' => 'CONFIRMADA'];

        $deliveries = DB::table('entregas')
            ->join('pacientes', 'pacientes.rut', '=', 'entregas.paciente_rut')
            ->join('usuarios', 'usuarios.rut', '=', 'entregas.usuario_rut')
            ->leftJoin('ordenes_entrega', 'ordenes_entrega.entrega_id', '=', 'entregas.entrega_id')
            ->leftJoin('personas_autorizadas', 'personas_autorizadas.persona_autorizada_id', '=', 'entregas.persona_autorizada_id')
            ->where('entregas.estado_ficha', 'RECIENTE')
            ->where('entregas.fecha_hora', '>', now()->subDays(30))
            ->select(
                'entregas.entrega_id',
                'entregas.fecha_hora',
                'entregas.estado',
                'ordenes_entrega.orden_entrega_id',
                'ordenes_entrega.numero_orden',
                PersonName::expression('pacientes', 'paciente'),
                'pacientes.rut',
                PersonName::expression('usuarios', 'responsable'),
                PersonName::expression('personas_autorizadas', 'persona_autorizada'),
            )
            ->when($filters['estado'] !== null, fn ($query) => $query->where('entregas.estado', $filters['estado']))
            ->when($filters['fecha'] ?? null, fn ($query, $date) => $query->whereDate('entregas.fecha_hora', $date))
            ->when($filters['desde'] ?? null, fn ($query, $date) => $query->whereDate('entregas.fecha_hora', '>=', $date))
            ->when($filters['hasta'] ?? null, fn ($query, $date) => $query->whereDate('entregas.fecha_hora', '<=', $date))
            ->when($filters['buscar'] ?? null, function ($query, string $search) {
                $term = '%'.$search.'%';
                $rutTerm = '%'.Rut::limpiarRut($search).'%';
                $query->where(function ($query) use ($search, $term, $rutTerm) {
                    $query->where('pacientes.rut', 'like', $rutTerm)
                        ->orWhere(function ($nameQuery) use ($search) {
                            PersonName::whereMatches($nameQuery, 'pacientes', $search);
                        })
                        ->orWhere(function ($nameQuery) use ($search) {
                            PersonName::whereMatches($nameQuery, 'usuarios', $search);
                        })
                        ->orWhere(function ($nameQuery) use ($search) {
                            PersonName::whereMatches($nameQuery, 'personas_autorizadas', $search);
                        })
                        ->orWhere('ordenes_entrega.numero_orden', 'like', $term)
                        ->orWhereExists(function ($details) use ($term) {
                            $details->selectRaw('1')
                                ->from('entrega_detalle')
                                ->join('productos', 'productos.producto_id', '=', 'entrega_detalle.producto_id')
                                ->whereColumn('entrega_detalle.entrega_id', 'entregas.entrega_id')
                                ->where('productos.nombre', 'like', $term);
                        });
                });
            })
            ->orderByDesc('entregas.fecha_hora')
            ->paginate(15)
            ->withQueryString();

        $requestedOrderId = $request->query('comprobante');
        $orderId = is_string($requestedOrderId) && ctype_digit($requestedOrderId) && (int) $requestedOrderId > 0
            ? (int) $requestedOrderId
            : null;
        $data = [
            'screen' => 'deliveries',
            'deliveries' => $deliveries,
            'filters' => $filters,
            'deliveryOrder' => $orderId === null ? null : $deliveryOrderData->forOrder($orderId),
        ];

        return ($request->routeIs('api.v1.*') || $request->expectsJson())
            ? response()->json(['data' => $data])
            : view('dispensations.index', $data);
    }

    private function dispensationProductsQuery(): Builder
    {
        $availableStock = DB::table('inventario')
            ->join('lotes', 'lotes.lote_id', '=', 'inventario.lote_id')
            ->join('ubicaciones', 'ubicaciones.ubicacion_id', '=', 'inventario.ubicacion_id')
            ->where('lotes.estado', 'ACTIVO')
            ->where('ubicaciones.tipo', 'VITRINA')
            ->where('inventario.cantidad', '>', 0)
            ->whereDate('lotes.fecha_vencimiento', '>=', now()->toDateString())
            ->select('lotes.producto_id')
            ->selectRaw('SUM(inventario.cantidad) as stock_disponible')
            ->groupBy('lotes.producto_id');

        return DB::table('productos')
            ->leftJoinSub($availableStock, 'stock_vitrina', fn ($join) => $join
                ->on('stock_vitrina.producto_id', '=', 'productos.producto_id'))
            ->leftJoin('unidades_medida as base_units', 'base_units.unidad_medida_id', '=', 'productos.unidad_base_inventario_id')
            ->where('productos.activo', true)
            ->select([
                'productos.producto_id',
                'productos.nombre',
                'productos.es_controlado',
                'base_units.codigo as unidad_base_codigo',
            ])
            ->selectRaw('COALESCE(stock_vitrina.stock_disponible, 0) as stock_disponible');
    }
}
