<?php

namespace App\Services\Alerts;

use App\Services\Notifications\UserNotificationService;
use Illuminate\Support\Facades\DB;

class StockExpiryAlertService
{
    public function __construct(private readonly UserNotificationService $notifications) {}

    public function refreshExpiringAlerts(): void
    {
        DB::transaction(function () {
            $lastAlertDay = now()->addDays(30)->toDateString();

            DB::table('inventario')
                ->join('lotes', 'lotes.lote_id', '=', 'inventario.lote_id')
                ->join('productos', 'productos.producto_id', '=', 'lotes.producto_id')
                ->join('ubicaciones', 'ubicaciones.ubicacion_id', '=', 'inventario.ubicacion_id')
                ->where('inventario.cantidad', '>', 0)
                ->whereDate('lotes.fecha_vencimiento', '<=', $lastAlertDay)
                ->orderBy('lotes.lote_id')
                ->orderBy('inventario.ubicacion_id')
                ->get([
                    'lotes.lote_id',
                    'lotes.numero_lote',
                    'lotes.fecha_vencimiento',
                    'lotes.producto_id',
                    'productos.nombre as producto',
                    'inventario.cantidad',
                    'inventario.ubicacion_id',
                    'ubicaciones.nombre as ubicacion',
                ])
                ->each(fn ($lot) => $this->syncExpiryAlert($lot->lote_id, $lot->ubicacion_id, $lot));

            $resolvedAlertIds = DB::table('alertas')
                ->whereIn('tipo_alerta', ['VENCIMIENTO', 'VENCIDO'])
                ->whereNull('resuelta_at')
                ->whereNotExists(function ($query) use ($lastAlertDay) {
                    $query->selectRaw('1')
                        ->from('inventario')
                        ->join('lotes', 'lotes.lote_id', '=', 'inventario.lote_id')
                        ->whereColumn('inventario.lote_id', 'alertas.lote_id')
                        ->whereColumn('inventario.ubicacion_id', 'alertas.ubicacion_id')
                        ->where('inventario.cantidad', '>', 0)
                        ->whereDate('lotes.fecha_vencimiento', '<=', $lastAlertDay);
                })
                ->pluck('alerta_id');

            if ($resolvedAlertIds->isNotEmpty()) {
                DB::table('alertas')->whereIn('alerta_id', $resolvedAlertIds)->update([
                    'estado' => 'RESUELTA',
                    'resuelta_at' => now(),
                    'ultima_evaluacion_en' => now(),
                ]);
                foreach ($resolvedAlertIds as $alertId) {
                    $this->recordResolutionHistory((int) $alertId);
                }
            }
        });
    }

    public function syncExpiryAlert(int $lotId, int $locationId, object $lot): void
    {
        DB::table('lotes')
            ->where('lote_id', $lotId)
            ->lockForUpdate()
            ->first(['lote_id']);

        $alerts = DB::table('alertas')
            ->whereIn('tipo_alerta', ['VENCIMIENTO', 'VENCIDO'])
            ->where('lote_id', $lotId)
            ->where('ubicacion_id', $locationId)
            ->whereNull('resuelta_at')
            ->orderByDesc('alerta_id')
            ->lockForUpdate()
            ->get();
        $daysToExpiry = now()->startOfDay()->diffInDays($lot->fecha_vencimiento, false);
        $quantity = (float) ($lot->cantidad ?? 0);

        if ($quantity <= 0 || $daysToExpiry > 30) {
            foreach ($alerts as $alert) {
                DB::table('alertas')->where('alerta_id', $alert->alerta_id)->update([
                    'estado' => 'RESUELTA',
                    'resuelta_at' => now(),
                    'ultima_evaluacion_en' => now(),
                ]);
                $this->recordResolutionHistory((int) $alert->alerta_id);
            }

            return;
        }
        $desiredType = $daysToExpiry < 0 ? 'VENCIDO' : 'VENCIMIENTO';
        $priority = $daysToExpiry < 0 ? 'CRITICA' : ($daysToExpiry <= 7 ? 'ALTA' : 'MEDIA');
        $message = $daysToExpiry < 0
            ? "El lote {$lot->numero_lote} de {$lot->producto} venció el {$lot->fecha_vencimiento} y debe retirarse de {$lot->ubicacion}."
            : "El lote {$lot->numero_lote} de {$lot->producto} en {$lot->ubicacion} vence el {$lot->fecha_vencimiento}.";
        $same = $alerts->where('tipo_alerta', $desiredType);
        $current = $same->first();

        if (! $current) {
            $alertId = DB::table('alertas')->insertGetId([
                'tipo_alerta' => $desiredType,
                'prioridad' => $priority,
                'producto_id' => $lot->producto_id,
                'lote_id' => $lotId,
                'ubicacion_id' => $locationId,
                'estado' => 'ACTIVA',
                'clave_deduplicacion' => 'EXPIRY:'.$lotId.':'.$locationId.':'.$desiredType,
                'nivel_actual' => $quantity,
                'mensaje' => $message,
                'atendida' => false,
                'detectada_en' => now(),
                'ultima_evaluacion_en' => now(),
            ]);
            $this->recordAlertHistory($alertId, 'GENERACION', null, 'ACTIVA', $message);

            return;
        }

        DB::table('alertas')->where('alerta_id', $current->alerta_id)->update([
            'producto_id' => $lot->producto_id,
            'prioridad' => $priority,
            'nivel_actual' => $quantity,
            'mensaje' => $message,
            'ultima_evaluacion_en' => now(),
        ]);
        $otherIds = $alerts->where('tipo_alerta', '!=', $desiredType)->pluck('alerta_id');
        if ($otherIds->isNotEmpty()) {
            foreach ($otherIds as $alertId) {
                DB::table('alertas')->where('alerta_id', $alertId)->update([
                    'estado' => 'RESUELTA',
                    'resuelta_at' => now(),
                    'ultima_evaluacion_en' => now(),
                ]);
                $this->recordResolutionHistory((int) $alertId);
            }
        }
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
