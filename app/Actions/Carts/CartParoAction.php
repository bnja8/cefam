<?php

namespace App\Actions\Carts;

use App\Models\Usuario;
use App\Services\Audit\AuditLogger;
use App\Services\Alerts\StockAlertService;
use App\Support\Inventory\InventoryQuantitySnapshot;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CartParoAction
{
    public function confirm(Request $request, int $preparationId, AuditLogger $audit): RedirectResponse|JsonResponse
    {
        $user = Auth::user();
        if (! $user instanceof Usuario || ! $user->hasAnyRole(['Químico Farmacéutico'])) {
            $audit->record('ACCESO_DENEGADO', 'preparaciones_unidosis', $preparationId, 'RECHAZADO');
            abort(403);
        }

        DB::transaction(function () use ($preparationId, $audit) {
            $preparation = DB::table('preparaciones_unidosis')
                ->where('preparacion_id', $preparationId)
                ->lockForUpdate()
                ->first();
            abort_unless($preparation, 404);
            if ($preparation->estado !== 'PREPARANDO') {
                throw ValidationException::withMessages(['preparation' => 'Solo se pueden confirmar preparaciones pendientes.']);
            }

            DB::table('preparaciones_unidosis')->where('preparacion_id', $preparationId)->update([
                'estado' => 'CONFIRMADA',
                'usuario_confirma_rut' => Auth::id(),
                'fecha_confirmacion' => now(),
            ]);
            $audit->record('CONFIRMACION_UNIDOSIS', 'preparaciones_unidosis', $preparationId, 'EXITOSO');
        });

        return ($request->routeIs('api.v1.*') || $request->expectsJson())
            ? response()->json(['data' => ['message' => 'Preparación de unidosis confirmada por el Químico Farmacéutico.']])
            : redirect()->route('carts.unidosis.index')->with('status', 'Preparación de unidosis confirmada por el Químico Farmacéutico.');
    }

    public function dispenseFromParo(Request $request, AuditLogger $audit, StockAlertService $alerts): RedirectResponse|JsonResponse
    {
        $data = $request->validate([
            'lote_id' => ['required', 'integer', 'exists:lotes,lote_id'],
            'cantidad' => ['required', 'numeric', 'decimal:0,2', 'gt:0'],
            'observacion' => ['required', 'string', 'max:1000'],
        ]);

        DB::transaction(function () use ($data, $audit, $alerts) {
            $cart = DB::table('carros')->join('ubicaciones', 'ubicaciones.ubicacion_id', '=', 'carros.ubicacion_id')
                ->where('carros.tipo_carro', 'PARO')
                ->first(['carros.carro_id', 'ubicaciones.ubicacion_id']);
            if (! $cart) {
                throw ValidationException::withMessages(['lote_id' => 'No hay un Carro de Paro configurado.']);
            }

            $lot = DB::table('inventario')
                ->join('lotes', 'lotes.lote_id', '=', 'inventario.lote_id')
                ->join('productos', 'productos.producto_id', '=', 'lotes.producto_id')
                ->where('inventario.lote_id', $data['lote_id'])
                ->where('inventario.ubicacion_id', $cart->ubicacion_id)
                ->where('productos.es_controlado', false)
                ->where('productos.activo', true)
                ->where('lotes.estado', 'ACTIVO')
                ->whereDate('lotes.fecha_vencimiento', '>=', now()->toDateString())
                ->lockForUpdate()
                ->first(['inventario.inventario_id', 'inventario.cantidad', 'inventario.ubicacion_id', 'lotes.lote_id', 'lotes.producto_id']);
            if (! $lot || (float) $lot->cantidad < (float) $data['cantidad']) {
                throw ValidationException::withMessages(['cantidad' => 'El Carro de Paro no tiene stock suficiente para esta salida.']);
            }

            DB::table('inventario')->where('inventario_id', $lot->inventario_id)->decrement('cantidad', $data['cantidad']);
            $movementId = DB::table('movimientos_inventario')->insertGetId([
                'lote_id' => $lot->lote_id,
                'ubicacion_id' => $lot->ubicacion_id,
                'tipo_movimiento' => 'SALIDA',
                'cantidad' => $data['cantidad'],
                ...InventoryQuantitySnapshot::forBaseQuantity((int) $lot->producto_id, (float) $data['cantidad']),
                'usuario_rut' => Auth::id(),
                'referencia_tipo' => 'CARRO_PARO',
                'observacion' => $data['observacion'],
            ]);
            $alerts->check($lot->lote_id, $lot->ubicacion_id);
            $alerts->checkForReplenishment($lot->producto_id, (string) Auth::id());
            $audit->record('SALIDA_CARRO_PARO', 'movimientos_inventario', $movementId, 'EXITOSO', $data);
        });

        return ($request->routeIs('api.v1.*') || $request->expectsJson())
            ? response()->json(['data' => ['message' => 'Uso del Carro de Paro registrado y descontado del inventario.']], 201)
            : redirect()->route('carts.usage.index')->with('status', 'Uso del Carro de Paro registrado y descontado del inventario.');
    }
}
