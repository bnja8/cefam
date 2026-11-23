<?php

namespace App\Http\Controllers\Prescriptions;

use App\Http\Controllers\Controller;
use App\Rules\FourDigitDate;
use App\Services\Audit\AuditLogger;
use App\Services\Patients\PatientFileService;
use App\Services\Patients\PatientRecordArchiver;
use App\Services\Prescriptions\PrescriptionQueryService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class PrescriptionController extends Controller
{
    public function index(
        Request $request,
        PatientRecordArchiver $recordArchiver,
        PrescriptionQueryService $queries
    ): View|JsonResponse
    {
        $screen = $request->route('screen', 'list');
        if ($screen === 'create') {
            $data = [
                'screen' => 'create',
            ] + $queries->patientOptions(true);

            return ($request->routeIs('api.v1.*') || $request->expectsJson())
                ? response()->json(['data' => $data])
                : view('prescriptions.index', $data);
        }

        $recordArchiver->archiveExpired();
        $filters = $request->validate([
            'buscar' => ['nullable', 'string', 'max:100'],
            'estado' => ['nullable', Rule::in(['VIGENTE', 'VENCIDA', 'UTILIZADA', 'ANULADA'])],
            'tipo_receta' => ['nullable', Rule::in(['ESTANDAR', 'RETENIDA'])],
        ]);

        $prescriptions = $queries->prescriptions($filters);

        $data = [
            'screen' => 'list',
            'prescriptions' => $prescriptions,
            'filters' => $filters,
        ] + $queries->patientOptions(false);

        return ($request->routeIs('api.v1.*') || $request->expectsJson())
            ? response()->json(['data' => $data])
            : view('prescriptions.index', $data);
    }

    public function searchProducts(Request $request, PrescriptionQueryService $queries): JsonResponse
    {
        $data = $request->validate([
            'q' => ['required', 'string', 'max:100'],
        ]);
        $term = trim($data['q']);
        if (mb_strlen($term) < 2) {
            return response()->json([]);
        }

        return response()->json($queries->searchProducts($term));
    }

    public function print(Request $request, int $prescriptionId, PrescriptionQueryService $queries): View|JsonResponse
    {
        ['prescription' => $prescription, 'details' => $details] = $queries->printable($prescriptionId);

        return ($request->routeIs('api.v1.*') || $request->expectsJson())
            ? response()->json(['data' => compact('prescription', 'details')])
            : view('prescriptions.print', compact('prescription', 'details'));
    }

    // Valida y guarda un nuevo registro.
    public function store(Request $request, AuditLogger $audit, PatientFileService $patientFiles): RedirectResponse|JsonResponse
    {
        $data = $request->validate([
            'paciente_rut' => ['required', 'string', 'max:12', 'exists:pacientes,rut,activo,1'],
            'profesional_rut' => [
                'required',
                'string',
                'max:12',
                Rule::exists('personal_salud', 'rut')->where('activo', true),
            ],
            'numero_receta' => ['required', 'string', 'max:100', 'unique:recetas,numero_receta'],
            'fecha_emision' => ['required', 'bail', new FourDigitDate(), 'before_or_equal:today'],
            'fecha_vencimiento' => ['required', 'bail', new FourDigitDate(), 'after_or_equal:fecha_emision'],
            'tipo_receta' => ['required', 'in:ESTANDAR,RETENIDA'],
            'observacion' => ['nullable', 'string', 'max:2000'],
            'indicar_tratamiento' => ['sometimes', 'boolean'],
            'tratamiento' => ['nullable', 'array:descripcion'],
            'tratamiento.descripcion' => ['required_if:indicar_tratamiento,true', 'string', 'max:2000'],
            'detalles' => ['required', 'array', 'min:1', 'max:30'],
            'detalles.*.producto_id' => ['required', 'integer', 'distinct', 'exists:productos,producto_id,activo,1'],
            'detalles.*.cantidad_indicada' => ['required', 'numeric', 'decimal:0,2', 'gt:0'],
            'detalles.*.unidades_por_toma' => ['required', 'numeric', 'decimal:0,2', 'gt:0'],
            'detalles.*.duracion_dias' => ['required', 'integer', 'min:1', 'max:365'],
            'detalles.*.es_sos' => ['prohibited'],
            'detalles.*.dosis' => ['nullable', 'string', 'max:100'],
            'detalles.*.frecuencia' => ['required', 'string', 'max:100'],
            'detalles.*.indicaciones' => ['nullable', 'string', 'max:1000'],
        ]);

        $result = DB::transaction(function () use ($data, $audit, $patientFiles) {
            $patientIsActive = DB::table('pacientes')
                ->where('rut', $data['paciente_rut'])
                ->where('activo', true)
                ->lockForUpdate()
                ->first(['rut']);
            if (! $patientIsActive) {
                throw ValidationException::withMessages(['paciente_rut' => 'El paciente no está activo.']);
            }
            $fichaId = $patientFiles->ensureForPatient($data['paciente_rut']);
            $professionalIsActive = DB::table('personal_salud')
                ->where('rut', $data['profesional_rut'])
                ->where('activo', true)
                ->lockForUpdate()
                ->first(['rut']);
            if (! $professionalIsActive) {
                throw ValidationException::withMessages(['profesional_rut' => 'El profesional no está activo.']);
            }
            $products = [];
            foreach ($data['detalles'] as $index => $detail) {
                $product = DB::table('productos')
                    ->join('unidades_medida', 'unidades_medida.unidad_medida_id', '=', 'productos.unidad_base_inventario_id')
                    ->where('productos.producto_id', $detail['producto_id'])
                    ->where('productos.activo', true)
                    ->lockForUpdate()
                    ->first([
                        'productos.producto_id',
                        'productos.tipo_producto',
                        'productos.via_administracion',
                        'unidades_medida.codigo as unidad_base_codigo',
                    ]);
                if (! $product || $product->tipo_producto !== 'MEDICAMENTO') {
                    throw ValidationException::withMessages(['detalles' => 'La receta solo puede incluir medicamentos activos con unidad base configurada.']);
                }
                $products[$detail['producto_id']] = $product;
            }

            $prescriptionId = DB::table('recetas')->insertGetId([
                'paciente_rut' => $data['paciente_rut'],
                'ficha_id' => $fichaId,
                'profesional_rut' => $data['profesional_rut'],
                'numero_receta' => $data['numero_receta'],
                'fecha_emision' => $data['fecha_emision'],
                'fecha_vencimiento' => $data['fecha_vencimiento'],
                'tipo_receta' => $data['tipo_receta'],
                'estado' => $data['fecha_vencimiento'] < now()->toDateString() ? 'VENCIDA' : 'VIGENTE',
                'fecha_registro' => now(),
                'estado_ficha' => 'RECIENTE',
                'observacion' => $data['observacion'] ?? null,
            ]);

            foreach ($data['detalles'] as $detail) {
                $product = $products[$detail['producto_id']];
                $dose = $detail['dosis'] ?? null;
                if (! $dose && isset($detail['unidades_por_toma'])) {
                    $doseAmount = rtrim(rtrim(number_format((float) $detail['unidades_por_toma'], 2, '.', ''), '0'), '.');
                    $dose = $doseAmount.' '.$product->unidad_base_codigo;
                }
                DB::table('receta_detalle')->insert([
                    'receta_id' => $prescriptionId,
                    'producto_id' => $detail['producto_id'],
                    'via_administracion' => $product->via_administracion,
                    'dosis' => $dose,
                    'frecuencia' => $detail['frecuencia'],
                    'cantidad_indicada' => $detail['cantidad_indicada'],
                    'unidades_por_toma' => $detail['unidades_por_toma'] ?? null,
                    'duracion_dias' => $detail['duracion_dias'],
                    'es_sos' => false,
                    'indicaciones' => $detail['indicaciones'] ?? null,
                ]);
            }

            $audit->record('CREACION', 'recetas', $prescriptionId, 'EXITOSO', [
                'numero_receta' => $data['numero_receta'],
                'paciente_rut' => $data['paciente_rut'],
                'detalle_count' => count($data['detalles']),
            ]);

            $treatmentId = null;
            if (($data['indicar_tratamiento'] ?? false) && isset($data['tratamiento'])) {
                $treatmentId = DB::table('tratamientos')->insertGetId([
                    'receta_id' => $prescriptionId,
                    'paciente_rut' => $data['paciente_rut'],
                    'descripcion' => $data['tratamiento']['descripcion'],
                    'fecha_inicio' => $data['fecha_emision'],
                    'fecha_termino' => $data['fecha_vencimiento'],
                    'activo' => true,
                ]);
                $audit->record('CREACION', 'tratamientos', $treatmentId, 'EXITOSO', [
                    'paciente_rut' => $data['paciente_rut'],
                    'receta_id' => $prescriptionId,
                ]);
            }

            return ['receta_id' => $prescriptionId, 'tratamiento_id' => $treatmentId];
        });

        return ($request->routeIs('api.v1.*') || $request->expectsJson())
            ? response()->json(['data' => [
                ...$result,
                'message' => $result['tratamiento_id']
                    ? 'Receta registrada y tratamiento vinculado al paciente.'
                    : 'Receta registrada y validada.',
            ]], 201)
            : redirect()->route('recipes.index')->with('status', 'Receta registrada y validada.');
    }
}
