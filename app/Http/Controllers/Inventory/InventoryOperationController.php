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
        $this->authorizeInventoryManager($audit, 'traspasos_inventario');

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

        return view('inventory.index', [
            'screen' => 'adjust',
            'canAuthorizeAdjustments' => Auth::user()->hasAnyRole(['Químico Farmacéutico']),
            'adjustmentDefaults' => $adjustmentDefaults,
            'locations' => DB::table('ubicaciones')->orderBy('nombre')->get(['ubicacion_id', 'nombre', 'tipo']),
            'adjustmentLots' => DB::table('lotes')
                ->join('productos', 'productos.producto_id', '=', 'lotes.producto_id')
                ->leftJoin('unidades_medida as base_units', 'base_units.unidad_medida_id', '=', 'productos.unidad_base_inventario_id')
                ->orderBy('productos.nombre')
                ->orderBy('lotes.fecha_vencimiento')
                ->get([
                    'lotes.lote_id',
                    'lotes.numero_lote',
                    'lotes.fecha_vencimiento',
                    'lotes.estado as estado_lote',
                    'productos.nombre as producto',
                    'base_units.codigo as unidad_base_codigo',
                ]),
        ]);
    }

    public function adjustmentRequests(Request $request, AuditLogger $audit): View|JsonResponse
    {
        $this->authorizeAdjustmentRequester($audit);
        $user = Auth::user();
        $canReviewAllRequests = $user->hasAnyRole(['Administrador', 'Químico Farmacéutico']);

        $requests = DB::table('solicitudes_ajuste_inventario')
            ->join('lotes', 'lotes.lote_id', '=', 'solicitudes_ajuste_inventario.lote_id')
            ->join('productos', 'productos.producto_id', '=', 'lotes.producto_id')
            ->join('ubicaciones', 'ubicaciones.ubicacion_id', '=', 'solicitudes_ajuste_inventario.ubicacion_id')
            ->join('usuarios as solicitante', 'solicitante.rut', '=', 'solicitudes_ajuste_inventario.usuario_solicita_rut')
            ->leftJoin('usuarios as autorizador', 'autorizador.rut', '=', 'solicitudes_ajuste_inventario.usuario_autoriza_rut')
            ->when(! $canReviewAllRequests, fn ($query) => $query->where(
                'solicitudes_ajuste_inventario.usuario_solicita_rut',
                $user->getAuthIdentifier()
            ))
            ->orderByRaw("CASE WHEN solicitudes_ajuste_inventario.estado = 'PENDIENTE' THEN 0 ELSE 1 END")
            ->orderByDesc('solicitudes_ajuste_inventario.fecha_solicitud')
            ->paginate(20, [
                'solicitudes_ajuste_inventario.*',
                'lotes.numero_lote',
                'productos.nombre as producto',
                'ubicaciones.nombre as ubicacion',
                PersonName::expression('solicitante', 'solicitante'),
                PersonName::expression('autorizador', 'autorizador'),
            ])
            ->withQueryString();

        if ($request->expectsJson()) {
            return response()->json(['data' => $requests]);
        }

        return view('inventory.index', [
            'screen' => 'adjustments',
            'adjustmentRequests' => $requests,
            'canAuthorizeAdjustments' => $user->hasAnyRole(['Químico Farmacéutico']),
            'isPharmacist' => $user->hasAnyRole(['Químico Farmacéutico']),
        ]);
    }

    public function apiOptions(AuditLogger $audit): JsonResponse
    {
        $this->authorizeAdjustmentRequester($audit);
        $canTransfer = Auth::user()->hasAnyRole(['Administrador', 'Químico Farmacéutico']);

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
            'puede_configurar_limites' => Auth::user()->hasAnyRole(['Administrador', 'Químico Farmacéutico']),
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
        $adjustmentId = $operations->requestAdjustment($request->validated(), (string) $request->user()->getAuthIdentifier());

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
    private function authorizeInventoryManager(AuditLogger $audit, string $entity): void
    {
        $user = Auth::user();
        if (! $user instanceof Usuario || ! $user->hasAnyRole(['Administrador', 'Químico Farmacéutico'])) {
            $audit->record('ACCESO_DENEGADO', $entity, null, 'RECHAZADO');
            abort(403);
        }
    }

    private function authorizeAdjustmentRequester(AuditLogger $audit): void
    {
        $user = Auth::user();
        if (! $user instanceof Usuario || ! $user->hasAnyRole([
            'Administrador',
            'Químico Farmacéutico',
            'Técnico Farmacéutico',
        ])) {
            $audit->record('ACCESO_DENEGADO', 'solicitudes_ajuste_inventario', null, 'RECHAZADO');
            abort(403);
        }
    }
}
