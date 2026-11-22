<?php

namespace App\Http\Controllers\Alerts;

use App\Http\Controllers\Controller;
use App\Rules\FourDigitDate;
use App\Services\Alerts\AlertQueryService;
use App\Services\Audit\AuditLogger;
use App\Services\Alerts\StockAlertService;
use App\Services\Notifications\UserNotificationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class AlertController extends Controller
{
    private const REPLENISHMENT_ROLES = ['Administrador', 'Químico Farmacéutico', 'Técnico Farmacéutico'];

    private const ALERT_MANAGEMENT_ROLES = ['Administrador', 'Químico Farmacéutico'];

    public function home(): RedirectResponse
    {
        return redirect()->route('alerts.map');
    }

    public function index(Request $request, StockAlertService $alerts, AlertQueryService $queries): View|JsonResponse
    {
        $alerts->refreshConfiguredStockAlerts();
        $alerts->refreshExpiringAlerts();
        $isTechnician = $this->isTechnician();

        $filters = $request->validate([
            'buscar_alerta' => ['nullable', 'string', 'max:100'],
            'prioridad' => ['nullable', Rule::in(['CRITICA', 'ALTA', 'MEDIA', 'VENCIMIENTO', 'VENCIDO', 'CONCILIACION'])],
            'tipo_alerta' => ['nullable', Rule::in(['SIN_EXISTENCIAS', 'STOCK_CRITICO', 'STOCK_BAJO', 'VENCIMIENTO', 'VENCIDO', 'DESCUADRE_CONTROLADO'])],
            'estado' => ['nullable', Rule::in(['pendientes', 'atendidas'])],
            'ubicacion_id' => ['nullable', 'integer', Rule::exists('ubicaciones', 'ubicacion_id')],
            'fecha_desde' => ['nullable', 'bail', new FourDigitDate()],
            'fecha_hasta' => ['nullable', 'bail', new FourDigitDate(), 'after_or_equal:fecha_desde'],
            'responsable_rut' => ['nullable', 'string', 'exists:usuarios,rut'],
            'estado_operativo' => ['nullable', Rule::in(['ACTIVA', 'EN_SEGUIMIENTO'])],
        ]);
        $alerts = $queries->alerts($filters, $isTechnician, Auth::id());
        $summary = ['pendientes' => $queries->pendingCount($isTechnician, Auth::id())];

        $data = [
            'section' => 'map',
            'alerts' => $alerts,
            'alertSummary' => $summary,
            'alertFilters' => $filters,
            'isTechnician' => $isTechnician,
            'locations' => DB::table('ubicaciones')->orderBy('nombre')->get(['ubicacion_id', 'nombre']),
            'responsibles' => $isTechnician ? collect() : $queries->responsibles(self::REPLENISHMENT_ROLES),
            'canManageReplenishment' => Auth::user()?->hasAnyRole(self::REPLENISHMENT_ROLES) ?? false,
        ];

        return $request->expectsJson()
            ? response()->json(['data' => $data])
            : view('alerts.index', $data);
    }

    public function history(Request $request, AlertQueryService $queries): View|RedirectResponse|JsonResponse
    {
        if ($this->isTechnician()) {
            abort_if($request->expectsJson(), 403);

            return redirect()->route('alerts.map');
        }

        $alertHistory = $queries->history();

        $data = [
            'section' => 'history',
            'alertHistory' => $alertHistory,
        ];

        return $request->expectsJson()
            ? response()->json(['data' => $data])
            : view('alerts.index', $data);
    }

    public function show(int $alertId, Request $request, AlertQueryService $queries): View|JsonResponse
    {
        $alert = $queries->alert($alertId);
        abort_unless($alert, 404);
        if ($this->isTechnician()) {
            abort_unless((string) $alert->responsable_rut === (string) Auth::id(), 404);
        }

        $data = [
            'alert' => $alert,
            'responsibles' => $queries->responsibles(self::REPLENISHMENT_ROLES),
            'isTechnician' => $this->isTechnician(),
            'canManageAlertActions' => Auth::user()?->hasAnyRole(self::ALERT_MANAGEMENT_ROLES) ?? false,
            'canManageReplenishment' => Auth::user()?->hasAnyRole(self::REPLENISHMENT_ROLES) ?? false,
            'canRequestExpiryWithdrawal' => $alert->tipo_alerta === 'VENCIDO'
                && is_null($alert->resuelta_at)
                && (string) $alert->responsable_rut === (string) Auth::id()
                && (Auth::user()?->hasAnyRole(self::REPLENISHMENT_ROLES) ?? false),
        ];

        return $request->expectsJson()
            ? response()->json(['data' => $data])
            : view('alerts.show', $data);
    }

    public function replenish(int $alertId, AuditLogger $audit, Request $request): RedirectResponse|JsonResponse
    {
        if (! (Auth::user()?->hasAnyRole(self::REPLENISHMENT_ROLES) ?? false)) {
            $audit->record('ACCESO_DENEGADO', 'solicitudes_reposicion', $alertId, 'RECHAZADO');
            abort(403);
        }

        $alert = DB::table('alertas')->where('alerta_id', $alertId)->first();
        abort_unless($alert, 404);
        abort_unless(in_array($alert->tipo_alerta, ['SIN_EXISTENCIAS', 'STOCK_CRITICO', 'STOCK_BAJO'], true), 422, 'La reposición solo aplica a alertas de stock.');
        abort_unless($alert->producto_id, 404, 'La alerta no tiene un producto asociado para gestionar la reposición.');

        $product = DB::table('productos')
            ->where('producto_id', $alert->producto_id)
            ->where('activo', true)
            ->first(['producto_id', 'nombre']);
        abort_unless($product, 404, 'No se puede gestionar reposición para un producto inactivo.');

        $openRequest = DB::table('solicitudes_reposicion')
            ->join('solicitud_reposicion_detalle', 'solicitud_reposicion_detalle.solicitud_id', '=', 'solicitudes_reposicion.solicitud_id')
            ->where('solicitud_reposicion_detalle.producto_id', $product->producto_id)
            ->whereIn('solicitudes_reposicion.estado', ['PENDIENTE', 'SOLICITADA'])
            ->first(['solicitudes_reposicion.solicitud_id', 'solicitudes_reposicion.estado']);

        $audit->record('ALERTA_REPOSICION_NOTIFICADA', 'alertas', $alertId, 'EXITOSO', [
            'producto_id' => $product->producto_id,
            'producto' => $product->nombre,
            'ubicacion_id' => $alert->ubicacion_id,
            'solicitud_abierta' => $openRequest ? true : false,
        ]);

        $params = [
            'buscar_solicitud' => $product->nombre,
            'estado_solicitud_lista' => 'PENDIENTE',
        ];
        $message = $openRequest
            ? 'Ya existe una solicitud interna pendiente para '.e($product->nombre).'. Puedes revisarla y gestionarla desde Abastecimiento.'
            : 'Se notificó la necesidad de reposición de '.e($product->nombre).'. Debe gestionarla desde Abastecimiento para crear la solicitud correspondiente.';

        return $request->expectsJson()
            ? response()->json(['data' => ['message' => $message, 'action_url' => route('supplies.requests.index', $params)]])
            : redirect()->route('supplies.requests.index', $params)->with('status', $message);
    }

    public function requestExpiryWithdrawal(int $alertId, Request $request): RedirectResponse|JsonResponse
    {
        $alert = DB::table('alertas')
            ->where('alerta_id', $alertId)
            ->first([
                'alerta_id',
                'tipo_alerta',
                'resuelta_at',
                'responsable_rut',
                'lote_id',
                'ubicacion_id',
            ]);

        abort_unless($alert, 404);
        abort_unless($alert->tipo_alerta === 'VENCIDO', 422, 'Solo se puede solicitar el retiro de un lote vencido.');
        abort_unless($alert->resuelta_at === null, 422, 'La alerta ya fue resuelta.');
        abort_unless((string) $alert->responsable_rut === (string) Auth::id(), 403, 'Solo el responsable asignado puede solicitar este retiro.');
        abort_unless(
            Auth::user()?->hasAnyRole(['Administrador', 'Químico Farmacéutico', 'Técnico Farmacéutico']),
            403
        );
        abort_unless($alert->lote_id && $alert->ubicacion_id, 422, 'La alerta no tiene un lote y ubicación para gestionar.');

        $inventory = DB::table('inventario')
            ->where('lote_id', $alert->lote_id)
            ->where('ubicacion_id', $alert->ubicacion_id)
            ->first(['cantidad']);
        abort_unless($inventory && (float) $inventory->cantidad > 0, 422, 'No hay existencias físicas en esa ubicación para solicitar su retiro.');

        $lot = DB::table('lotes')->where('lote_id', $alert->lote_id)->first(['numero_lote']);
        abort_unless($lot, 404);

        $params = [
            'lote_id' => $alert->lote_id,
            'ubicacion_id' => $alert->ubicacion_id,
            'tipo_ajuste' => 'SALIDA',
            'cantidad' => $inventory->cantidad,
            'motivo' => "Retiro de medicamento vencido: lote {$lot->numero_lote} (alerta #{$alertId}).",
        ];

        return $request->expectsJson()
            ? response()->json(['data' => ['action_url' => route('inventory.adjust.form', $params)]])
            : redirect()->route('inventory.adjust.form', $params);
    }

    // Asigna o reasigna una alerta conservando el historial.
    public function assign(int $alertId, Request $request, AuditLogger $audit, UserNotificationService $notifications): RedirectResponse|JsonResponse
    {
        abort_unless(Auth::user()?->hasAnyRole(self::ALERT_MANAGEMENT_ROLES), 403);
        $data = $request->validate(['responsable_rut' => ['required', 'string', 'exists:usuarios,rut']]);
        $authorized = DB::table('usuarios')
            ->join('usuario_rol', 'usuario_rol.usuario_rut', '=', 'usuarios.rut')
            ->join('roles', 'roles.rol_id', '=', 'usuario_rol.rol_id')
            ->where('usuarios.rut', $data['responsable_rut'])
            ->whereIn('roles.nombre', ['Administrador', 'Químico Farmacéutico', 'Técnico Farmacéutico'])
            ->exists();
        abort_unless($authorized, 422, 'El responsable debe ser Administrador, Químico Farmacéutico o Técnico Farmacéutico.');

        DB::transaction(function () use ($alertId, $data, $audit, $notifications) {
            $alert = DB::table('alertas')->where('alerta_id', $alertId)->lockForUpdate()->first();
            abort_unless($alert, 404);
            abort_unless(is_null($alert->resuelta_at), 422, 'Una alerta resuelta no puede reasignarse.');
            $previous = $alert->responsable_rut;
            DB::table('alertas')->where('alerta_id', $alertId)->update([
                'responsable_rut' => $data['responsable_rut'],
                'ultima_evaluacion_en' => now(),
            ]);
            $this->recordAlertHistory($alertId, 'ASIGNACION', $alert->estado ?? 'ACTIVA', $alert->estado ?? 'ACTIVA', $previous, $data['responsable_rut'], 'Asignación o reasignación de responsable.');
            $audit->record('ASIGNACION_ALERTA', 'alertas', $alertId, 'EXITOSO', ['responsable_anterior' => $previous, 'responsable_nuevo' => $data['responsable_rut']]);
            $notifications->alertAssigned($alertId, $data['responsable_rut'], $previous);
        });

        return $request->expectsJson()
            ? response()->json(['data' => ['message' => 'Responsable de la alerta actualizado.']])
            : redirect()->route('alerts.show', $alertId)->with('status', 'Responsable de la alerta actualizado.');
    }

    // Reconoce una alerta sin resolverla ni modificar el inventario.
    public function acknowledge(int $alertId, AuditLogger $audit, Request $request): RedirectResponse|JsonResponse
    {
        abort_unless(Auth::user()?->hasAnyRole(self::ALERT_MANAGEMENT_ROLES), 403);
        DB::transaction(function () use ($alertId, $audit) {
            $alert = DB::table('alertas')->where('alerta_id', $alertId)->lockForUpdate()->first();
            abort_unless($alert, 404);
            abort_unless(is_null($alert->resuelta_at), 422, 'Una alerta resuelta no puede reconocerse.');
            if (! is_null($alert->reconocida_en)) {
                return;
            }
            DB::table('alertas')->where('alerta_id', $alertId)->update([
                'reconocida_por_rut' => Auth::id(),
                'reconocida_en' => now(),
                'ultima_evaluacion_en' => now(),
            ]);
            $this->recordAlertHistory($alertId, 'RECONOCIMIENTO', $alert->estado ?? 'ACTIVA', $alert->estado ?? 'ACTIVA', $alert->responsable_rut, $alert->responsable_rut, 'El usuario tomó conocimiento de la alerta.');
            $audit->record('RECONOCIMIENTO_ALERTA', 'alertas', $alertId, 'EXITOSO');
        });

        return $request->expectsJson()
            ? response()->json(['data' => ['message' => 'Alerta reconocida.']])
            : redirect()->route('alerts.show', $alertId)->with('status', 'Alerta reconocida.');
    }

    // Registra seguimiento operativo sin dar por resuelta la condición.
    public function follow(int $alertId, Request $request, AuditLogger $audit): RedirectResponse|JsonResponse
    {
        abort_unless(Auth::user()?->hasAnyRole(self::ALERT_MANAGEMENT_ROLES), 403);
        $data = $request->validate(['observacion' => ['required', 'string', 'max:1000']]);
        DB::transaction(function () use ($alertId, $data, $audit) {
            $alert = DB::table('alertas')->where('alerta_id', $alertId)->lockForUpdate()->first();
            abort_unless($alert, 404);
            abort_unless(is_null($alert->resuelta_at), 422, 'Una alerta resuelta no admite seguimiento operativo.');
            $previous = $alert->estado ?: 'ACTIVA';
            DB::table('alertas')->where('alerta_id', $alertId)->update(['estado' => 'EN_SEGUIMIENTO', 'ultima_evaluacion_en' => now()]);
            $this->recordAlertHistory($alertId, 'SEGUIMIENTO', $previous, 'EN_SEGUIMIENTO', $alert->responsable_rut, $alert->responsable_rut, $data['observacion']);
            $audit->record('SEGUIMIENTO_ALERTA', 'alertas', $alertId, 'EXITOSO', ['observacion' => $data['observacion']]);
        });

        return $request->expectsJson()
            ? response()->json(['data' => ['message' => 'Seguimiento registrado.']])
            : redirect()->route('alerts.show', $alertId)->with('status', 'Seguimiento registrado.');
    }

    private function recordAlertHistory(int $alertId, string $event, ?string $previousState, ?string $newState, ?string $previousResponsible, ?string $newResponsible, string $description): void
    {
        DB::table('historial_alertas')->insert([
            'alerta_id' => $alertId,
            'usuario_rut' => Auth::id(),
            'tipo_evento' => $event,
            'estado_anterior' => $previousState,
            'estado_nuevo' => $newState,
            'responsable_anterior_rut' => $previousResponsible,
            'responsable_nuevo_rut' => $newResponsible,
            'descripcion' => $description,
            'created_at' => now(),
        ]);
    }

    private function isTechnician(): bool
    {
        return Auth::user()?->hasAnyRole(['Técnico Farmacéutico']) ?? false;
    }
}
