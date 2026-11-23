<?php

namespace App\Actions\Dispensations;

use App\Support\People\PersonName;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class DispensationPatientOptionsAction
{
    public function patientOptions(string $patientRut): JsonResponse
    {
        $patient = DB::table('pacientes')->where('rut', $patientRut)->where('activo', true)->first(['rut']);
        abort_unless($patient, 404);

        $today = now()->toDateString();
        $recipes = DB::table('recetas')
            ->join('personal_salud', 'personal_salud.rut', '=', 'recetas.profesional_rut')
            ->leftJoin('tratamientos', 'tratamientos.receta_id', '=', 'recetas.receta_id')
            ->where('recetas.paciente_rut', $patientRut)
            ->where('recetas.estado', 'VIGENTE')
            ->where('recetas.fecha_vencimiento', '>=', $today)
            ->orderByDesc('recetas.fecha_emision')
            ->get([
                'recetas.receta_id',
                'recetas.numero_receta',
                'recetas.profesional_rut',
                'recetas.fecha_emision',
                'recetas.fecha_vencimiento',
                'recetas.observacion',
                'tratamientos.tratamiento_id',
                'tratamientos.descripcion as tratamiento_descripcion',
                'tratamientos.fecha_inicio as tratamiento_fecha_inicio',
                'tratamientos.fecha_termino as tratamiento_fecha_termino',
                'tratamientos.activo as tratamiento_activo',
                PersonName::expression('personal_salud', 'profesional'),
            ]);

        $monthStart = now()->startOfMonth()->startOfDay();
        $nextMonthStart = $monthStart->copy()->addMonthNoOverflow();
        $dispensedByProduct = DB::table('entrega_detalle')
            ->join('entregas', 'entregas.entrega_id', '=', 'entrega_detalle.entrega_id')
            ->where('entregas.estado', 'CONFIRMADA')
            ->select('entregas.receta_id', 'entrega_detalle.producto_id')
            ->selectRaw('SUM(entrega_detalle.cantidad) as cantidad_dispensada')
            ->groupBy('entregas.receta_id', 'entrega_detalle.producto_id');
        $dispensedThisMonthByProduct = DB::table('entrega_detalle')
            ->join('entregas', 'entregas.entrega_id', '=', 'entrega_detalle.entrega_id')
            ->where('entregas.estado', 'CONFIRMADA')
            ->where('entregas.fecha_hora', '>=', $monthStart)
            ->where('entregas.fecha_hora', '<', $nextMonthStart)
            ->select('entregas.receta_id', 'entrega_detalle.producto_id')
            ->selectRaw('SUM(entrega_detalle.cantidad) as cantidad_dispensada')
            ->groupBy('entregas.receta_id', 'entrega_detalle.producto_id');
        $remainingRecipeIds = DB::table('receta_detalle')
            ->join('recetas', 'recetas.receta_id', '=', 'receta_detalle.receta_id')
            ->leftJoin('tratamientos', 'tratamientos.receta_id', '=', 'recetas.receta_id')
            ->whereIn('receta_detalle.receta_id', $recipes->pluck('receta_id'))
            ->leftJoinSub(
                $dispensedByProduct,
                'dispensado',
                fn ($join) => $join->on('dispensado.receta_id', '=', 'receta_detalle.receta_id')
                    ->on('dispensado.producto_id', '=', 'receta_detalle.producto_id')
            )
            ->leftJoinSub($dispensedThisMonthByProduct, 'dispensado_mes', fn ($join) => $join
                ->on('dispensado_mes.receta_id', '=', 'receta_detalle.receta_id')
                ->on('dispensado_mes.producto_id', '=', 'receta_detalle.producto_id'))
            ->whereRaw('receta_detalle.cantidad_indicada > CASE WHEN tratamientos.tratamiento_id IS NOT NULL AND tratamientos.activo = 1 THEN COALESCE(dispensado_mes.cantidad_dispensada, 0) ELSE COALESCE(dispensado.cantidad_dispensada, 0) END')
            ->distinct()
            ->pluck('receta_detalle.receta_id')
            ->map(fn ($id) => (string) $id)
            ->all();
        $monthlyRecipeIds = $recipes
            ->filter(fn ($recipe) => $recipe->tratamiento_id !== null && (bool) $recipe->tratamiento_activo)
            ->pluck('receta_id')
            ->map(fn ($id) => (string) $id)
            ->all();

        $stockByProduct = DB::table('inventario')
            ->join('lotes', 'lotes.lote_id', '=', 'inventario.lote_id')
            ->join('ubicaciones', 'ubicaciones.ubicacion_id', '=', 'inventario.ubicacion_id')
            ->where('lotes.estado', 'ACTIVO')
            ->where('ubicaciones.tipo', 'VITRINA')
            ->where('inventario.cantidad', '>', 0)
            ->whereDate('lotes.fecha_vencimiento', '>=', $today)
            ->select('lotes.producto_id')
            ->selectRaw('SUM(inventario.cantidad) as stock_disponible')
            ->groupBy('lotes.producto_id');
        $prescriptionDetails = DB::table('receta_detalle')
            ->join('productos', 'productos.producto_id', '=', 'receta_detalle.producto_id')
            ->leftJoin('unidades_medida', 'unidades_medida.unidad_medida_id', '=', 'productos.unidad_base_inventario_id')
            ->leftJoinSub($dispensedByProduct, 'dispensado', fn ($join) => $join
                ->on('dispensado.receta_id', '=', 'receta_detalle.receta_id')
                ->on('dispensado.producto_id', '=', 'receta_detalle.producto_id'))
            ->leftJoinSub($dispensedThisMonthByProduct, 'dispensado_mes', fn ($join) => $join
                ->on('dispensado_mes.receta_id', '=', 'receta_detalle.receta_id')
                ->on('dispensado_mes.producto_id', '=', 'receta_detalle.producto_id'))
            ->leftJoinSub($stockByProduct, 'stock_vitrina', fn ($join) => $join
                ->on('stock_vitrina.producto_id', '=', 'productos.producto_id'))
            ->whereIn('receta_detalle.receta_id', $recipes->pluck('receta_id'))
            ->orderBy('productos.nombre')
            ->get([
                'receta_detalle.receta_id',
                'productos.producto_id',
                'productos.nombre as producto',
                'productos.es_controlado',
                'unidades_medida.codigo as unidad_base_codigo',
                'receta_detalle.cantidad_indicada',
                'receta_detalle.via_administracion',
                'receta_detalle.dosis',
                'receta_detalle.unidades_por_toma',
                'receta_detalle.frecuencia',
                'receta_detalle.duracion_dias',
                'receta_detalle.es_sos',
                'receta_detalle.indicaciones',
                DB::raw('COALESCE(dispensado.cantidad_dispensada, 0) as cantidad_dispensada'),
                DB::raw('COALESCE(dispensado_mes.cantidad_dispensada, 0) as cantidad_dispensada_mes'),
                DB::raw('COALESCE(stock_vitrina.stock_disponible, 0) as stock_disponible'),
            ])
            ->groupBy('receta_id');

        $activeProgram = DB::table('paciente_programa')
            ->join('programas_salud', 'programas_salud.programa_id', '=', 'paciente_programa.programa_id')
            ->where('paciente_programa.paciente_rut', $patientRut)
            ->where('paciente_programa.activo', true)
            ->where('programas_salud.activo', true)
            ->whereDate('paciente_programa.fecha_inicio', '<=', now()->toDateString())
            ->where(function ($query) {
                $query->whereNull('paciente_programa.fecha_termino')
                    ->orWhereDate('paciente_programa.fecha_termino', '>=', now()->toDateString());
            })
            ->orderByDesc('paciente_programa.fecha_inicio')
            ->value('programas_salud.nombre');

        return response()->json([
            'programa_activo' => $activeProgram,
            'recetas' => $recipes->filter(fn ($recipe) => in_array((string) $recipe->receta_id, $remainingRecipeIds, true))
                ->map(function ($recipe) use ($prescriptionDetails, $monthlyRecipeIds) {
                    $details = $prescriptionDetails->get($recipe->receta_id, collect())
                        ->map(function ($detail) use ($recipe, $monthlyRecipeIds) {
                            $isMonthlyTreatment = in_array((string) $recipe->receta_id, $monthlyRecipeIds, true);
                            $dispensed = $isMonthlyTreatment ? $detail->cantidad_dispensada_mes : $detail->cantidad_dispensada;
                            $detail->es_tratamiento_prolongado = $isMonthlyTreatment;
                            $detail->cantidad_pendiente = max(0, (float) $detail->cantidad_indicada - (float) $dispensed);

                            return $detail;
                        })
                        ->values();

                    return [
                        'receta_id' => $recipe->receta_id,
                        'numero_receta' => $recipe->numero_receta,
                        'profesional_rut' => $recipe->profesional_rut,
                        'fecha_emision' => $recipe->fecha_emision,
                        'fecha_vencimiento' => $recipe->fecha_vencimiento,
                        'profesional' => $recipe->profesional,
                        'observacion' => $recipe->observacion,
                        'tratamiento' => $recipe->tratamiento_id ? [
                            'tratamiento_id' => $recipe->tratamiento_id,
                            'descripcion' => $recipe->tratamiento_descripcion,
                            'fecha_inicio' => $recipe->tratamiento_fecha_inicio,
                            'fecha_termino' => $recipe->tratamiento_fecha_termino,
                            'activo' => (bool) $recipe->tratamiento_activo,
                        ] : null,
                        'detalles' => $details,
                    ];
                })
                ->values(),
            'personas_autorizadas' => DB::table('personas_autorizadas')->where('paciente_rut', $patientRut)->where('activo', true)
                ->orderBy('apellidoPaterno')->orderBy('apellidoMaterno')->orderBy('nombre')
                ->get(['persona_autorizada_id', 'rut', PersonName::expression('personas_autorizadas', 'nombre_completo')]),
        ])->header('Cache-Control', 'no-store, private');
    }
}
