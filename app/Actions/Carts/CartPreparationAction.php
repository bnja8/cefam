<?php

namespace App\Actions\Carts;

use App\Rules\FourDigitDate;
use App\Services\Audit\AuditLogger;
use App\Services\Inventory\FefoInventoryAllocator;
use App\Services\Alerts\StockAlertService;
use App\Support\Inventory\InventoryQuantitySnapshot;
use App\Support\People\PersonName;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CartPreparationAction
{
    public function prepare(
        Request $request,
        AuditLogger $audit,
        FefoInventoryAllocator $allocator,
        StockAlertService $alerts
    ): RedirectResponse|JsonResponse {
        $data = $request->validate([
            'tratamiento_id' => ['required', 'integer', 'exists:tratamientos,tratamiento_id'],
            'fecha_preparacion' => ['required', 'bail', new FourDigitDate(), 'after_or_equal:today'],
            'periodo' => ['required', 'string', 'max:50'],
            'detalles' => ['required', 'array', 'min:1', 'max:30'],
            'detalles.*.producto_id' => ['required', 'integer', 'distinct', 'exists:productos,producto_id,activo,1'],
            'detalles.*.cantidad' => ['required', 'numeric', 'decimal:0,2', 'gt:0'],
            'detalles.*.horario' => ['nullable', 'date_format:H:i'],
        ]);

        $treatment = DB::table('tratamientos')
            ->join('pacientes', 'pacientes.rut', '=', 'tratamientos.paciente_rut')
            ->where('tratamientos.tratamiento_id', $data['tratamiento_id'])
            ->where('tratamientos.activo', true)
            ->where('pacientes.activo', true)
            ->first([
                'tratamientos.tratamiento_id',
                'tratamientos.paciente_rut',
                'tratamientos.fecha_inicio',
                'tratamientos.fecha_termino',
                PersonName::expression('pacientes', 'paciente'),
            ]);
        if (! $treatment
            || $data['fecha_preparacion'] < $treatment->fecha_inicio
            || ($treatment->fecha_termino && $data['fecha_preparacion'] > $treatment->fecha_termino)) {
            throw ValidationException::withMessages(['tratamiento_id' => 'La fecha indicada está fuera de la vigencia del tratamiento.']);
        }

        $cart = DB::table('carros')->join('ubicaciones', 'ubicaciones.ubicacion_id', '=', 'carros.ubicacion_id')
            ->where('carros.tipo_carro', 'UNIDOSIS')
            ->first(['carros.carro_id', 'ubicaciones.ubicacion_id']);
        if (! $cart) {
            throw ValidationException::withMessages(['tratamiento_id' => 'No hay un Carro Unidosis configurado.']);
        }

        $preparationId = DB::transaction(function () use ($data, $audit, $allocator, $alerts, $treatment, $cart) {
            $lockedTreatment = DB::table('tratamientos')->where('tratamiento_id', $data['tratamiento_id'])
                ->where('activo', true)->lockForUpdate()->first();
            $activePatient = $lockedTreatment && DB::table('pacientes')
                ->where('rut', $lockedTreatment->paciente_rut)
                ->where('activo', true)->lockForUpdate()->first(['rut']);
            if (! $lockedTreatment || ! $activePatient) {
                throw ValidationException::withMessages(['tratamiento_id' => 'El paciente o el tratamiento ya no está activo.']);
            }

            $duplicate = DB::table('preparaciones_unidosis')
                ->where('tratamiento_id', $data['tratamiento_id'])
                ->where('fecha_preparacion', $data['fecha_preparacion'])
                ->where('periodo', $data['periodo'])
                ->exists();
            if ($duplicate) {
                throw ValidationException::withMessages(['fecha_preparacion' => 'Ya existe una preparación para este tratamiento, fecha y período.']);
            }

            $preparationId = DB::table('preparaciones_unidosis')->insertGetId([
                'tratamiento_id' => $data['tratamiento_id'],
                'carro_id' => $cart->carro_id,
                'fecha_preparacion' => $data['fecha_preparacion'],
                'periodo' => $data['periodo'],
                'usuario_prepara_rut' => Auth::id(),
                'estado' => 'PREPARANDO',
            ]);

            foreach ($data['detalles'] as $detail) {
                $product = DB::table('productos')
                    ->where('producto_id', $detail['producto_id'])
                    ->where('activo', true)
                    ->where('es_controlado', false)
                    ->lockForUpdate()
                    ->first(['producto_id', 'nombre']);
                if (! $product) {
                    throw ValidationException::withMessages(['detalles' => 'Los medicamentos controlados deben registrarse en el libro digital, no en unidosis.']);
                }

                $lots = $allocator->consume(
                    $product->producto_id,
                    [$cart->ubicacion_id],
                    (float) $detail['cantidad'],
                    $data['fecha_preparacion'],
                    "Stock insuficiente en el Carro Unidosis para {$product->nombre}.",
                    'detalles'
                );

                foreach ($lots as $lot) {
                    $detailId = DB::table('preparacion_unidosis_detalle')->insertGetId([
                        'preparacion_id' => $preparationId,
                        'lote_id' => $lot->lote_id,
                        'cantidad' => $lot->cantidad_asignada,
                        'horario' => $detail['horario'] ?? null,
                    ]);
                    $labelContent = implode(' | ', array_filter([
                        'Paciente: '.$treatment->paciente,
                        'Medicamento: '.$product->nombre,
                        'Horario: '.($detail['horario'] ?? 'Según indicación'),
                        'Lote: '.$lot->numero_lote,
                        'Vencimiento: '.$lot->fecha_vencimiento,
                    ]));
                    DB::table('rotulos_unidosis')->insert([
                        'preparacion_detalle_id' => $detailId,
                        'identificador' => 'UNI-'.$preparationId.'-'.$detailId,
                        'contenido_generado' => $labelContent,
                    ]);
                    DB::table('movimientos_inventario')->insert([
                        'lote_id' => $lot->lote_id,
                        'ubicacion_id' => $cart->ubicacion_id,
                        'tipo_movimiento' => 'SALIDA',
                        'cantidad' => $lot->cantidad_asignada,
                        ...InventoryQuantitySnapshot::forBaseQuantity((int) $product->producto_id, (float) $lot->cantidad_asignada),
                        'usuario_rut' => Auth::id(),
                        'referencia_tipo' => 'PREPARACION_UNIDOSIS',
                        'referencia_id' => $preparationId,
                        'observacion' => 'Preparación unidosis para '.$treatment->paciente,
                    ]);
                    $alerts->check($lot->lote_id, $lot->ubicacion_id);
                    $alerts->checkForReplenishment($product->producto_id, (string) Auth::id());
                }
            }

            $audit->record('PREPARACION_UNIDOSIS', 'preparaciones_unidosis', $preparationId, 'EXITOSO', [
                'tratamiento_id' => $data['tratamiento_id'],
                'fecha_preparacion' => $data['fecha_preparacion'],
                'periodo' => $data['periodo'],
            ]);

            return $preparationId;
        });

        return ($request->routeIs('api.v1.*') || $request->expectsJson())
            ? response()->json(['data' => ['preparacion_id' => $preparationId, 'message' => 'Preparación creada; el stock se descontó por FEFO y se generaron sus rótulos.']], 201)
            : redirect()->route('carts.unidosis.index')->with('status', 'Preparación creada; el stock se descontó por FEFO y se generaron sus rótulos.');
    }
}
