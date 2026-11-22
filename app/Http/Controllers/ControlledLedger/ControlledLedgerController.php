<?php


namespace App\Http\Controllers\ControlledLedger;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreControlledCountRequest;
use App\Models\Usuario;
use App\Services\Audit\AuditLogger;
use App\Services\ControlledLedger\ControlledInventoryReconciliationService;
use App\Services\Alerts\StockAlertService;
use App\Support\Inventory\InventoryQuantitySnapshot;
use App\Support\People\PersonName;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class ControlledLedgerController extends Controller
{
    public function index(AuditLogger $audit, Request $request): View|JsonResponse
    {
        $this->authorizePharmacist($audit);
        $screen = $request->route('screen', 'movements');
        if (in_array($screen, ['create', 'reconcile'], true)) {
            $products = DB::table('productos')
                ->where('activo', true)
                ->where('es_controlado', true)
                ->orderBy('nombre')
                ->get(['producto_id', 'nombre']);
            $lotsQuery = DB::table('lotes')
                ->join('productos', 'productos.producto_id', '=', 'lotes.producto_id')
                ->where('productos.es_controlado', true)
                ->where('productos.activo', true)
                ->where('lotes.estado', 'ACTIVO')
                ->orderBy('lotes.fecha_vencimiento')
                ->select(['lotes.lote_id', 'lotes.producto_id', 'lotes.numero_lote', 'lotes.fecha_vencimiento']);

            $data = [
                'screen' => $screen,
                'products' => $products,
                'lots' => $screen === 'create'
                    ? (clone $lotsQuery)->whereDate('lotes.fecha_vencimiento', '>=', now()->toDateString())->get()
                    : collect(),
                'countLots' => $screen === 'reconcile' ? $lotsQuery->get() : collect(),
                'locations' => DB::table('ubicaciones')->orderBy('nombre')->get(['ubicacion_id', 'nombre']),
            ];

            return ($request->routeIs('api.v1.*') || $request->expectsJson())
                ? response()->json(['data' => $data])
                : view('controlled-ledger.index', $data);
        }

        $movements = DB::table('movimientos_controlados')
            ->join('productos', 'productos.producto_id', '=', 'movimientos_controlados.producto_id')
            ->join('lotes', 'lotes.lote_id', '=', 'movimientos_controlados.lote_id')
            ->join('usuarios as responsable', 'responsable.rut', '=', 'movimientos_controlados.usuario_responsable_rut')
            ->leftJoin('usuarios as autorizador', 'autorizador.rut', '=', 'movimientos_controlados.usuario_autoriza_rut')
            ->orderByDesc('movimientos_controlados.fecha_hora')
            ->orderByDesc('movimientos_controlados.movimiento_controlado_id')
            ->paginate(25, [
                'movimientos_controlados.fecha_hora',
                'productos.nombre as producto',
                'lotes.numero_lote',
                'lotes.fecha_vencimiento',
                'movimientos_controlados.tipo_movimiento',
                'movimientos_controlados.cantidad',
                'movimientos_controlados.observacion',
                'movimientos_controlados.firmado',
                PersonName::expression('responsable', 'responsable'),
                PersonName::expression('autorizador', 'autorizador'),
            ])
            ->withQueryString();

        $data = [
            'screen' => 'movements',
            'movements' => $movements,
        ];

        return ($request->routeIs('api.v1.*') || $request->expectsJson())
            ? response()->json(['data' => $data])
            : view('controlled-ledger.index', $data);
    }

    public function reconcile(
        StoreControlledCountRequest $request,
        AuditLogger $audit,
        ControlledInventoryReconciliationService $reconciliation
    ): RedirectResponse|JsonResponse {
        $this->authorizePharmacist($audit);
        $user = $request->user();
        abort_unless($user instanceof Usuario, 401);
        $mismatch = $reconciliation->reconcile($request->validated(), $user);

        $message = $mismatch
            ? 'Se detectó una diferencia. La alerta quedó registrada; el stock no fue modificado automáticamente.'
            : 'El conteo físico coincide con el inventario del sistema.';

        return ($request->routeIs('api.v1.*') || $request->expectsJson())
            ? response()->json(['data' => ['diferencia' => $mismatch, 'message' => $message]])
            : redirect()->route('controlled-ledger.reconcile.form')->with($mismatch ? 'error' : 'status', $message);
    }

    // Valida y guarda un nuevo registro.
    public function store(Request $request, AuditLogger $audit, StockAlertService $alerts): RedirectResponse|JsonResponse
    {
        $this->authorizePharmacist($audit);

        $data = $request->validate([
            'producto_id' => ['required', 'integer', 'exists:productos,producto_id,activo,1'],
            'lote_id' => ['required', 'integer', 'exists:lotes,lote_id'],
            'ubicacion_id' => ['required', 'integer', 'exists:ubicaciones,ubicacion_id'],
            'tipo_movimiento' => ['required', 'in:INGRESO,SALIDA'],
            'cantidad' => ['required', 'numeric', 'decimal:0,2', 'gt:0'],
            'observacion' => ['required', 'string', 'max:1000'],
            'password' => ['required', 'string'],
        ]);

        if (! Hash::check($data['password'], Auth::user()->getAuthPassword())) {
            $audit->record('FIRMA_CONTROLADO_RECHAZADA', 'movimientos_controlados', null, 'RECHAZADO');
            throw ValidationException::withMessages(['password' => 'La clave de firma no es correcta.']);
        }

        DB::transaction( function () use ($data, $audit, $alerts) {
            $lot = DB::table('lotes')
                ->join('productos', 'productos.producto_id', '=', 'lotes.producto_id')
                ->where('lotes.lote_id', $data['lote_id'])
                ->where('productos.producto_id', $data['producto_id'])
                ->where('productos.es_controlado', true)
                ->where('productos.activo', true)
                ->whereDate('lotes.fecha_vencimiento', '>=', now()->toDateString())
                ->lockForUpdate()
                ->first(['lotes.lote_id', 'lotes.producto_id']);
            if (! $lot) {
                throw ValidationException::withMessages(['lote_id' => 'El lote no pertenece al medicamento controlado o está vencido.']);
            }

            $inventory = DB::table('inventario')
                ->where('lote_id', $lot->lote_id)
                ->where('ubicacion_id', $data['ubicacion_id'])
                ->lockForUpdate()
                ->first();
            if ($data['tipo_movimiento'] === 'SALIDA' && (! $inventory || (float) $inventory->cantidad < (float) $data['cantidad'])) {
                throw ValidationException::withMessages(['cantidad' => 'No existe stock suficiente en esa ubicación.']);
            }
            $locationName = DB::table('ubicaciones')->where('ubicacion_id', $data['ubicacion_id'])->value('nombre');

            if ($inventory) {
                $data['tipo_movimiento'] === 'INGRESO'
                    ? DB::table('inventario')->where('inventario_id', $inventory->inventario_id)->increment('cantidad', $data['cantidad'])
                    : DB::table('inventario')->where('inventario_id', $inventory->inventario_id)->decrement('cantidad', $data['cantidad']);
            } else {
                DB::table('inventario')->insert([
                    'lote_id' => $lot->lote_id,
                    'ubicacion_id' => $data['ubicacion_id'],
                    'cantidad' => $data['cantidad'],
                ]);
            }

            $movementId = DB::table('movimientos_controlados')->insertGetId([
                'producto_id' => $lot->producto_id,
                'lote_id' => $lot->lote_id,
                'tipo_movimiento' => $data['tipo_movimiento'],
                'cantidad' => $data['cantidad'],
                'usuario_responsable_rut' => Auth::id(),
                'usuario_autoriza_rut' => Auth::id(),
                'firma_autorizacion' => 'Confirmación de identidad mediante contraseña; sin firma digital certificada.',
                'referencia_tipo' => 'LIBRO_DIGITAL',
                'observacion' => $data['observacion'].' | Ubicación: '.$locationName,
                'firmado' => true,
            ]);

            DB::table('movimientos_inventario')->insert([
                'lote_id' => $lot->lote_id,
                'ubicacion_id' => $data['ubicacion_id'],
                'tipo_movimiento' => $data['tipo_movimiento'] === 'INGRESO' ? 'RECEPCION' : 'SALIDA',
                'cantidad' => $data['cantidad'],
                ...InventoryQuantitySnapshot::forBaseQuantity((int) $lot->producto_id, (float) $data['cantidad']),
                'usuario_rut' => Auth::id(),
                'referencia_tipo' => 'MOVIMIENTO_CONTROLADO',
                'referencia_id' => $movementId,
                'observacion' => $data['observacion'],
            ]);

            $alerts->check($lot->lote_id, $data['ubicacion_id']);
            if ($data['tipo_movimiento'] === 'SALIDA') {
                $alerts->checkForReplenishment($lot->producto_id, (string) Auth::id());
            }
            $audit->record('MOVIMIENTO_CONTROLADO', 'movimientos_controlados', $movementId, 'FIRMADO', [
                'tipo_movimiento' => $data['tipo_movimiento'],
                'cantidad' => $data['cantidad'],
                'lote_id' => $lot->lote_id,
            ]);
        });

        return ($request->routeIs('api.v1.*') || $request->expectsJson())
            ? response()->json(['data' => ['message' => 'Movimiento controlado registrado con confirmación de identidad del Químico Farmacéutico.']], 201)
            : redirect()->route('controlled-ledger.index')->with('status', 'Movimiento controlado registrado con confirmación de identidad del Químico Farmacéutico.');
    }

    private function authorizePharmacist(AuditLogger $audit): void
    {
        $user = Auth::user();
        if (! $user instanceof Usuario || ! $user->hasAnyRole(['Químico Farmacéutico'])) {
            $audit->record('ACCESO_DENEGADO', 'libro_digital', null, 'RECHAZADO');
            abort(403);
        }
    }
}
