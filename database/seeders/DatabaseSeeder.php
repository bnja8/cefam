<?php
// Carga datos iniciales de database seeder.


namespace Database\Seeders;

use App\Services\Patients\PatientFileService;
use App\Support\People\Rut;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        foreach ([
            ['Administrador', 'Gestión global del sistema'],
            ['Técnico Farmacéutico', 'Operación de farmacia e inventario'],
            ['Químico Farmacéutico', 'Supervisión y validación farmacéutica'],
        ] as [$name, $description]) {
            DB::table('roles')->insertOrIgnore(['nombre' => $name, 'descripcion' => $description]);
        }

        $this->call(AdministratorSeeder::class);

        foreach ([
            'Medicamentos',
            'Insumos clínicos',
            'Carro de Paro',
        ] as $category) {
            DB::table('categorias_producto')->insertOrIgnore(['nombre' => $category, 'descripcion' => $category]);
        }

        foreach ([
            ['Bodega Central', 'BODEGA'],
            ['Vitrina Farmacia', 'VITRINA'],
            ['Carro de Paro', 'CARRO_PARO'],
            ['Carro Unidosis', 'CARRO_UNIDOSIS'],
        ] as [$name, $type]) {
            DB::table('ubicaciones')->insertOrIgnore(['nombre' => $name, 'tipo' => $type]);
        }
        foreach ([
            ['Carro de Paro', 'CARRO_PARO', 'PARO'],
            ['Carro Unidosis', 'CARRO_UNIDOSIS', 'UNIDOSIS'],
        ] as [$name, $locationType, $cartType]) {
            $locationId = DB::table('ubicaciones')->where('tipo', $locationType)->value('ubicacion_id');
            DB::table('carros')->insertOrIgnore([
                'ubicacion_id' => $locationId,
                'tipo_carro' => $cartType,
                'nombre' => $name,
            ]);
        }

        foreach (['Salud Cardiovascular (PSCV)', 'Programa Respiratorio (ERA)', 'PACAM'] as $program) {
            DB::table('programas_salud')->insertOrIgnore(['nombre' => $program, 'descripcion' => $program, 'activo' => true]);
        }

        DB::table('profesionales_salud')->insertOrIgnore([
            'rut' => Rut::limpiarRut('12.345.678-5'),
            'nombre' => 'Camila',
            'apellidoPaterno' => 'Fuentes',
            'apellidoMaterno' => 'Soto',
            'tipo_profesional' => 'Médico Cirujano',
        ]);

        $samplePatients = [
            ['rut' => '12.345.678-5', 'nombre' => 'María', 'apellidoPaterno' => 'González', 'apellidoMaterno' => 'López', 'fecha_nacimiento' => '1958-03-12', 'telefono' => '912345678'],
            ['rut' => '15.876.543-8', 'nombre' => 'Juan', 'apellidoPaterno' => 'Pérez', 'apellidoMaterno' => 'Morales', 'fecha_nacimiento' => '1981-07-24', 'telefono' => '923456789'],
            ['rut' => '9.876.543-3', 'nombre' => 'Ana', 'apellidoPaterno' => 'Torres', 'apellidoMaterno' => 'Rojas', 'fecha_nacimiento' => '1952-11-02', 'telefono' => '934567890'],
        ];
        $patientFiles = app(PatientFileService::class);
        foreach ($samplePatients as $patient) {
            $patient['rut'] = Rut::limpiarRut($patient['rut']);
            DB::table('pacientes')->insertOrIgnore($patient + ['activo' => true]);
            $patientFiles->ensureForPatient($patient['rut']);
        }

        $categoryId = DB::table('categorias_producto')->where('nombre', 'Medicamentos')->value('categoria_id');
        $baseUnitId = DB::table('unidades_medida')->where('codigo', 'unidad')->value('unidad_medida_id');
        if (! $baseUnitId) {
            throw new \RuntimeException('Ejecuta primero las migraciones farmacéuticas para crear las unidades de medida.');
        }
        $products = [
            ['MED-001', 'Paracetamol 500 mg', false],
            ['MED-002', 'Amoxicilina 500 mg', false],
            ['MED-003', 'Losartán 50 mg', false],
            ['MED-004', 'Metformina 850 mg', false],
        ];
        foreach ($products as [$code, $name, $controlled]) {
            DB::table('productos')->insertOrIgnore([
                'numero_serie' => $code,
                'nombre' => $name,
                'descripcion' => $name,
                'categoria_id' => $categoryId,
                'unidad_base_inventario_id' => $baseUnitId,
                'tipo_producto' => 'MEDICAMENTO',
                'es_medicamento' => true,
                'via_administracion' => 'ORAL',
                'es_controlado' => $controlled,
                'activo' => true,
            ]);
            $productId = DB::table('productos')->where('numero_serie', $code)->value('producto_id');
            DB::table('productos')->where('producto_id', $productId)->whereNull('unidad_base_inventario_id')
                ->update(['unidad_base_inventario_id' => $baseUnitId]);
            DB::table('presentaciones_producto')->insertOrIgnore([
                'producto_id' => $productId,
                'nombre' => 'Unidad base',
                'factor_conversion' => 1,
                'activo' => true,
            ]);
        }

        $locationIds = [
            'BODEGA' => DB::table('ubicaciones')->where('tipo', 'BODEGA')->value('ubicacion_id'),
            'VITRINA' => DB::table('ubicaciones')->where('tipo', 'VITRINA')->value('ubicacion_id'),
        ];
        foreach ([
            ['MED-001', 'L250821', 1200, 180, 10],
            ['MED-002', 'L260201', 400, 20, 25],
            ['MED-003', 'L260102', 2500, 300, 20],
            ['MED-004', 'L260115', 1800, 250, 20],
        ] as [$code, $lotNumber, $warehouseStock, $displayStock, $minimum]) {
            $productId = DB::table('productos')->where('numero_serie', $code)->value('producto_id');
            DB::table('lotes')->insertOrIgnore([
                'producto_id' => $productId,
                'numero_lote' => $lotNumber,
                'fecha_vencimiento' => now()->addMonths(18)->toDateString(),
                'fecha_recepcion' => now(),
                'estado' => 'ACTIVO',
            ]);
            $lotId = DB::table('lotes')->where('producto_id', $productId)->where('numero_lote', $lotNumber)->value('lote_id');
            foreach ([$locationIds['BODEGA'] => $warehouseStock, $locationIds['VITRINA'] => $displayStock] as $locationId => $amount) {
                DB::table('inventario')->insertOrIgnore([
                    'lote_id' => $lotId,
                    'ubicacion_id' => $locationId,
                    'cantidad' => $amount,
                ]);
                DB::table('stock_minimos_ubicacion')->insertOrIgnore([
                    'producto_id' => $productId,
                    'ubicacion_id' => $locationId,
                    'stock_minimo' => $minimum,
                ]);
            }
        }
    }
}
