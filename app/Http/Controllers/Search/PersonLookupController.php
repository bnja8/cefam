<?php

namespace App\Http\Controllers\Search;

use App\Http\Controllers\Controller;
use App\Support\People\Rut;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PersonLookupController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $data = $request->validate([
            'rut' => ['required', 'string', 'max:12'],
        ]);
        $rut = Rut::limpiarRut($data['rut']);
        if (! Rut::validarRut($rut)) {
            return response()->json(['message' => 'El RUT ingresado no es válido.'], 422);
        }

        $records = [];
        foreach ([
            'usuarios' => ['rut', 'nombre', 'apellidoPaterno', 'apellidoMaterno', 'telefono'],
            'pacientes' => ['rut', 'nombre', 'apellidoPaterno', 'apellidoMaterno', 'fecha_nacimiento', 'telefono'],
            'profesionales_salud' => ['rut', 'nombre', 'apellidoPaterno', 'apellidoMaterno', 'tipo_profesional'],
            'personas_autorizadas' => ['rut', 'nombre', 'apellidoPaterno', 'apellidoMaterno', 'vinculo'],
        ] as $table => $columns) {
            $query = DB::table($table)->where('rut', $rut);
            if ($table === 'personas_autorizadas') {
                $query->orderBy('persona_autorizada_id');
            }
            $record = $query->first($columns);
            if ($record) {
                $records[$table] = $record;
            }
        }

        return response()->json(['records' => $records]);
    }
}
