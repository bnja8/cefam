<?php

namespace App\Services\Alerts;

use App\Services\Notifications\UserNotificationService;
use App\Support\Formatting\NumberFormat;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class StockThresholdAlertService
{
    public function __construct(private readonly UserNotificationService $notifications) {}

    public function refreshConfiguredStockAlerts(): Collection
    {
        return DB::transaction(function () {
            $snapshots = DB::table('vw_stock_producto_ubicacion')
                ->orderBy('producto_id')
                ->orderBy('ubicacion_id')
                ->get();

            foreach ($snapshots as $snapshot) {
                $this->syncCriticalStockAlert($snapshot);
            }

            $configuredLocations = $snapshots
                ->mapWithKeys(fn ($snapshot) => [$this->stockPairKey($snapshot->producto_id, $snapshot->ubicacion_id) => true]);
            DB::table('alertas')
                ->leftJoin('lotes', 'lotes.lote_id', '=', 'alertas.lote_id')
                ->where('tipo_alerta', 'STOCK_CRITICO')
                ->whereNull('resuelta_at')
                ->orderBy('alerta_id')
                ->get([
                    'alertas.alerta_id',
                    DB::raw('COALESCE(alertas.producto_id, lotes.producto_id) as producto_id'),
                    'alertas.ubicacion_id',
                ])
                ->each(function ($alert) use ($configuredLocations) {
                    if (! $configuredLocations->has($this->stockPairKey($alert->producto_id, $alert->ubicacion_id))) {
                        $this->resolveCriticalStockAlerts($alert->producto_id, $alert->ubicacion_id);
                    }
                });

            return $snapshots->filter(
                // Conserva los elementos que cumplen la condición.
                fn ($snapshot) => (float) $snapshot->stock_disponible <= (float) $snapshot->stock_minimo
            )->values();
        });
    }

    public function refreshProductLocation(int $productId, int $locationId): void
    {
        DB::table('productos')
            ->where('producto_id', $productId)
            ->lockForUpdate()
            ->first(['producto_id']);

        $snapshot = DB::table('vw_stock_producto_ubicacion')
            ->where('producto_id', $productId)
            ->where('ubicacion_id', $locationId)
            ->first();

        if ($snapshot) {
            $this->syncCriticalStockAlert($snapshot);
        } else {
            $this->resolveCriticalStockAlerts($productId, $locationId);
        }
    }

    private function syncCriticalStockAlert(object $snapshot): void
    {
        DB::table('productos')
            ->where('producto_id', $snapshot->producto_id)
            ->lockForUpdate()
            ->first(['producto_id']);
        $stock = (float) $snapshot->stock_disponible;
        $minimum = (float) $snapshot->stock_minimo;
        $preventiveThreshold = $minimum * 1.25;
        $desiredType = null;
        $priority = null;
        $message = null;

        if ($minimum > 0 && $stock <= 0) {
            $desiredType = 'SIN_EXISTENCIAS';
            $priority = 'CRITICA';
            $message = "Sin existencias de {$snapshot->producto} en {$snapshot->ubicacion}: 0 unidades disponibles; mínimo configurado ".NumberFormat::format($minimum).'.';
        } elseif ($minimum > 0 && $stock <= $minimum) {
            $desiredType = 'STOCK_CRITICO';
            $priority = 'ALTA';
            $message = "Stock crítico de {$snapshot->producto} en {$snapshot->ubicacion}: ".NumberFormat::format($stock).' unidades disponibles; mínimo '.NumberFormat::format($minimum).'; diferencia '.NumberFormat::format(max(0, $minimum - $stock)).'.';
        } elseif ($minimum > 0 && $stock <= $preventiveThreshold) {
            $desiredType = 'STOCK_BAJO';
            $priority = 'MEDIA';
            $message = "Stock bajo de {$snapshot->producto} en {$snapshot->ubicacion}: ".NumberFormat::format($stock).' unidades disponibles; mínimo '.NumberFormat::format($minimum).'.';
        }

        $types = ['SIN_EXISTENCIAS', 'STOCK_CRITICO', 'STOCK_BAJO'];
        $alerts = DB::table('alertas')
            ->whereIn('tipo_alerta', $types)
            ->where('ubicacion_id', $snapshot->ubicacion_id)
            ->where(function ($query) use ($snapshot) {
                $query->where('producto_id', $snapshot->producto_id)
                    ->orWhere(function ($query) use ($snapshot) {
                        $query->whereNull('producto_id')->whereIn('lote_id', DB::table('lotes')->select('lote_id')->where('producto_id', $snapshot->producto_id));
                    });
            })
            ->whereNull('resuelta_at')
            ->orderByDesc('alerta_id')
            ->lockForUpdate()
            ->get();

        if (! $desiredType) {
            if ($alerts->isNotEmpty()) {
                $ids = $alerts->pluck('alerta_id');
                DB::table('alertas')->whereIn('alerta_id', $ids)->update([
                    'estado' => 'RESUELTA',
                    'resuelta_at' => now(),
                    'ultima_evaluacion_en' => now(),
                ]);
                foreach ($ids as $alertId) {
                    $this->recordResolutionHistory((int) $alertId);
                }
            }

            return;
        }

        $same = $alerts->where('tipo_alerta', $desiredType);
        $current = $same->first();
        if (! $current) {
            $key = 'STOCK:'.$snapshot->producto_id.':'.$snapshot->ubicacion_id.':'.$desiredType;
            $alertId = DB::table('alertas')->insertGetId([
                'tipo_alerta' => $desiredType,
                'prioridad' => $priority,
                'producto_id' => $snapshot->producto_id,
                'lote_id' => null,
                'ubicacion_id' => $snapshot->ubicacion_id,
                'estado' => 'ACTIVA',
                'clave_deduplicacion' => $key,
                'nivel_actual' => $stock,
                'mensaje' => $message,
                'atendida' => false,
                'detectada_en' => now(),
                'ultima_evaluacion_en' => now(),
            ]);
            $this->recordAlertHistory($alertId, 'GENERACION', null, 'ACTIVA', $message);
        } else {
            DB::table('alertas')->where('alerta_id', $current->alerta_id)->update([
                'producto_id' => $snapshot->producto_id,
                'lote_id' => null,
                'ubicacion_id' => $snapshot->ubicacion_id,
                'prioridad' => $priority,
                'nivel_actual' => $stock,
                'mensaje' => $message,
                'ultima_evaluacion_en' => now(),
            ]);
        }

        $otherIds = $alerts->where('tipo_alerta', '!=', $desiredType)->pluck('alerta_id');
        if ($otherIds->isNotEmpty()) {
            DB::table('alertas')->whereIn('alerta_id', $otherIds)->update([
                'estado' => 'RESUELTA',
                'resuelta_at' => now(),
                'ultima_evaluacion_en' => now(),
            ]);
            foreach ($otherIds as $alertId) {
                $this->recordResolutionHistory((int) $alertId);
            }
        }
    }

    private function resolveCriticalStockAlerts(int $productId, int $locationId): void
    {
        DB::table('alertas')
            ->whereIn('tipo_alerta', ['SIN_EXISTENCIAS', 'STOCK_CRITICO', 'STOCK_BAJO'])
            ->where('ubicacion_id', $locationId)
            ->where(function ($query) use ($productId) {
                $query->where('producto_id', $productId)
                    ->orWhere(function ($query) use ($productId) {
                        $query->whereNull('producto_id')
                            ->whereIn('lote_id', DB::table('lotes')
                                ->select('lote_id')
                                ->where('producto_id', $productId));
                    });
            })
            ->whereNull('resuelta_at')
            ->get(['alerta_id'])
            ->each(function ($alert) {
                DB::table('alertas')->where('alerta_id', $alert->alerta_id)->update([
                    'estado' => 'RESUELTA',
                    'resuelta_at' => now(),
                    'ultima_evaluacion_en' => now(),
                ]);
                $this->recordResolutionHistory((int) $alert->alerta_id);
            });
    }

    private function stockPairKey(int|string|null $productId, int|string|null $locationId): string
    {
        return $productId.':'.$locationId;
    }

    private function recordResolutionHistory(int $alertId): void
    {
        $this->recordAlertHistory($alertId, 'RESOLUCION', 'ACTIVA', 'RESUELTA', 'Sistema verificó que desapareció la condición detectada.');
        $this->notifications->alertResolved($alertId);
    }

    private function recordAlertHistory(int $alertId, string $event, ?string $previousState, ?string $newState, string $description): void
    {
        DB::table('historial_alertas')->insert([
            'alerta_id' => $alertId,
            'usuario_rut' => null,
            'tipo_evento' => $event,
            'estado_anterior' => $previousState,
            'estado_nuevo' => $newState,
            'descripcion' => $description,
            'created_at' => now(),
        ]);
    }
}
