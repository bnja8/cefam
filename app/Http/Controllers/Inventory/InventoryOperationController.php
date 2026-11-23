<?php

namespace App\Http\Controllers\Inventory;

use App\Http\Controllers\Controller;
use App\Http\Requests\Inventory\AdjustInventoryRequest;
use App\Http\Requests\Inventory\ReceiveInventoryRequest;
use App\Http\Requests\Inventory\ResolveInventoryAdjustmentRequest;
use App\Http\Requests\Inventory\TransferInventoryRequest;
use App\Http\Requests\Inventory\UpdateStockLimitsRequest;
use App\Models\Usuario;
use App\Services\Audit\AuditLogger;
use App\Services\Inventory\InventoryOperationService;
use App\Support\People\PersonName;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class InventoryOperationController extends Controller
{
    public function createTransfer(AuditLogger $audit): View
    {
        $this->authorizeInventoryManager($audit, 'inventory.transfer', 'traspasos_inventario');

        return view('inventory.index', [
            'screen' => 'transfer',
            'locations' => DB::table('ubicaciones')->orderBy('nombre')->get(['ubicacion_id', 'nombre', 'tipo']),
            'lots' => DB::table('inventario')
                ->join('lotes', 'lotes.lote_id', '=', 'inventario.lote_id')
                ->join('productos', 'productos.producto_id', '=', 'lotes.producto_id')
                ->join('ubicaciones', 'ubicaciones.ubicacion_id', '=', 'inventario.ubicacion_id')
                ->leftJoin('unidades_medida as base_units', 'base_units.unidad_medida_id', '=', 'productos.unidad_base_inventario_id')
                ->where('productos.activo', true)
                ->where('lotes.estado', 'ACTIVO')
                ->where('inventario.cantidad', '>', 0)
                ->whereDate('lotes.fecha_vencimiento', '>=', now()->toDateString())
                ->orderBy('lotes.fecha_vencimiento')
                ->get([
                    'lotes.lote_id',
                    'lotes.numero_lote',
                    'productos.nombre as producto',
                    'base_units.codigo as unidad_base_codigo',
                    'ubicaciones.nombre as ubicacion',
                    'inventario.cantidad',
                    'inventario.ubicacion_id',
                ]),
        ]);
    }

    public function createAdjustment(Request $request, AuditLogger $audit): View
    {
        $this->authorizeAdjustmentRequester($audit);
        $adjustmentDefaults = $request->validate([
            'lote_id' => ['nullable', 'integer', 'exists:lotes,lote_id'],
            'ubicacion_id' => ['nullable', 'integer', 'exists:ubicaciones,ubicacion_id'],
            'tipo_ajuste' => ['nullable', 'in:ENTRADA,SALIDA'],
            'cantidad' => ['nullable', 'numeric', 'gt:0'],
            'motivo' => ['nullable', 'string', 'max:1000'],
        ]);
        $adjustmentLots = DB::table('lotes')
            ->join('productos', 'productos.producto_id', '=', 'lotes.producto_id')
            ->leftJoin('unidades_medida as base_units', 'base_units.unidad_medida_id', '=', 'productos.unidad_base_inventario_id')
            ->leftJoinSub(
                DB::table('inventario')
                    ->select('lote_id')
                    ->selectRaw('SUM(cantidad) as existencia_total')
                    ->groupBy('lote_id'),
                'stock_lote',
                'stock_lote.lote_id',
                '=',
                'lotes.lote_id'
            )
            ->orderBy('productos.nombre')
            ->orderBy('lotes.fecha_vencimiento')
            ->get([
                'lotes.lote_id',
                'lotes.producto_id',
                'lotes.numero_lote',
                'lotes.fecha_vencimiento',
                'lotes.estado as estado_lote',
                'productos.nombre as producto',
                'base_units.codigo as unidad_base_codigo',
                DB::raw('COALESCE(stock_lote.existencia_total, 0) as existencia_total'),
            ]);
        $stockByLocation = DB::table('inventario')
            ->whereIn('lote_id', $adjustmentLots->pluck('lote_id'))
            ->get(['lote_id', 'ubicacion_id', 'cantidad'])
            ->groupBy('lote_id')
            ->map(fn ($stockRows) => $stockRows->mapWithKeys(
                fn ($stock) => [(string) $stock->ubicacion_id => (float) $stock->cantidad]
            )->all());
        $adjustmentLots->each(function ($lot) use ($stockByLocation): void {
            $lot->existencia_por_ubicacion = $stockByLocation->get($lot->lote_id, []);
        });
        $defaultLot = $adjustmentLots->firstWhere('lote_id', (int) ($adjustmentDefaults['lote_id'] ?? 0));
        $details = $request->old('detalles', []);
        $details = is_array($details) ? array_filter($details, 'is_array') : [];
        if ($details === []) {
            $details = [[
                'producto_id' => $defaultLot->producto_id ?? null,
                'lote_id' => $adjustmentDefaults['lote_id'] ?? '',
                'ubicacion_id' => $adjustmentDefaults['ubicacion_id'] ?? '',
                'tipo_ajuste' => $adjustmentDefaults['tipo_ajuste'] ?? '',
                'cantidad' => $adjustmentDefaults['cantidad'] ?? '',
                'motivo' => $adjustmentDefaults['motivo'] ?? '',
            ]];
        }
        $productIds = collect($details)
            ->filter(fn ($detail) => is_array($detail) && is_numeric($detail['producto_id'] ?? null))
            ->pluck('producto_id')
            ->unique()
            ->values();
        $selectedProducts = $productIds->isEmpty()
            ? collect()
            : DB::table('productos')
                ->leftJoin('unidades_medida as base_units', 'base_units.unidad_medida_id', '=', 'productos.unidad_base_inventario_id')
                ->whereIn('productos.producto_id', $productIds)
                ->get([
                    'productos.producto_id',
                    'productos.nombre',
                    'productos.es_controlado',
                    'base_units.codigo as unidad_base_codigo',
                ])
                ->keyBy('producto_id');
        $adjustmentDetails = collect($details)
            ->map(function (array $detail) use ($selectedProducts): array {
                $detail['product'] = $selectedProducts->get($detail['producto_id'] ?? null);

                return $detail;
            });

        return view('inventory.index', [
            'screen' => 'adjust',
            'canAuthorizeAdjustments' => Auth::user()->hasPermission('inventory.adjust.resolve'),
            'adjustmentDefaults' => $adjustmentDefaults,
            'adjustmentDetails' => $adjustmentDetails,
            'locations' => DB::table('ubicaciones')->orderBy('nombre')->get(['ubicacion_id', 'nombre', 'tipo']),
            'adjustmentLots' => $adjustmentLots,
        ]);
    }

    public function adjustmentRequests(Request $request, AuditLogger $audit): View|JsonResponse
    {
        $this->authorizeAdjustmentRequester($audit, allowReview: true);
        $user = Auth::user();
        $canReviewAllRequests = $user->hasPermission('inventory.adjust.resolve');

        $requests = DB::table('solicitudes_ajuste_grupos')
            ->join('usuarios as solicitante', 'solicitante.rut', '=', 'solicitudes_ajuste_grupos.usuario_solicita_rut')
            ->leftJoin('usuarios as autorizador', 'autorizador.rut', '=', 'solicitudes_ajuste_grupos.usuario_autoriza_rut')
            ->when(! $canReviewAllRequests, fn ($query) => $query->where(
                'solicitudes_ajuste_grupos.usuario_solicita_rut',
                $user->getAuthIdentifier()
            ))
            ->orderByRaw("CASE WHEN solicitudes_ajuste_grupos.estado = 'PENDIENTE' THEN 0 ELSE 1 END")
            ->orderByDesc('solicitudes_ajuste_grupos.fecha_solicitud')
            ->paginate(20, [
                'solicitudes_ajuste_grupos.*',
                PersonName::expression('solicitante', 'solicitante'),
                PersonName::expression('autorizador', 'autorizador'),
            ])
            ->withQueryString();
        $details = DB::table('solicitudes_ajuste_inventario')
            ->join('lotes', 'lotes.lote_id', '=', 'solicitudes_ajuste_inventario.lote_id')
            ->join('productos', 'productos.producto_id', '=', 'lotes.producto_id')
            ->join('ubicaciones', 'ubicaciones.ubicacion_id', '=', 'solicitudes_ajuste_inventario.ubicacion_id')
            ->whereIn('solicitudes_ajuste_inventario.grupo_ajuste_id', $requests->getCollection()->pluck('grupo_ajuste_id'))
            ->orderBy('solicitudes_ajuste_inventario.solicitud_ajuste_id')
            ->get([
                'solicitudes_ajuste_inventario.solicitud_ajuste_id',
                'solicitudes_ajuste_inventario.grupo_ajuste_id',
                'solicitudes_ajuste_inventario.tipo_ajuste',
                'solicitudes_ajuste_inventario.cantidad',
                'solicitudes_ajuste_inventario.motivo',
                'solicitudes_ajuste_inventario.movimiento_id',
                'lotes.numero_lote',
                'productos.nombre as producto',
                'ubicaciones.nombre as ubicacion',
            ])
            ->groupBy('grupo_ajuste_id');
        $requests->getCollection()->each(function ($adjustmentGroup) use ($details): void {
            $adjustmentGroup->detalles = $details->get($adjustmentGroup->grupo_ajuste_id, collect());
            $adjustmentGroup->solicitud_ajuste_id = $adjustmentGroup->detalles->first()?->solicitud_ajuste_id;
        });

        if ($request->expectsJson()) {
            return response()->json(['data' => $requests]);
        }

        return view('inventory.index', [
            'screen' => 'adjustments',
            'adjustmentRequests' => $requests,
            'canAuthorizeAdjustments' => $user->hasPermission('inventory.adjust.resolve'),
            'isPharmacist' => $user->hasPermission('inventory.adjust.resolve'),
        ]);
    }

    public function showAdjustmentRequest(int $groupId, AuditLogger $audit): View
    {
        $this->authorizeAdjustmentRequester($audit, allowReview: true);
        $user = Auth::user();
        $canReviewAllRequests = $user->hasPermission('inventory.adjust.resolve');

        $adjustmentRequest = DB::table('solicitudes_ajuste_grupos')
            ->join('usuarios as solicitante', 'solicitante.rut', '=', 'solicitudes_ajuste_grupos.usuario_solicita_rut')
            ->leftJoin('usuarios as autorizador', 'autorizador.rut', '=', 'solicitudes_ajuste_grupos.usuario_autoriza_rut')
            ->where('solicitudes_ajuste_grupos.grupo_ajuste_id', $groupId)
            ->when(! $canReviewAllRequests, fn ($query) => $query->where(
                'solicitudes_ajuste_grupos.usuario_solicita_rut',
                $user->getAuthIdentifier()
            ))
            ->first([
                'solicitudes_ajuste_grupos.*',
                PersonName::expression('solicitante', 'solicitante'),
                PersonName::expression('autorizador', 'autorizador'),
            ]);

        abort_if(! $adjustmentRequest, 404);

        $details = DB::table('solicitudes_ajuste_inventario')
            ->join('lotes', 'lotes.lote_id', '=', 'solicitudes_ajuste_inventario.lote_id')
            ->join('productos', 'productos.producto_id', '=', 'lotes.producto_id')
            ->join('ubicaciones', 'ubicaciones.ubicacion_id', '=', 'solicitudes_ajuste_inventario.ubicacion_id')
            ->where('solicitudes_ajuste_inventario.grupo_ajuste_id', $groupId)
            ->orderBy('solicitudes_ajuste_inventario.solicitud_ajuste_id')
            ->get([
                'solicitudes_ajuste_inventario.solicitud_ajuste_id',
                'solicitudes_ajuste_inventario.tipo_ajuste',
                'solicitudes_ajuste_inventario.cantidad',
                'solicitudes_ajuste_inventario.motivo',
                'solicitudes_ajuste_inventario.movimiento_id',
                'lotes.numero_lote',
                'lotes.fecha_vencimiento',
                'productos.nombre as producto',
                'ubicaciones.nombre as ubicacion',
            ]);

        $selfAuthorizationBlocked = $adjustmentRequest->estado === 'PENDIENTE'
            && $adjustmentRequest->solicitante_es_qf
            && $adjustmentRequest->usuario_solicita_rut === $user->getAuthIdentifier();

        return view('inventory.adjustment-show', [
            'adjustmentRequest' => $adjustmentRequest,
            'adjustmentDetails' => $details,
            'canResolveAdjustment' => $canReviewAllRequests && ! $selfAuthorizationBlocked,
            'selfAuthorizationBlocked' => $selfAuthorizationBlocked,
        ]);
    }

    public function apiOptions(AuditLogger $audit): JsonResponse
    {
        $this->authorizeAdjustmentRequester($audit);
        $canTransfer = Auth::user()->hasPermission('inventory.transfer');

        return response()->json([
            'ubicaciones' => DB::table('ubicaciones')->orderBy('nombre')->get(['ubicacion_id', 'nombre', 'tipo']),
            'productos' => DB::table('productos')
                ->leftJoin('unidades_medida as base_units', 'base_units.unidad_medida_id', '=', 'productos.unidad_base_inventario_id')
                ->where('productos.activo', true)
                ->orderBy('productos.nombre')
                ->get([
                    'productos.producto_id',
                    'productos.nombre',
                    'base_units.codigo as unidad_base_codigo',
                ]),
            'lotes' => DB::table('lotes')
                ->join('productos', 'productos.producto_id', '=', 'lotes.producto_id')
                ->where('productos.activo', true)
                ->orderBy('productos.nombre')
                ->orderBy('lotes.fecha_vencimiento')
                ->get([
                    'lotes.lote_id',
                    'lotes.numero_lote',
                    'lotes.fecha_vencimiento',
                    'lotes.estado as estado_lote',
                    'productos.producto_id',
                    'productos.nombre as producto',
                ]),
            'traspasos' => $canTransfer ? DB::table('inventario')
                ->join('lotes', 'lotes.lote_id', '=', 'inventario.lote_id')
                ->join('productos', 'productos.producto_id', '=', 'lotes.producto_id')
                ->join('ubicaciones', 'ubicaciones.ubicacion_id', '=', 'inventario.ubicacion_id')
                ->where('productos.activo', true)
                ->where('lotes.estado', 'ACTIVO')
                ->where('inventario.cantidad', '>', 0)
                ->whereDate('lotes.fecha_vencimiento', '>=', now()->toDateString())
                ->orderBy('lotes.fecha_vencimiento')
                ->get([
                    'lotes.lote_id',
                    'lotes.numero_lote',
                    'productos.nombre as producto',
                    'ubicaciones.nombre as ubicacion',
                    'inventario.cantidad',
                    'inventario.ubicacion_id',
                ]) : collect(),
            'puede_traspasar' => $canTransfer,
            'puede_configurar_limites' => Auth::user()->hasPermission('inventory.limits.manage'),
        ]);
    }

    // Actualiza límites de stock para la ubicación seleccionada.
    public function updateStockLimits(
        UpdateStockLimitsRequest $request,
        int $productId,
        InventoryOperationService $operations
    ): RedirectResponse|JsonResponse {
        $operations->updateStockLimits($productId, $request->validated());

        if ($request->expectsJson()) {
            return response()->json(['message' => 'Límites de stock actualizados.']);
        }

        return redirect()->route('products.stock', $productId)
            ->withFragment('stock-ubicacion-'.$request->validated()['ubicacion_id'])
            ->with('status', 'Límites de stock actualizados para la ubicación.');
    }

    // Registra una recepción validada y actualiza las existencias.
    public function receive(ReceiveInventoryRequest $request, InventoryOperationService $operations): RedirectResponse|JsonResponse
    {
        $operations->receive($request->validated(), (string) $request->user()->getAuthIdentifier());

        if ($request->expectsJson()) {
            return response()->json(['message' => 'Recepción registrada.'], 201);
        }

        return redirect()->route('inventory.index')
            ->with('status', 'Recepción registrada; el stock incluye solo las cantidades aceptadas.');
    }

    // Registra una solicitud de ajuste sin modificar las existencias.
    public function adjust(AdjustInventoryRequest $request, InventoryOperationService $operations): RedirectResponse|JsonResponse
    {
        $data = $request->validated();
        if (isset($data['detalles'])) {
            $result = $operations->requestAdjustments($data['detalles'], (string) $request->user()->getAuthIdentifier());

            if ($request->expectsJson()) {
                return response()->json([
                    'message' => 'Solicitud de ajuste registrada.',
                    'grupo_ajuste_id' => $result['grupo_ajuste_id'],
                    'solicitud_ajuste_ids' => $result['solicitud_ajuste_ids'],
                ], 201);
            }

            return redirect()->route('inventory.adjustments.index')
                ->with('status', 'Solicitud registrada con '.count($result['solicitud_ajuste_ids']).' líneas. El stock se actualizará cuando un Químico Farmacéutico la autorice.');
        }

        $adjustmentId = $operations->requestAdjustment($data, (string) $request->user()->getAuthIdentifier());
        if ($request->expectsJson()) {
            return response()->json(['message' => 'Solicitud de ajuste registrada.', 'solicitud_ajuste_id' => $adjustmentId], 201);
        }

        return redirect()->route('inventory.adjustments.index')
            ->with('status', 'Solicitud registrada. El stock se actualizará cuando un Químico Farmacéutico la autorice.');
    }

    public function resolveAdjustment(
        ResolveInventoryAdjustmentRequest $request,
        int $adjustmentId,
        InventoryOperationService $operations
    ): RedirectResponse|JsonResponse {
        $data = $request->validated();
        $operations->resolveAdjustmentRequest(
            $adjustmentId,
            (string) $request->user()->getAuthIdentifier(),
            $data['decision'],
            $data['motivo_resolucion'] ?? null
        );

        if ($request->expectsJson()) {
            return response()->json(['message' => 'Solicitud de ajuste resuelta.']);
        }

        return redirect()->route('inventory.adjustments.index')->with(
            'status',
            $data['decision'] === 'APROBAR' ? 'Solicitud autorizada y stock actualizado.' : 'Solicitud rechazada.'
        );
    }

    // Registra un traspaso validado entre ubicaciones.
    public function transfer(TransferInventoryRequest $request, InventoryOperationService $operations): RedirectResponse|JsonResponse
    {
        $operations->transfer($request->validated(), (string) $request->user()->getAuthIdentifier());

        if ($request->expectsJson()) {
            return response()->json(['message' => 'Traspaso registrado.'], 201);
        }

        return redirect()->route('inventory.index')->with('status', 'Traspaso registrado sin alterar la trazabilidad del lote.');
    }

    // Audita y rechaza usuarios sin permisos de gestión de inventario.
    private function authorizeInventoryManager(AuditLogger $audit, string $permission, string $entity): void
    {
        $user = Auth::user();
        if (! $user instanceof Usuario || ! $user->hasPermission($permission)) {
            $audit->record('ACCESO_DENEGADO', $entity, null, 'RECHAZADO');
            abort(403);
        }
    }

    private function authorizeAdjustmentRequester(AuditLogger $audit, bool $allowReview = false): void
    {
        $user = Auth::user();
        $permissions = $allowReview
            ? ['inventory.adjust.request', 'inventory.adjust.resolve']
            : ['inventory.adjust.request'];
        if (! $user instanceof Usuario || ! $user->hasAnyPermission($permissions)) {
            $audit->record('ACCESO_DENEGADO', 'solicitudes_ajuste_inventario', null, 'RECHAZADO');
            abort(403);
        }
    }
}
