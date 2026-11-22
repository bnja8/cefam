<?php


namespace App\Services\Search;

use App\Models\Paciente;
use App\Models\Producto;
use App\Support\People\PersonName;
use App\Support\People\Rut;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class GlobalSearchService
{
    public function search(string $term): array
    {
        $like = '%'.$term.'%';
        $rutLike = '%'.Rut::limpiarRut($term).'%';

        $patients = Paciente::query()
            ->active()
            ->where( fn ($query) => $query
                ->where('rut', 'like', $rutLike)
                ->orWhere( fn ($nameQuery) => PersonName::whereMatches($nameQuery, 'pacientes', $term)))
            ->orderBy('apellidoPaterno')
            ->orderBy('apellidoMaterno')
            ->orderBy('nombre')
            ->limit(4)
            ->get(['rut', 'nombre', 'apellidoPaterno', 'apellidoMaterno'])
            ->map( fn (Paciente $patient) => [
                'type' => 'patient',
                'title' => $patient->nombre_completo,
                'detail' => $patient->rut,
                'identifier' => $patient->rut,
            ]);

        $products = Producto::query()
            ->active()
            ->where( fn ($query) => $query
                ->where('numero_serie', 'like', $like)
                ->orWhere('nombre', 'like', $like)
                ->orWhereHas('codigosBarra',  fn ($barcodeQuery) => $barcodeQuery
                    ->where('activo', true)
                    ->where('codigo_barra', 'like', $like)))
            ->orderBy('nombre')
            ->limit(4)
            ->get(['producto_id', 'numero_serie', 'nombre'])
            ->map( fn (Producto $product) => [
                'type' => 'product',
                'title' => $product->nombre,
                'detail' => $product->numero_serie,
                'identifier' => $product->numero_serie,
            ]);

        $lots = DB::table('lotes')
            ->join('productos', 'productos.producto_id', '=', 'lotes.producto_id')
            ->join('inventario', 'inventario.lote_id', '=', 'lotes.lote_id')
            ->join('ubicaciones', 'ubicaciones.ubicacion_id', '=', 'inventario.ubicacion_id')
            ->where('productos.activo', true)
            ->where( fn ($query) => $query
                ->where('lotes.numero_lote', 'like', $like)
                ->orWhere('productos.nombre', 'like', $like)
                ->orWhere('productos.numero_serie', 'like', $like))
            ->orderBy('lotes.fecha_vencimiento')
            ->limit(4)
            ->get([
                'lotes.lote_id',
                'lotes.numero_lote',
                'lotes.fecha_vencimiento',
                'productos.numero_serie as codigo_producto',
                'productos.nombre',
                'ubicaciones.nombre as ubicacion',
            ])
            ->map( fn (object $lot) => [
                'type' => 'lot',
                'title' => $lot->numero_lote.' · '.$lot->nombre,
                'detail' => $lot->codigo_producto.' · Vence '.Carbon::parse($lot->fecha_vencimiento)->format('d/m/Y').' · '.$lot->ubicacion,
                'identifier' => $lot->numero_lote,
            ]);

        return $patients->concat($products)->concat($lots)->take(10)->values()->all();
    }
}
