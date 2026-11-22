<?php


namespace App\Http\Controllers\Search;

use App\Http\Controllers\Controller;
use App\Http\Requests\GlobalSearchRequest;
use App\Services\Search\GlobalSearchService;
use Illuminate\Http\JsonResponse;

class GlobalSearchController extends Controller
{
    public function __invoke(GlobalSearchRequest $request, GlobalSearchService $search): JsonResponse
    {
        $results = array_map(static function (array $result): array {
            return [
                'tipo' => match ($result['type']) {
                    'patient' => 'Paciente',
                    'product' => 'Medicamento / insumo',
                    'lot' => 'Lote',
                },
                'titulo' => $result['title'],
                'detalle' => $result['detail'],
                'url' => match ($result['type']) {
                    'patient' => route('dispensations.index', ['paciente_rut' => $result['identifier']]),
                    'product', 'lot' => route('inventory.index', ['buscar' => $result['identifier']]),
                },
            ];
        }, $search->search($request->validated('q')));

        return response()->json($results);
    }
}
