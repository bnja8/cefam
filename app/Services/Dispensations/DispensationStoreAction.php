<?php

namespace App\Services\Dispensations;

use App\Services\Audit\AuditLogger;
use App\Services\Patients\PatientFileService;
use App\Support\People\PersonName;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class DispensationStoreAction
{
    public function __construct(
        private readonly AuditLogger $audit,
        private readonly PatientFileService $patientFiles,
        private readonly DispensationPrescriptionValidator $prescriptionValidator,
        private readonly DispensationProductWriter $productWriter,
        private readonly DispensationAuthorizationService $authorization
    ) {}

    public function store(array $data): array
    {
        $audit = $this->audit;
        $patientFiles = $this->patientFiles;

        $hasControlledProduct = DB::table('productos')
            ->whereIn('producto_id', collect($data['detalles'])->pluck('producto_id'))
            ->where('es_controlado', true)
            ->exists();
        $authorizerRut = $hasControlledProduct
            ? $this->authorization->authorizeControlledDispensation($data)
            : null;

        $delivery = DB::transaction(function () use ($data, $audit, $authorizerRut, $patientFiles, $hasControlledProduct) {
            $isSos = filter_var($data['tratamiento_sos'] ?? false, FILTER_VALIDATE_BOOLEAN);
            $patient = DB::table('pacientes')->where('rut', $data['paciente_rut'])
                ->where('activo', true)->lockForUpdate()->first();
            if (! $patient) {
                throw ValidationException::withMessages(['paciente_rut' => 'El paciente no está activo.']);
            }
            $fichaId = $patientFiles->ensureForPatient($patient->rut);
            $today = now()->toDateString();
            $recipe = $isSos ? null : DB::table('recetas')->where('receta_id', $data['receta_id'])
                ->where('paciente_rut', $data['paciente_rut'])
                ->where('estado', 'VIGENTE')
                ->where('fecha_vencimiento', '>=', $today)
                ->lockForUpdate()
                ->first();
            if (! $isSos && ! $recipe) {
                throw ValidationException::withMessages(['receta_id' => 'La receta está vencida, no corresponde al paciente o no está vigente.']);
            }
            $treatment = $recipe ? DB::table('tratamientos')
                ->where('receta_id', $recipe->receta_id)
                ->lockForUpdate()
                ->first() : null;
            $monthlyLimitApplies = ! $isSos && $treatment
                && (bool) $treatment->activo
                && $treatment->fecha_inicio <= $today
                && ($treatment->fecha_termino === null || $treatment->fecha_termino >= $today);

            if (! empty($data['nueva_persona_autorizada'])) {
                $newAuthorizedPerson = $data['nueva_persona_autorizada'];
                $duplicate = DB::table('personas_autorizadas')
                    ->where('paciente_rut', $patient->rut)
                    ->where('rut', $newAuthorizedPerson['rut'])
                    ->lockForUpdate()
                    ->exists();
                if ($duplicate) {
                    throw ValidationException::withMessages([
                        'nueva_persona_autorizada.rut' => 'Esta persona ya tiene una autorización registrada para el paciente.',
                    ]);
                }

                $authorizedPersonId = DB::table('personas_autorizadas')->insertGetId([
                    ...$newAuthorizedPerson,
                    'paciente_rut' => $patient->rut,
                    'activo' => true,
                ]);
                $data['persona_autorizada_id'] = $authorizedPersonId;
                $audit->record('CREACION', 'personas_autorizadas', $authorizedPersonId, 'EXITOSO', [
                    'rut' => $newAuthorizedPerson['rut'],
                    'paciente_rut' => $patient->rut,
                    'origen' => 'DISPENSACION',
                ]);
            }

            $authorizedPerson = null;
            if (! empty($data['persona_autorizada_id'])) {
                $authorizedPerson = DB::table('personas_autorizadas')->where('persona_autorizada_id', $data['persona_autorizada_id'])
                    ->where('paciente_rut', $data['paciente_rut'])->where('activo', true)
                    ->lockForUpdate()->first([
                        'persona_autorizada_id',
                        'rut',
                        'nombre',
                        'apellidoPaterno',
                        'apellidoMaterno',
                    ]);
                if (! $authorizedPerson) {
                    throw ValidationException::withMessages(['persona_autorizada_id' => 'La persona autorizada no corresponde a este paciente.']);
                }
            }

            $prescribedDetails = $recipe ? DB::table('receta_detalle')
                ->join('productos', 'productos.producto_id', '=', 'receta_detalle.producto_id')
                ->where('receta_id', $recipe->receta_id)
                ->lockForUpdate()
                ->get([
                    'receta_detalle.producto_id',
                    'cantidad_indicada',
                    'dosis',
                    'unidades_por_toma',
                    'duracion_dias',
                    'es_sos',
                    'frecuencia',
                    'indicaciones',
                    'productos.es_controlado',
                ])
                ->keyBy('producto_id') : collect();
            $alreadyDispensed = $recipe ? DB::table('entrega_detalle')
                ->join('entregas', 'entregas.entrega_id', '=', 'entrega_detalle.entrega_id')
                ->where('entregas.receta_id', $recipe->receta_id)
                ->where('entregas.estado', 'CONFIRMADA')
                ->select('entrega_detalle.producto_id')
                ->selectRaw('SUM(entrega_detalle.cantidad) as cantidad')
                ->groupBy('entrega_detalle.producto_id')
                ->pluck('cantidad', 'producto_id') : collect();
            $monthStart = now()->startOfMonth()->startOfDay();
            $nextMonthStart = $monthStart->copy()->addMonthNoOverflow();
            $alreadyDispensedThisMonth = $recipe && $monthlyLimitApplies
                ? DB::table('entrega_detalle')
                    ->join('entregas', 'entregas.entrega_id', '=', 'entrega_detalle.entrega_id')
                    ->where('entregas.receta_id', $recipe->receta_id)
                    ->where('entregas.estado', 'CONFIRMADA')
                    ->where('entregas.fecha_hora', '>=', $monthStart)
                    ->where('entregas.fecha_hora', '<', $nextMonthStart)
                    ->select('entrega_detalle.producto_id')
                    ->selectRaw('SUM(entrega_detalle.cantidad) as cantidad')
                    ->groupBy('entrega_detalle.producto_id')
                    ->pluck('cantidad', 'producto_id')
                : collect();

            if (! $isSos) {
                $this->prescriptionValidator->validate(
                    $data,
                    $prescribedDetails,
                    $alreadyDispensed,
                    $alreadyDispensedThisMonth,
                    (bool) $monthlyLimitApplies
                );
            }
            $requiresControlledAuthorization = $hasControlledProduct;
            if ($requiresControlledAuthorization && $authorizerRut === null) {
                throw ValidationException::withMessages([
                    'autorizacion_qf' => 'Se requiere la validación de un Químico Farmacéutico para dispensar medicamentos controlados.',
                ]);
            }

            $locationIds = DB::table('ubicaciones')->where('tipo', 'VITRINA')->pluck('ubicacion_id')->all();

            $deliveryId = DB::table('entregas')->insertGetId([
                'paciente_rut' => $patient->rut,
                'ficha_id' => $fichaId,
                'persona_autorizada_id' => $data['persona_autorizada_id'] ?? null,
                'paciente_nombre_snapshot' => PersonName::format($patient),
                'persona_autorizada_nombre_snapshot' => $authorizedPerson ? PersonName::format($authorizedPerson) : null,
                'persona_autorizada_rut_snapshot' => $authorizedPerson->rut ?? null,
                'receta_id' => $recipe?->receta_id,
                'es_sos' => $isSos,
                'usuario_rut' => Auth::id(),
                'estado' => 'CONFIRMADA',
                'estado_ficha' => 'RECIENTE',
                'observacion' => $data['observacion'] ?? null,
                'fecha_hora' => now(),
            ]);

            $dispensedItems = [];
            foreach ($data['detalles'] as $index => $detail) {
                $dispensedItems[] = $this->productWriter->write(
                    $detail,
                    $index,
                    (int) $deliveryId,
                    $patient,
                    $recipe,
                    $prescribedDetails,
                    $alreadyDispensed,
                    $alreadyDispensedThisMonth,
                    (bool) $monthlyLimitApplies,
                    $locationIds,
                    $authorizerRut,
                    (string) Auth::id(),
                    $today,
                    $isSos
                );
            }
            if ($requiresControlledAuthorization) {
                $audit->record('FIRMA_CONTROLADO_VALIDADA', 'entregas', $deliveryId, 'EXITOSO', [
                    'usuario_autoriza_rut' => $authorizerRut,
                ]);
            }
            $orderId = DB::table('ordenes_entrega')->insertGetId([
                'entrega_id' => $deliveryId,
                'numero_orden' => 'ORD-'.now()->format('Ymd').'-'.str_pad((string) $deliveryId, 8, '0', STR_PAD_LEFT),
                'plantilla' => 'COMPROBANTE_FARMACIA_V1',
            ]);
            $audit->record('DISPENSACION', 'entregas', $deliveryId, 'EXITOSO', [
                'paciente_rut' => $patient->rut,
                'receta_id' => $recipe?->receta_id,
                'tratamiento_sos' => $isSos,
                'receta_verificada' => (bool) ($data['confirmacion_receta'] ?? false),
                'detalles' => $dispensedItems,
            ]);

            if ($recipe) {
                $dispensedByProduct = DB::table('entrega_detalle')
                    ->join('entregas', 'entregas.entrega_id', '=', 'entrega_detalle.entrega_id')
                    ->where('entregas.receta_id', $recipe->receta_id)
                    ->where('entregas.estado', 'CONFIRMADA')
                    ->select('entrega_detalle.producto_id')
                    ->selectRaw('SUM(entrega_detalle.cantidad) as cantidad_dispensada')
                    ->groupBy('entrega_detalle.producto_id');
                $hasUndispensedPrescriptionItems = DB::table('receta_detalle')
                    ->leftJoinSub($dispensedByProduct, 'dispensado', fn ($join) => $join
                        ->on('dispensado.producto_id', '=', 'receta_detalle.producto_id'))
                    ->where('receta_detalle.receta_id', $recipe->receta_id)
                    ->whereRaw('receta_detalle.cantidad_indicada > COALESCE(dispensado.cantidad_dispensada, 0) + 0.00001')
                    ->exists();
                if (! $monthlyLimitApplies && ! $hasUndispensedPrescriptionItems) {
                    DB::table('recetas')->where('receta_id', $recipe->receta_id)->update(['estado' => 'UTILIZADA']);
                }
            }

            return ['delivery_id' => $deliveryId, 'order_id' => $orderId];
        });

        return $delivery;
    }
}
