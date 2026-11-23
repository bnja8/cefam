<?php

namespace App\Http\Controllers\Inventory;

use App\Http\Controllers\Controller;
use App\Http\Requests\Inventory\StoreLocationRequest;
use App\Http\Requests\Inventory\UpdateLocationRequest;
use App\Models\Usuario;
use App\Services\Audit\AuditLogger;
use App\Services\Inventory\InventoryLocationService;
use App\Support\Inventory\InventoryLocationTypes;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class LocationController extends Controller
{
    public function index(Request $request, AuditLogger $audit, InventoryLocationService $locations): View|JsonResponse
    {
        $this->authorizeLocationManager($audit, 'inventory.catalog.view');
        $locationPage = $locations->paginate()->withQueryString();

        if ($request->expectsJson()) {
            return response()->json(['data' => $locationPage, 'types' => InventoryLocationTypes::OPTIONS]);
        }

        return view('inventory.locations.index', [
            'locations' => $locationPage,
            'types' => InventoryLocationTypes::OPTIONS,
            'canManageLocations' => Auth::user()?->hasPermission('inventory.catalog.manage') ?? false,
        ]);
    }

    public function create(Request $request, AuditLogger $audit): View|JsonResponse
    {
        $this->authorizeLocationManager($audit, 'inventory.catalog.manage');
        if ($request->expectsJson()) {
            return response()->json(['data' => [
                'location' => null,
                'types' => InventoryLocationTypes::OPTIONS,
                'typeLocked' => false,
            ]]);
        }

        return view('inventory.locations.form', [
            'location' => null,
            'types' => InventoryLocationTypes::OPTIONS,
            'typeLocked' => false,
        ]);
    }

    // Registra una ubicación y sincroniza su eventual carro asociado.
    public function store(
        StoreLocationRequest $request,
        InventoryLocationService $locations
    ): RedirectResponse|JsonResponse {
        $locationId = $locations->create($request->validated());

        if ($request->expectsJson()) {
            return response()->json(['message' => 'Ubicación agregada correctamente.', 'ubicacion_id' => $locationId], 201);
        }

        return redirect()->route('locations.index')->with('status', 'Ubicación agregada correctamente.');
    }

    public function edit(
        int $locationId,
        Request $request,
        AuditLogger $audit,
        InventoryLocationService $locations
    ): View|JsonResponse {
        $this->authorizeLocationManager($audit, 'inventory.catalog.manage', $locationId);
        $data = $locations->formData($locationId);

        if ($request->expectsJson()) {
            return response()->json(['data' => [
                ...$data,
                'types' => InventoryLocationTypes::OPTIONS,
            ]]);
        }

        return view('inventory.locations.form', [
            ...$data,
            'types' => InventoryLocationTypes::OPTIONS,
        ]);
    }

    // Actualiza la ubicación y sincroniza el carro dentro de una transacción.
    public function update(
        UpdateLocationRequest $request,
        int $locationId,
        InventoryLocationService $locations
    ): RedirectResponse|JsonResponse {
        $locations->update($locationId, $request->validated());

        if ($request->expectsJson()) {
            return response()->json(['message' => 'Ubicación actualizada correctamente.', 'ubicacion_id' => $locationId]);
        }

        return redirect()->route('locations.index')->with('status', 'Ubicación actualizada correctamente.');
    }

    // Autoriza la gestión de ubicaciones y audita denegaciones.
    private function authorizeLocationManager(AuditLogger $audit, string $permission, ?int $locationId = null): void
    {
        $user = Auth::user();
        if (! $user instanceof Usuario || ! $user->hasPermission($permission)) {
            $audit->record('ACCESO_DENEGADO', 'ubicaciones', $locationId, 'RECHAZADO');
            abort(403);
        }
    }
}
