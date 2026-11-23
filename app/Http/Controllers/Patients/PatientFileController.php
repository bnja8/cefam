<?php

namespace App\Http\Controllers\Patients;

use App\Http\Controllers\Controller;
use App\Services\Patients\PatientFileService;
use App\Services\Patients\PatientRecordArchiver;
use App\Support\People\PersonName;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class PatientFileController extends Controller
{
    public function show(
        Request $request,
        string $patientRut,
        PatientFileService $patientFiles,
        PatientRecordArchiver $recordArchiver
    ): View|JsonResponse {
        $patient = DB::table('pacientes')
            ->where('rut', $patientRut)
            ->first([
                'rut',
                PersonName::expression('pacientes', 'nombre_completo'),
                'fecha_nacimiento',
                'telefono',
                'activo',
            ]);
        abort_unless($patient, 404);

        $fichaId = $patientFiles->ensureForPatient($patientRut);
        $recordArchiver->archiveExpired();

        $prescriptions = DB::table('recetas')
            ->join('personal_salud', 'personal_salud.rut', '=', 'recetas.profesional_rut')
            ->where('recetas.ficha_id', $fichaId)
            ->orderByDesc('recetas.fecha_registro')
            ->orderByDesc('recetas.receta_id')
            ->get([
                'recetas.receta_id',
                'recetas.numero_receta',
                'recetas.fecha_emision',
                'recetas.fecha_vencimiento',
                'recetas.fecha_registro',
                'recetas.tipo_receta',
                'recetas.estado',
                'recetas.estado_ficha',
                PersonName::expression('personal_salud', 'profesional'),
            ]);
        $prescriptionDetails = DB::table('receta_detalle')
            ->join('productos', 'productos.producto_id', '=', 'receta_detalle.producto_id')
            ->whereIn('receta_detalle.receta_id', $prescriptions->pluck('receta_id'))
            ->orderBy('productos.nombre')
            ->get([
                'receta_detalle.receta_id',
                'productos.nombre as producto',
                'receta_detalle.cantidad_indicada',
            ])
            ->groupBy('receta_id');
        $prescriptions->each(function ($prescription) use ($prescriptionDetails) {
            $prescription->detalles = $prescriptionDetails->get($prescription->receta_id, collect());
        });

        $deliveries = DB::table('entregas')
            ->join('usuarios', 'usuarios.rut', '=', 'entregas.usuario_rut')
            ->leftJoin('personas_autorizadas', 'personas_autorizadas.persona_autorizada_id', '=', 'entregas.persona_autorizada_id')
            ->leftJoin('ordenes_entrega', 'ordenes_entrega.entrega_id', '=', 'entregas.entrega_id')
            ->where('entregas.ficha_id', $fichaId)
            ->orderByDesc('entregas.fecha_hora')
            ->orderByDesc('entregas.entrega_id')
            ->get([
                'entregas.entrega_id',
                'entregas.fecha_hora',
                'entregas.estado',
                'entregas.estado_ficha',
                'ordenes_entrega.orden_entrega_id',
                'ordenes_entrega.numero_orden',
                PersonName::expression('usuarios', 'responsable'),
                PersonName::expression('personas_autorizadas', 'persona_autorizada'),
            ]);
        $deliveryDetails = DB::table('entrega_detalle')
            ->join('productos', 'productos.producto_id', '=', 'entrega_detalle.producto_id')
            ->whereIn('entrega_detalle.entrega_id', $deliveries->pluck('entrega_id'))
            ->orderBy('productos.nombre')
            ->get([
                'entrega_detalle.entrega_id',
                'productos.nombre as producto',
                'entrega_detalle.cantidad',
            ])
            ->groupBy('entrega_id');
        $deliveries->each(function ($delivery) use ($deliveryDetails) {
            $delivery->detalles = $deliveryDetails->get($delivery->entrega_id, collect());
        });

        $data = compact('patient', 'prescriptions', 'deliveries');

        return $request->expectsJson()
            ? response()->json(['data' => $data])
            : view('patients.file', $data);
    }
}
