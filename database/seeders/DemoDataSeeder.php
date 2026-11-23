<?php
// Carga datos iniciales de demo data seeder.


namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class DemoDataSeeder extends Seeder
{
    // Ejecuta la operación de run.
    public function run(): void
    {
        $userRut = DB::table('usuarios')->where('rut', '12345674')->value('rut');
        if (! $userRut) {
            throw new \RuntimeException('Ejecuta primero php artisan db:seed para crear los datos base.');
        }

        $patients = [];
        foreach ([
            ['elena', 21000001, 'Elena', 'Muñoz', 'Navarro', '1960-04-18', true],
            ['roberto', 21000002, 'Roberto', 'Vargas', 'Leiva', '1974-09-03', true],
            ['patricia', 21000003, 'Patricia', 'Riquelme', 'Díaz', '1988-01-26', true],
            ['oscar', 21000004, 'Óscar', 'Contreras', 'Silva', '1955-12-11', true],
            ['ines', 21000005, 'Inés', 'Araya', 'Fuentes', '1992-06-07', false],
        ] as [$key, $rutBody, $name, $lastName, $secondLastName, $birthDate, $active]) {
            $rut = $this->rut($rutBody);
            DB::table('pacientes')->updateOrInsert(
                ['rut' => $rut],
                [
                    'nombre' => $name,
                    'apellidoPaterno' => $lastName,
                    'apellidoMaterno' => $secondLastName,
                    'fecha_nacimiento' => $birthDate,
                    'telefono' => '900000'.str_pad((string) ($rutBody % 1000), 3, '0', STR_PAD_LEFT),
                    'activo' => $active,
                ]
            );
            $patients[$key] = DB::table('pacientes')->where('rut', $rut)->value('rut');
        }

        $categoryIds = [
            'medicine' => DB::table('categorias_producto')->where('nombre', 'Medicamentos')->value('categoria_id'),
            'supplies' => DB::table('categorias_producto')->where('nombre', 'Insumos clínicos')->value('categoria_id'),
        ];
        foreach ($categoryIds as $categoryId) {
            if (! $categoryId) {
                throw new \RuntimeException('Ejecuta primero php artisan db:seed para crear las categorías base.');
            }
        }

        $baseUnitId = DB::table('unidades_medida')->where('codigo', 'unidad')->value('unidad_medida_id');
        if (! $baseUnitId) {
            throw new \RuntimeException('Ejecuta primero las migraciones farmacéuticas para crear las unidades de medida.');
        }

        $products = [];
        foreach ([
            ['DEMO-MED-001', 'Ibuprofeno 400 mg', 'medicine', true],
            ['DEMO-MED-002', 'Salbutamol inhalador 100 mcg', 'medicine', true],
            ['DEMO-MED-003', 'Omeprazol 20 mg', 'medicine', true],
            ['DEMO-INS-001', 'Guantes de nitrilo talla M', 'supplies', true],
            ['DEMO-MED-004', 'Producto de demostración inactivo', 'medicine', false],
        ] as [$code, $name, $category, $active]) {
            DB::table('productos')->updateOrInsert(
                ['numero_serie' => $code],
                [
                    'nombre' => $name,
                    'descripcion' => 'Registro de demostración para pruebas de búsqueda y filtros.',
                    'categoria_id' => $categoryIds[$category],
                    'unidad_base_inventario_id' => $baseUnitId,
                    'es_controlado' => false,
                    'activo' => $active,
                ]
            );
            $products[$code] = DB::table('productos')->where('numero_serie', $code)->value('producto_id');
            DB::table('productos')->where('producto_id', $products[$code])->whereNull('unidad_base_inventario_id')
                ->update(['unidad_base_inventario_id' => $baseUnitId]);
            DB::table('presentaciones_producto')->insertOrIgnore([
                'producto_id' => $products[$code],
                'nombre' => 'Unidad base',
                'factor_conversion' => 1,
                'activo' => true,
            ]);
        }

        $locations = [];
        foreach (['BODEGA', 'VITRINA', 'CARRO_PARO', 'CARRO_UNIDOSIS'] as $type) {
            $locations[$type] = DB::table('ubicaciones')->where('tipo', $type)->value('ubicacion_id');
            if (! $locations[$type]) {
                throw new \RuntimeException('Ejecuta primero php artisan db:seed para crear las ubicaciones base.');
            }
        }

        $lots = [];
        foreach ([
            ['DEMO-MED-001', 'DEMO-IBU-01', 365, 'ACTIVO', ['BODEGA' => 75, 'VITRINA' => 20]],
            ['DEMO-MED-001', 'DEMO-IBU-02', 35, 'ACTIVO', ['BODEGA' => 18]],
            ['DEMO-MED-002', 'DEMO-SAL-01', 240, 'ACTIVO', ['BODEGA' => 32, 'CARRO_PARO' => 5]],
            ['DEMO-MED-003', 'DEMO-OME-01', -10, 'ACTIVO', ['BODEGA' => 12]],
            ['DEMO-INS-001', 'DEMO-GUA-01', 300, 'ACTIVO', ['BODEGA' => 90, 'CARRO_PARO' => 10]],
            ['DEMO-MED-004', 'DEMO-INACTIVO-01', 300, 'ACTIVO', ['BODEGA' => 8]],
            ['DEMO-MED-001', 'DEMO-IBU-03', 200, 'INACTIVO', ['BODEGA' => 4]],
        ] as [$productCode, $lotNumber, $daysToExpiry, $state, $stockByLocation]) {
            $productId = $products[$productCode];
            DB::table('lotes')->updateOrInsert(
                ['producto_id' => $productId, 'numero_lote' => $lotNumber],
                [
                    'fecha_vencimiento' => now()->addDays($daysToExpiry)->toDateString(),
                    'fecha_recepcion' => now()->subDays(12),
                    'estado' => $state,
                ]
            );
            $lotId = DB::table('lotes')
                ->where('producto_id', $productId)
                ->where('numero_lote', $lotNumber)
                ->value('lote_id');
            $lots[$lotNumber] = $lotId;

            foreach ($stockByLocation as $locationType => $amount) {
                DB::table('inventario')->updateOrInsert(
                    ['lote_id' => $lotId, 'ubicacion_id' => $locations[$locationType]],
                    ['cantidad' => $amount]
                );
                DB::table('stock_minimos_ubicacion')->updateOrInsert(
                    ['producto_id' => $productId, 'ubicacion_id' => $locations[$locationType]],
                    ['stock_minimo' => $locationType === 'BODEGA' ? 25 : 5]
                );
            }
        }

        $professionalId = DB::table('personal_salud')->where('rut', '123456785')->value('rut');
        if ($professionalId) {
            foreach ([
                ['DEMO-REC-001', 'elena', 'DEMO-MED-001', 'VIGENTE', 90],
                ['DEMO-REC-002', 'roberto', 'DEMO-MED-002', 'VENCIDA', -20],
            ] as [$number, $patientKey, $productCode, $status, $daysUntilExpiry]) {
                $issuedAt = now()->subDays(7)->toDateString();
                if ($daysUntilExpiry < 0) {
                    $issuedAt = now()->addDays($daysUntilExpiry - 10)->toDateString();
                }
                $expiresAt = now()->addDays($daysUntilExpiry)->toDateString();
                DB::table('recetas')->updateOrInsert(
                    ['numero_receta' => $number],
                    [
                        'paciente_rut' => $patients[$patientKey],
                        'profesional_rut' => $professionalId,
                        'fecha_emision' => $issuedAt,
                        'fecha_vencimiento' => $expiresAt,
                        'tipo_receta' => 'ESTANDAR',
                        'estado' => $status,
                        'observacion' => 'Receta de demostración.',
                    ]
                );
                $prescriptionId = DB::table('recetas')->where('numero_receta', $number)->value('receta_id');
                DB::table('receta_detalle')->updateOrInsert(
                    ['receta_id' => $prescriptionId, 'producto_id' => $products[$productCode]],
                    [
                        'dosis' => '1 comprimido',
                        'frecuencia' => 'Cada 8 horas',
                        'cantidad_indicada' => 21,
                        'indicaciones' => 'Registro de demostración.',
                    ]
                );
            }
        }

        foreach ([
            ['elena', 'DEMO-TER-001', 'Sofía', 'Muñoz', 'Navarro', 'Hija', true],
            ['roberto', 'DEMO-TER-002', 'Pedro', 'Vargas', 'Leiva', 'Hermano', true],
            ['patricia', 'DEMO-TER-003', 'Laura', 'Riquelme', 'Díaz', 'Cuidadora', false],
        ] as [$patientKey, $rut, $name, $lastName, $secondLastName, $relationship, $active]) {
            DB::table('personas_autorizadas')->updateOrInsert(
                ['paciente_rut' => $patients[$patientKey], 'rut' => $rut],
                [
                    'nombre' => $name,
                    'apellidoPaterno' => $lastName,
                    'apellidoMaterno' => $secondLastName,
                    'vinculo' => $relationship,
                    'activo' => $active,
                ]
            );
        }

        $programs = [];
        foreach ([
            ['DEMO-Programa Cardiovascular', true],
            ['DEMO-Programa Respiratorio', true],
            ['DEMO-Programa Inactivo', false],
        ] as [$name, $active]) {
            DB::table('programas_salud')->updateOrInsert(
                ['nombre' => $name],
                [
                    'descripcion' => 'Programa de demostración para pruebas.',
                    'activo' => $active,
                ]
            );
            $programs[$name] = DB::table('programas_salud')->where('nombre', $name)->value('programa_id');
        }

        foreach ([
            ['elena', 'DEMO-Programa Cardiovascular', -180, null, true, 'Control cardiovascular'],
            ['roberto', 'DEMO-Programa Respiratorio', -90, -10, true, 'Cobertura respiratoria'],
            ['patricia', 'DEMO-Programa Cardiovascular', 20, null, true, 'Inicio programado'],
            ['oscar', 'DEMO-Programa Cardiovascular', -120, null, false, 'Afiliación desactivada'],
            ['ines', 'DEMO-Programa Inactivo', -60, null, true, 'Programa o paciente inactivo'],
        ] as [$patientKey, $programName, $startDays, $endDays, $active, $coverage]) {
            $start = now()->addDays($startDays)->toDateString();
            DB::table('paciente_programa')->updateOrInsert(
                [
                    'paciente_rut' => $patients[$patientKey],
                    'programa_id' => $programs[$programName],
                    'fecha_inicio' => $start,
                ],
                [
                    'fecha_termino' => $endDays === null ? null : now()->addDays($endDays)->toDateString(),
                    'cobertura' => $coverage,
                    'activo' => $active,
                ]
            );
        }

        $requestIds = [];
        $monthStart = now()->startOfMonth();
        foreach ([
            ['PENDIENTE', 3, 'DEMO-MED-001', 40],
            ['SOLICITADA', 8, 'DEMO-MED-002', 25],
            ['RECIBIDA', 14, 'DEMO-INS-001', 60],
            ['CANCELADA', 20, 'DEMO-MED-003', 15],
        ] as [$state, $daysAgo, $productCode, $quantity]) {
            $requestedAt = $monthStart->copy()->subDays($daysAgo)->setTime(10, 0);
            DB::table('solicitudes_reposicion')->updateOrInsert(
                ['usuario_rut' => $userRut, 'estado' => $state, 'fecha_solicitud' => $requestedAt],
                ['usuario_rut' => $userRut]
            );
            $requestId = DB::table('solicitudes_reposicion')
                ->where('usuario_rut', $userRut)
                ->where('estado', $state)
                ->where('fecha_solicitud', $requestedAt)
                ->value('solicitud_id');
            $requestIds[$state] = $requestId;
            DB::table('solicitud_reposicion_detalle')->updateOrInsert(
                ['solicitud_id' => $requestId, 'producto_id' => $products[$productCode]],
                ['cantidad_solicitada' => $quantity]
            );
        }

        foreach ([
            ['DEMO-REM-001', 'CENABAST', 'RECIBIDA', 'DEMO-INS-001', 'DEMO-GUA-02', 60, 14],
            ['DEMO-REM-002', 'Proveedor local', null, 'DEMO-MED-001', 'DEMO-IBU-04', 30, 2],
        ] as [$shipmentCode, $origin, $requestState, $productCode, $lotNumber, $quantity, $daysAgo]) {
            $receivedAt = $monthStart->copy()->subDays($daysAgo)->setTime(14, 30);
            DB::table('remesas')->updateOrInsert(
                ['codigo_remesa' => $shipmentCode],
                [
                    'origen' => $origin,
                    'fecha_recepcion' => $receivedAt,
                    'solicitud_id' => $requestState ? $requestIds[$requestState] : null,
                    'usuario_rut' => $userRut,
                ]
            );
            $shipmentId = DB::table('remesas')->where('codigo_remesa', $shipmentCode)->value('remesa_id');
            DB::table('remesa_detalle')->updateOrInsert(
                [
                    'remesa_id' => $shipmentId,
                    'producto_id' => $products[$productCode],
                    'numero_lote' => $lotNumber,
                ],
                [
                    'fecha_vencimiento' => now()->addYear()->toDateString(),
                    'cantidad' => $quantity,
                ]
            );
        }

        foreach ([
            ['DEMO-IBU-01', 'BODEGA', 'RECEPCION', 75, 1, 'Recepción de demostración'],
            ['DEMO-IBU-01', 'VITRINA', 'TRASPASO_ENTRADA', 20, 2, 'Reposición de vitrina de demostración'],
            ['DEMO-IBU-02', 'BODEGA', 'AJUSTE_ENTRADA', 18, 4, 'Ajuste de inventario de demostración'],
            ['DEMO-SAL-01', 'CARRO_PARO', 'TRASPASO_ENTRADA', 5, 6, 'Reposición de carro de paro de demostración'],
            ['DEMO-GUA-01', 'CARRO_PARO', 'TRASPASO_ENTRADA', 10, 9, 'Reposición de insumos de demostración'],
        ] as [$lotNumber, $locationType, $movementType, $quantity, $daysAgo, $observation]) {
            $movementAt = now()->subDays($daysAgo)->setTime(11, 0);
            DB::table('movimientos_inventario')->updateOrInsert(
                [
                    'lote_id' => $lots[$lotNumber],
                    'ubicacion_id' => $locations[$locationType],
                    'referencia_tipo' => 'DEMO_DATA',
                    'referencia_id' => $daysAgo,
                ],
                [
                    'tipo_movimiento' => $movementType,
                    'cantidad' => $quantity,
                    'fecha_hora' => $movementAt,
                    'usuario_rut' => $userRut,
                    'observacion' => $observation,
                ]
            );
        }
    }

    // Ejecuta la operación de rut.
    private function rut(int $body): string
    {
        $digits = (string) $body;
        $sum = 0;
        $weight = 2;

        for ($index = strlen($digits) - 1; $index >= 0; $index--) {
            $sum += (int) $digits[$index] * $weight;
            $weight = $weight === 7 ? 2 : $weight + 1;
        }

        $remainder = 11 - ($sum % 11);
        $verifier = match ($remainder) {
            11 => '0',
            10 => 'K',
            default => (string) $remainder,
        };

        return $digits.$verifier;
    }
}
