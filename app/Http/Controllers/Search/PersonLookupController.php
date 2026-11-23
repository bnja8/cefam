<?php

namespace App\Http\Controllers\Search;

use App\Http\Controllers\Controller;
use App\Models\Usuario;
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
        $user = $request->user();
        $tablePermissions = [
            'usuarios' => 'users.view',
            'pacientes' => 'patients.view',
            'personal_salud' => 'personal.view',
            'personas_autorizadas' => 'authorized-people.view',
        ];
        foreach ([
            'usuarios' => ['rut', 'nombre', 'apellidoPaterno', 'apellidoMaterno', 'telefono'],
            'pacientes' => ['rut', 'nombre', 'apellidoPaterno', 'apellidoMaterno', 'fecha_nacimiento', 'telefono'],
            'personal_salud' => ['rut', 'nombre', 'apellidoPaterno', 'apellidoMaterno', 'tipo_profesional'],
            'personas_autorizadas' => ['rut', 'nombre', 'apellidoPaterno', 'apellidoMaterno', 'vinculo'],
        ] as $table => $columns) {
            if (! $user instanceof Usuario || ! $user->hasPermission($tablePermissions[$table])) {
                continue;
            }

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
