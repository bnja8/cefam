<?php


namespace App\Http\Controllers\Inventory;

use App\Http\Controllers\Controller;
use App\Http\Requests\LookupBarcodeRequest;
use App\Services\Inventory\BarcodeLookupService;
use Illuminate\Http\JsonResponse;

class BarcodeLookupController extends Controller
{
    public function __invoke(LookupBarcodeRequest $request, BarcodeLookupService $lookup): JsonResponse
    {
        $product = $lookup->findProduct($request->validated('codigo_barra'));

        if (! $product) {
            return response()->json(['message' => 'No hay un producto activo asociado a ese código de barras.'], 404);
        }

        return response()->json($product);
    }
}
