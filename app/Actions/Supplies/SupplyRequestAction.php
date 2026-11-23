<?php

namespace App\Actions\Supplies;

use App\Models\Usuario;
use App\Services\Audit\AuditLogger;
use App\Services\Notifications\UserNotificationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class SupplyRequestAction
{
    public function storeRequest(Request $request, AuditLogger $audit, UserNotificationService $notifications): RedirectResponse|JsonResponse
    {
        $this->authorizeSupplyOperation($audit, 'supplies.request', 'solicitudes_reposicion');
        $data = $request->validate([
            'detalles' => ['required', 'array', 'min:1', 'max:100'],
            'detalles.*.producto_id' => ['required', 'integer', 'exists:productos,producto_id,activo,1'],
            'detalles.*.cantidad_solicitada' => ['required', 'numeric', 'decimal:0,2', 'gt:0'],
        ]);

        $productIds = array_map('intval', array_column($data['detalles'], 'producto_id'));
        if (count($productIds) !== count(array_unique($productIds))) {
            throw ValidationException::withMessages([
                'detalles' => 'Cada producto debe aparecer una sola vez por solicitud. Elimina la línea repetida y suma su cantidad en una sola línea.',
            ]);
        }

        $requestId = DB::transaction(function () use ($data, $audit, $notifications, $productIds) {
            foreach ($data['detalles'] as $detail) {
                $activeProduct = DB::table('productos')->where('producto_id', $detail['producto_id'])
                    ->where('activo', true)->lockForUpdate()->first(['producto_id']);
                if (! $activeProduct) {
                    throw ValidationException::withMessages([
                        'detalles' => 'No se puede solicitar reposición de un producto inactivo.',
                    ]);
                }
            }

            $requestId = DB::table('solicitudes_reposicion')->insertGetId([
                'estado' => 'PENDIENTE',
                'usuario_rut' => Auth::id(),
            ]);
            foreach ($data['detalles'] as $detail) {
                DB::table('solicitud_reposicion_detalle')->insert([
                    'solicitud_id' => $requestId,
                    'producto_id' => $detail['producto_id'],
                    'cantidad_solicitada' => $detail['cantidad_solicitada'],
                ]);
            }

            $audit->record('SOLICITUD_REPOSICION', 'solicitudes_reposicion', $requestId, 'EXITOSO', [
                'lineas' => count($data['detalles']),
            ]);
            $notifications->replenishmentRequested($requestId, $productIds);

            return $requestId;
        });

        if ($request->expectsJson()) {
            return response()->json(['message' => 'Solicitud de reposición creada.', 'solicitud_id' => $requestId], 201);
        }

        return redirect()->route('supplies.requests.index')->with('status', 'Solicitud de reposición creada.');
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
