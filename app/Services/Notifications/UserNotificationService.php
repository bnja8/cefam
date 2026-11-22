<?php

namespace App\Services\Notifications;

use App\Support\Formatting\NumberFormat;
use App\Support\People\PersonName;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use InvalidArgumentException;
use RuntimeException;

class UserNotificationService
{
    public function create(
        string $userRut,
        string $type,
        string $title,
        string $message,
        ?string $actionLabel = null,
        ?string $actionUrl = null
    ): void {
        if (trim($userRut) === '') {
            throw new InvalidArgumentException('A notification recipient is required.');
        }

        DB::table('notificaciones')->insert([
            'usuario_rut' => $userRut,
            'tipo' => $type,
            'titulo' => $title,
            'mensaje' => $message,
            'accion_texto' => $actionLabel,
            'accion_url' => $actionUrl,
            'created_at' => now(),
        ]);
    }

    public function alertAssigned(int $alertId, string $newResponsibleRut, ?string $previousResponsibleRut): void
    {
        if ($newResponsibleRut === $previousResponsibleRut) {
            return;
        }

        $alert = DB::table('alertas')
            ->leftJoin('productos', 'productos.producto_id', '=', 'alertas.producto_id')
            ->leftJoin('lotes', 'lotes.lote_id', '=', 'alertas.lote_id')
            ->leftJoin('productos as lote_productos', 'lote_productos.producto_id', '=', 'lotes.producto_id')
            ->leftJoin('ubicaciones', 'ubicaciones.ubicacion_id', '=', 'alertas.ubicacion_id')
            ->where('alertas.alerta_id', $alertId)
            ->first([
                'alertas.tipo_alerta',
                'lotes.numero_lote',
                'lotes.fecha_vencimiento',
                DB::raw('COALESCE(productos.nombre, lote_productos.nombre) as producto'),
                'ubicaciones.nombre as ubicacion',
            ]);

        if (! $alert) {
            throw new RuntimeException("Alert {$alertId} could not be loaded for notification.");
        }

        $product = $alert->producto ?: 'el producto asociado';
        $location = $alert->ubicacion ?: 'ubicación no especificada';
        $isExpiry = in_array($alert->tipo_alerta, ['VENCIMIENTO', 'VENCIDO'], true);

        if ($isExpiry) {
            $title = $alert->tipo_alerta === 'VENCIDO' ? 'Alerta de vencimiento' : 'Vencimiento próximo';
            $lot = $alert->numero_lote ? " del lote {$alert->numero_lote}" : '';
            $expiry = $alert->fecha_vencimiento ? " con vencimiento el {$alert->fecha_vencimiento}" : '';
            $message = "Se te asignó la revisión preventiva de {$product}{$lot}{$expiry}. Ubicación: {$location}.";
        } else {
            $title = 'Alerta asignada';
            $message = "Se te asignó el seguimiento del stock crítico de {$product} en {$location}. Responsabilidad: revisar existencias y coordinar la reposición.";
        }

        $actionUrl = $this->alertUrl($alertId);
        $this->create($newResponsibleRut, $isExpiry ? 'VENCIMIENTO_ASIGNADO' : 'ALERTA_ASIGNADA', $title, $message, 'Revisar alerta', $actionUrl);

        if ($previousResponsibleRut) {
            $newResponsible = DB::table('usuarios')
                ->where('rut', $newResponsibleRut)
                ->first(['nombre', 'apellidoPaterno', 'apellidoMaterno']);
            $newResponsibleName = $newResponsible ? PersonName::format($newResponsible) : 'otro responsable';

            $this->create(
                $previousResponsibleRut,
                'ALERTA_REASIGNADA',
                'Responsabilidad reasignada',
                "La alerta de {$product} en {$location} fue reasignada a {$newResponsibleName}; ya no está bajo tu responsabilidad.",
                'Ver alerta',
                $actionUrl
            );
        }
    }

    public function alertResolved(int $alertId): void
    {
        $alert = DB::table('alertas')
            ->leftJoin('productos', 'productos.producto_id', '=', 'alertas.producto_id')
            ->leftJoin('lotes', 'lotes.lote_id', '=', 'alertas.lote_id')
            ->leftJoin('productos as lote_productos', 'lote_productos.producto_id', '=', 'lotes.producto_id')
            ->leftJoin('ubicaciones', 'ubicaciones.ubicacion_id', '=', 'alertas.ubicacion_id')
            ->where('alertas.alerta_id', $alertId)
            ->first([
                'alertas.responsable_rut',
                'alertas.tipo_alerta',
                'lotes.numero_lote',
                DB::raw('COALESCE(productos.nombre, lote_productos.nombre) as producto'),
                'ubicaciones.nombre as ubicacion',
            ]);

        if (! $alert || ! $alert->responsable_rut) {
            return;
        }

        $product = $alert->producto ?: 'el producto asociado';
        $location = $alert->ubicacion ?: 'la ubicación correspondiente';
        $message = in_array($alert->tipo_alerta, ['VENCIMIENTO', 'VENCIDO'], true)
            ? "La alerta del lote {$alert->numero_lote} de {$product} en {$location} se cerró automáticamente."
            : "El stock de {$product} en {$location} se normalizó y la alerta se cerró automáticamente.";

        $this->create(
            $alert->responsable_rut,
            'ALERTA_RESUELTA',
            'Alerta resuelta',
            $message,
            'Consultar alerta',
            $this->alertUrl($alertId)
        );
    }

    public function replenishmentRequested(int $requestId, array $productIds): void
    {
        if ($productIds === []) {
            return;
        }

        $requestedLines = DB::table('solicitud_reposicion_detalle')
            ->join('productos', 'productos.producto_id', '=', 'solicitud_reposicion_detalle.producto_id')
            ->where('solicitud_reposicion_detalle.solicitud_id', $requestId)
            ->get([
                'solicitud_reposicion_detalle.producto_id',
                'productos.nombre',
                'solicitud_reposicion_detalle.cantidad_solicitada',
            ]);

        $responsibilities = DB::table('alertas')
            ->leftJoin('lotes', 'lotes.lote_id', '=', 'alertas.lote_id')
            ->whereNull('alertas.resuelta_at')
            ->whereNotNull('alertas.responsable_rut')
            ->where(function ($query) use ($productIds) {
                $query->whereIn('alertas.producto_id', $productIds)
                    ->orWhereIn('lotes.producto_id', $productIds);
            })
            ->get([
                'alertas.responsable_rut',
                DB::raw('COALESCE(alertas.producto_id, lotes.producto_id) as producto_id'),
            ])
            ->groupBy('responsable_rut');

        $requestCode = 'RE-'.now()->format('Y').'-'.str_pad((string) $requestId, 3, '0', STR_PAD_LEFT);
        $actionUrl = Route::has('supplies.requests.index')
            ? route('supplies.requests.index', ['buscar_solicitud' => (string) $requestId], false)
            : '/supplies?screen=requests&buscar_solicitud='.$requestId;

        foreach ($responsibilities as $userRut => $rows) {
            $assignedProductIds = $rows->pluck('producto_id')->unique()->map(fn ($id) => (int) $id);
            $lines = $requestedLines
                ->whereIn('producto_id', $assignedProductIds)
                ->map(fn ($line) => "{$line->nombre} · ".NumberFormat::format((float) $line->cantidad_solicitada).' unidades')
                ->implode('; ');

            if ($lines === '') {
                continue;
            }

            $this->create(
                (string) $userRut,
                'REPOSICION_PENDIENTE',
                'Reposición pendiente',
                "La solicitud {$requestCode} está pendiente de gestión. Productos: {$lines}. Debes dar seguimiento y registrar la recepción de la remesa cuando esté disponible.",
                'Ver solicitud y recepción',
                $actionUrl
            );
        }
    }

    private function alertUrl(int $alertId): string
    {
        return Route::has('alerts.show')
            ? route('alerts.show', $alertId, false)
            : '/alertas/'.$alertId;
    }
}
