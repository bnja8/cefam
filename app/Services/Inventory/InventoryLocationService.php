<?php

namespace App\Services\Inventory;

use App\Services\Audit\AuditLogger;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class InventoryLocationService
{
    public function __construct(private readonly AuditLogger $audit)
    {
    }

    public function paginate(): LengthAwarePaginator
    {
        return DB::table('ubicaciones')->orderBy('nombre')->paginate(25);
    }

    // Obtiene una ubicación y determina si su tipo está bloqueado por historial.
    public function formData(int $locationId): array
    {
        $location = DB::table('ubicaciones')->where('ubicacion_id', $locationId)->first();
        if (! $location) {
            throw new ModelNotFoundException('No se encontró la ubicación solicitada.');
        }

        return [
            'location' => $location,
            'typeLocked' => $this->hasLocationHistory($locationId),
        ];
    }

    // Crea una ubicación y su carro asociado dentro de una transacción.
    public function create(array $data): int
    {
        return DB::transaction(function () use ($data): int {
            $locationId = DB::table('ubicaciones')->insertGetId($data);
            $this->syncCart((int) $locationId, $data['nombre'], $data['tipo']);
            $this->audit->record('CREACION', 'ubicaciones', $locationId, 'EXITOSO', $data);

            return (int) $locationId;
        });
    }

    // Actualiza la ubicación sin cambiar el tipo si existe historial operativo.
    public function update(int $locationId, array $data): void
    {
        DB::transaction(function () use ($data, $locationId): void {
            $location = DB::table('ubicaciones')
                ->where('ubicacion_id', $locationId)
                ->lockForUpdate()
                ->first();
            if (! $location) {
                throw new ModelNotFoundException('No se encontró la ubicación solicitada.');
            }

            if ($location->tipo !== $data['tipo'] && $this->hasLocationHistory($locationId)) {
                throw ValidationException::withMessages([
                    'tipo' => 'No se puede cambiar el tipo de una ubicación con existencias, movimientos o carros asociados.',
                ]);
            }

            DB::table('ubicaciones')->where('ubicacion_id', $locationId)->update($data);
            $this->syncCart($locationId, $data['nombre'], $data['tipo']);
            $this->audit->record('ACTUALIZACION', 'ubicaciones', $locationId, 'EXITOSO', [
                'nombre' => $data['nombre'],
                'tipo' => $data['tipo'],
                'descripcion' => $data['descripcion'] ?? null,
            ]);
        });
    }

    // Sincroniza las ubicaciones de carro con su registro operativo.
    private function syncCart(int $locationId, string $name, string $locationType): void
    {
        $cart = DB::table('carros')
            ->where('ubicacion_id', $locationId)
            ->lockForUpdate()
            ->first(['carro_id']);
        $cartType = match ($locationType) {
            'CARRO_PARO' => 'PARO',
            'CARRO_UNIDOSIS' => 'UNIDOSIS',
            default => null,
        };

        if ($cartType === null) {
            if ($cart) {
                throw ValidationException::withMessages([
                    'tipo' => 'No se puede quitar el tipo de carro de una ubicación asociada a un carro.',
                ]);
            }

            return;
        }

        $duplicateCart = DB::table('carros')
            ->where('nombre', $name)
            ->when($cart, fn ($query) => $query->where('carro_id', '<>', $cart->carro_id))
            ->exists();
        if ($duplicateCart) {
            throw ValidationException::withMessages([
                'nombre' => 'Ya existe un carro con este nombre.',
            ]);
        }

        if ($cart) {
            DB::table('carros')->where('carro_id', $cart->carro_id)->update([
                'tipo_carro' => $cartType,
                'nombre' => $name,
            ]);

            return;
        }

        DB::table('carros')->insert([
            'ubicacion_id' => $locationId,
            'tipo_carro' => $cartType,
            'nombre' => $name,
        ]);
    }

    // Indica si existencias o movimientos impiden cambiar el tipo de ubicación.
    private function hasLocationHistory(int $locationId): bool
    {
        return DB::table('inventario')->where('ubicacion_id', $locationId)->exists()
            || DB::table('movimientos_inventario')->where('ubicacion_id', $locationId)->exists()
            || DB::table('stock_minimos_ubicacion')->where('ubicacion_id', $locationId)->exists()
            || DB::table('alertas')->where('ubicacion_id', $locationId)->exists()
            || DB::table('carros')->where('ubicacion_id', $locationId)->exists();
    }
}
