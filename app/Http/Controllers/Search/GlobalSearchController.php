<?php


namespace App\Http\Controllers\Search;

use App\Http\Controllers\Controller;
use App\Http\Requests\GlobalSearchRequest;
use App\Models\Usuario;
use App\Services\Search\GlobalSearchService;
use Illuminate\Http\JsonResponse;

class GlobalSearchController extends Controller
{
    public function __invoke(GlobalSearchRequest $request, GlobalSearchService $search): JsonResponse
    {
        $user = $request->user();
        abort_unless($user instanceof Usuario, 401);

        $results = collect($search->search($request->validated('q')))
            ->filter(fn (array $result): bool => match ($result['type']) {
                'patient' => $user->hasPermission('patients.view')
                    || ($user->hasPermission('dispensations.view') && $user->hasPermission('dispensations.create')),
                'product', 'lot' => $user->hasPermission('inventory.view'),
                default => false,
            })
            ->map(function (array $result) use ($user): array {
                return [
                    'tipo' => match ($result['type']) {
                        'patient' => 'Paciente',
                        'product' => 'Medicamento / insumo',
                        'lot' => 'Lote',
                    },
                    'titulo' => $result['title'],
                    'detalle' => $result['detail'],
                    'url' => match ($result['type']) {
                        'patient' => $user->hasPermission('dispensations.view') && $user->hasPermission('dispensations.create')
                            ? route('dispensations.index', ['paciente_rut' => $result['identifier']])
                            : route('patients.file', $result['identifier']),
                        'product', 'lot' => route('inventory.index', ['buscar' => $result['identifier']]),
                    },
                ];
            })
            ->values()
            ->all();

        return response()->json($results);
    }
}
