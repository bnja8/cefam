<?php

namespace App\Actions\Supplies;

use App\Models\Usuario;
use App\Services\Audit\AuditLogger;
use App\Support\People\PersonName;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class SupplySearchAndDetailAction
{
    public function searchRequestProducts(Request $request, AuditLogger $audit): JsonResponse
    {
        $this->authorizeSupplyOperation($audit, 'supplies.view', 'solicitudes_reposicion');
        $data = $request->validate([
            'q' => ['required', 'string', 'max:100'],
        ]);
        $term = trim($data['q']);
        if (mb_strlen($term) < 2) {
            return response()->json([]);
        }

        $products = DB::table('productos')
            ->leftJoin('unidades_medida as base_units', 'base_units.unidad_medida_id', '=', 'productos.unidad_base_inventario_id')
            ->where('productos.activo', true)
            ->where('productos.nombre', 'like', '%'.$term.'%')
            ->orderBy('productos.nombre')
            ->limit(20)
            ->get([
                'productos.producto_id',
                'productos.nombre',
                'base_units.codigo as unidad_base_codigo',
            ]);

        $locationsByProduct = collect();
        if ($products->isNotEmpty()) {
            $locationsByProduct = DB::table('inventario')
                ->join('lotes', 'lotes.lote_id', '=', 'inventario.lote_id')
                ->join('ubicaciones', 'ubicaciones.ubicacion_id', '=', 'inventario.ubicacion_id')
                ->whereIn('lotes.producto_id', $products->pluck('producto_id'))
                ->where('lotes.estado', 'ACTIVO')
                ->whereDate('lotes.fecha_vencimiento', '>=', now()->toDateString())
                ->where('inventario.cantidad', '>', 0)
                ->groupBy('lotes.producto_id', 'ubicaciones.ubicacion_id', 'ubicaciones.nombre')
                ->orderBy('ubicaciones.nombre')
                ->get([
                    'lotes.producto_id',
                    'ubicaciones.nombre as nombre',
                    DB::raw('SUM(inventario.cantidad) as cantidad'),
                ])
                ->groupBy('producto_id');
        }

        return response()->json($products->map(fn ($product) => [
            'producto_id' => (int) $product->producto_id,
            'nombre' => $product->nombre,
            'unidad_base_codigo' => $product->unidad_base_codigo,
            'ubicaciones' => $locationsByProduct->get($product->producto_id, collect())
                ->map(fn ($location) => [
                    'nombre' => $location->nombre,
                    'cantidad' => (float) $location->cantidad,
                    'unidad_base_codigo' => $product->unidad_base_codigo,
                ])
                ->values(),
        ])->values());
    }

    public function shipmentDetail(int $shipmentId): JsonResponse
    {
        $shipment = DB::table('remesas')
            ->join('usuarios', 'usuarios.rut', '=', 'remesas.usuario_rut')
            ->leftJoin('solicitudes_reposicion', 'solicitudes_reposicion.solicitud_id', '=', 'remesas.solicitud_id')
            ->where('remesas.remesa_id', $shipmentId)
            ->first([
                'remesas.remesa_id',
                'remesas.codigo_remesa',
                'remesas.origen',
                'remesas.fecha_recepcion',
                'remesas.solicitud_id',
                'solicitudes_reposicion.estado as estado_solicitud',
                PersonName::expression('usuarios', 'responsable'),
            ]);
        abort_unless($shipment, 404);

        $details = DB::table('remesa_detalle')
            ->join('productos', 'productos.producto_id', '=', 'remesa_detalle.producto_id')
            ->where('remesa_detalle.remesa_id', $shipmentId)
            ->orderBy('productos.nombre')
            ->get([
                'remesa_detalle.producto_id',
                'productos.nombre as producto',
                'remesa_detalle.numero_lote',
                'remesa_detalle.fecha_fabricacion',
                'remesa_detalle.fecha_vencimiento',
                'remesa_detalle.cantidad',
                'remesa_detalle.cantidad_aceptada',
                'remesa_detalle.cantidad_rechazada',
                'remesa_detalle.motivo_rechazo',
            ]);

        return response()->json(['data' => ['remesa' => $shipment, 'detalles' => $details]]);
    }

    public function requestDetail(int $requestId): JsonResponse
    {
        $supplyRequest = DB::table('solicitudes_reposicion')
            ->join('usuarios', 'usuarios.rut', '=', 'solicitudes_reposicion.usuario_rut')
            ->where('solicitudes_reposicion.solicitud_id', $requestId)
            ->first([
                'solicitudes_reposicion.solicitud_id',
                'solicitudes_reposicion.fecha_solicitud',
                'solicitudes_reposicion.estado',
                PersonName::expression('usuarios', 'responsable'),
            ]);
        abort_unless($supplyRequest, 404);

        $details = DB::table('solicitud_reposicion_detalle')
            ->join('productos', 'productos.producto_id', '=', 'solicitud_reposicion_detalle.producto_id')
            ->where('solicitud_reposicion_detalle.solicitud_id', $requestId)
            ->orderBy('productos.nombre')
            ->get([
                'solicitud_reposicion_detalle.producto_id',
                'productos.nombre as producto',
                'solicitud_reposicion_detalle.cantidad_solicitada',
            ]);

        return response()->json(['data' => ['solicitud' => $supplyRequest, 'detalles' => $details]]);
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
