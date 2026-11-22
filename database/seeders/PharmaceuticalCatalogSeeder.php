<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class PharmaceuticalCatalogSeeder extends Seeder
{
    public function run(): void
    {
        DB::table('formas_farmaceuticas')->insertOrIgnore(array_map(
            static fn (string $name): array => ['nombre' => $name, 'activo' => true],
            [
                'Comprimido', 'Cápsula', 'Solución oral', 'Suspensión oral',
                'Polvo', 'Polvo para suspensión', 'Crema', 'Pomada', 'Gel',
                'Jarabe', 'Inyectable', 'Gotas', 'Inhalador', 'Supositorio',
                'Parche transdérmico', 'Otro',
            ]
        ));

        DB::table('unidades_medida')->insertOrIgnore([
            ['codigo' => 'mg', 'nombre' => 'miligramo', 'tipo' => 'MASA', 'activo' => true],
            ['codigo' => 'g', 'nombre' => 'gramo', 'tipo' => 'MASA', 'activo' => true],
            ['codigo' => 'mcg', 'nombre' => 'microgramo', 'tipo' => 'MASA', 'activo' => true],
            ['codigo' => 'mL', 'nombre' => 'mililitro', 'tipo' => 'VOLUMEN', 'activo' => true],
            ['codigo' => 'L', 'nombre' => 'litro', 'tipo' => 'VOLUMEN', 'activo' => true],
            ['codigo' => '%', 'nombre' => 'porcentaje', 'tipo' => 'PORCENTAJE', 'activo' => true],
            ['codigo' => 'comprimido', 'nombre' => 'comprimido', 'tipo' => 'PRESENTACION', 'activo' => true],
            ['codigo' => 'cápsula', 'nombre' => 'cápsula', 'tipo' => 'PRESENTACION', 'activo' => true],
            ['codigo' => 'sobre', 'nombre' => 'sobre', 'tipo' => 'PRESENTACION', 'activo' => true],
            ['codigo' => 'frasco', 'nombre' => 'frasco', 'tipo' => 'PRESENTACION', 'activo' => true],
            ['codigo' => 'tubo', 'nombre' => 'tubo', 'tipo' => 'PRESENTACION', 'activo' => true],
            ['codigo' => 'dosis', 'nombre' => 'dosis', 'tipo' => 'PRESENTACION', 'activo' => true],
            ['codigo' => 'unidad', 'nombre' => 'unidad', 'tipo' => 'PRESENTACION', 'activo' => true],
        ]);
    }
}
