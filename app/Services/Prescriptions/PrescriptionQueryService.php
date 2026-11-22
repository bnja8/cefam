<?php

namespace App\Services\Prescriptions;

use App\Support\People\PersonName;
use App\Support\People\Rut;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class PrescriptionQueryService
{
    public function patientOptions(bool $activeProfessionals): array
    {
        return [
            'activePatientCount' => DB::table('pacientes')->where('activo', true)->count(),
            'selectedPatient' => DB::table('pacientes')
                ->where('rut', old('paciente_rut'))
                ->where('activo', true)
                ->first(['rut', PersonName::expression('pacientes', 'nombre_completo')]),
            'professionals' => DB::table('profesionales_salud')
                ->when($activeProfessionals, fn ($query) => $query->where('activo', true))
                ->orderBy('apellidoPaterno')->orderBy('apellidoMaterno')->orderBy('nombre')
                ->get(['rut', PersonName::expression('profesionales_salud', 'nombre_completo'), 'tipo_profesional']),
        ];
    }

    public function prescriptions(array $filters): mixed
    {
        $prescriptions = DB::table('recetas')
            ->join('pacientes', 'pacientes.rut', '=', 'recetas.paciente_rut')
            ->join('profesionales_salud', 'profesionales_salud.rut', '=', 'recetas.profesional_rut')
            ->where('recetas.estado_ficha', 'RECIENTE')
            ->where('recetas.fecha_registro', '>', now()->subDays(30))
            ->select('recetas.*', 'pacientes.rut', PersonName::expression('pacientes', 'paciente'), PersonName::expression('profesionales_salud', 'profesional'))
            ->when($filters['estado'] ?? null, fn ($query, string $status) => $query->where('recetas.estado', $status))
            ->when($filters['tipo_receta'] ?? null, fn ($query, string $type) => $query->where('recetas.tipo_receta', $type))
            ->when($filters['buscar'] ?? null, function ($query, string $search) {
                $term = '%'.$search.'%';
                $query->where(function ($query) use ($term, $search) {
                    $query->where('recetas.numero_receta', 'like', $term)
                        ->orWhere('recetas.estado', 'like', $term)
                        ->orWhere('pacientes.rut', 'like', '%'.Rut::limpiarRut($search).'%')
                        ->orWhere(fn ($nameQuery) => PersonName::whereMatches($nameQuery, 'pacientes', $search))
                        ->orWhere(fn ($nameQuery) => PersonName::whereMatches($nameQuery, 'profesionales_salud', $search));
                });
            })
            ->orderByDesc('recetas.fecha_emision')
            ->paginate(15)
            ->withQueryString();
        $prescriptionIds = $prescriptions->getCollection()->pluck('receta_id')->filter()->values()->all();
        $dispensedByProduct = DB::table('entrega_detalle')
            ->join('entregas', 'entregas.entrega_id', '=', 'entrega_detalle.entrega_id')
            ->where('entregas.estado', 'CONFIRMADA')
            ->select('entregas.receta_id', 'entrega_detalle.producto_id')
            ->selectRaw('SUM(entrega_detalle.cantidad) as cantidad_dispensada')
            ->groupBy('entregas.receta_id', 'entrega_detalle.producto_id');
        $details = DB::table('receta_detalle')
            ->join('productos', 'productos.producto_id', '=', 'receta_detalle.producto_id')
            ->leftJoin('unidades_medida', 'unidades_medida.unidad_medida_id', '=', 'productos.unidad_base_inventario_id')
            ->leftJoinSub($dispensedByProduct, 'dispensado', fn ($join) => $join
                ->on('dispensado.receta_id', '=', 'receta_detalle.receta_id')
                ->on('dispensado.producto_id', '=', 'receta_detalle.producto_id'))
            ->when($prescriptionIds !== [], fn ($query) => $query->whereIn('receta_detalle.receta_id', $prescriptionIds))
            ->get([
                'receta_detalle.receta_id',
                'productos.producto_id',
                'productos.nombre as producto',
                'unidades_medida.codigo as unidad_base_codigo',
                'receta_detalle.via_administracion',
                'receta_detalle.cantidad_indicada',
                'receta_detalle.unidades_por_toma',
                'receta_detalle.duracion_dias',
                'receta_detalle.es_sos',
                'receta_detalle.dosis',
                'receta_detalle.frecuencia',
                'receta_detalle.indicaciones',
                DB::raw('COALESCE(dispensado.cantidad_dispensada, 0) as cantidad_dispensada'),
            ])->groupBy('receta_id');
        $prescriptions->getCollection()->each(function ($prescription) use ($details) {
            $prescription->medicamentos = $details->get($prescription->receta_id, collect())
                ->each(function ($medicine) {
                    $medicine->cantidad_pendiente = max(0, (float) $medicine->cantidad_indicada - (float) $medicine->cantidad_dispensada);
                    $medicine->estado_dispensacion = $medicine->cantidad_pendiente <= 0.00001
                        ? 'COMPLETA'
                        : ((float) $medicine->cantidad_dispensada > 0 ? 'PARCIAL' : 'PENDIENTE');
                });
        });

        return $prescriptions;
    }

    public function searchProducts(string $term): Collection
    {
        return DB::table('productos')
            ->join('unidades_medida', 'unidades_medida.unidad_medida_id', '=', 'productos.unidad_base_inventario_id')
            ->where('productos.activo', true)
            ->where('productos.tipo_producto', 'MEDICAMENTO')
            ->where('productos.nombre', 'like', '%'.$term.'%')
            ->orderBy('productos.nombre')
            ->limit(20)
            ->get([
                'productos.producto_id',
                'productos.nombre',
                'productos.es_controlado',
                'productos.via_administracion',
                'unidades_medida.codigo as unidad_base_codigo',
            ])
            ->map(fn ($product) => [
                'producto_id' => (int) $product->producto_id,
                'nombre' => $product->nombre,
                'es_controlado' => (bool) $product->es_controlado,
                'via_administracion' => $product->via_administracion,
                'unidad_base_codigo' => $product->unidad_base_codigo,
                'detalle' => 'Vía: '.($product->via_administracion ?: 'sin especificar').' · Unidad: '.$product->unidad_base_codigo,
            ])
            ->values();
    }

    public function printable(int $prescriptionId): array
    {
        $prescription = DB::table('recetas')
            ->join('pacientes', 'pacientes.rut', '=', 'recetas.paciente_rut')
            ->join('profesionales_salud', 'profesionales_salud.rut', '=', 'recetas.profesional_rut')
            ->where('recetas.receta_id', $prescriptionId)
            ->first([
                'recetas.*',
                'pacientes.rut as paciente_rut',
                PersonName::expression('pacientes', 'paciente'),
                PersonName::expression('profesionales_salud', 'profesional'),
            ]);
        abort_unless($prescription, 404);

        $details = DB::table('receta_detalle')
            ->join('productos', 'productos.producto_id', '=', 'receta_detalle.producto_id')
            ->leftJoin('unidades_medida', 'unidades_medida.unidad_medida_id', '=', 'productos.unidad_base_inventario_id')
            ->where('receta_detalle.receta_id', $prescriptionId)
            ->orderBy('productos.nombre')
            ->get([
                'productos.nombre as producto',
                'unidades_medida.codigo as unidad_base_codigo',
                'receta_detalle.via_administracion',
                'receta_detalle.cantidad_indicada',
                'receta_detalle.unidades_por_toma',
                'receta_detalle.duracion_dias',
                'receta_detalle.es_sos',
                'receta_detalle.dosis',
                'receta_detalle.frecuencia',
                'receta_detalle.indicaciones',
            ]);

        return compact('prescription', 'details');
    }
}
