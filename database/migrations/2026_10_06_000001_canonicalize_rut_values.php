<?php
// Actualiza el esquema de datos: 2026 10 06 000001 canonicalize rut values.


use App\Support\People\Rut;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const TABLES = [
        'usuarios' => 'rut',
        'pacientes' => 'rut',
        'profesionales_salud' => 'rut',
        'personas_autorizadas' => 'persona_autorizada_id',
    ];

    // Aplica esta migración al esquema de la base de datos.
    public function up(): void
    {
        $changes = [];
        foreach (self::TABLES as $table => $primaryKey) {
            $seen = [];
            $columns = array_values(array_unique([$primaryKey, 'rut']));
            if ($table === 'personas_autorizadas') {
                $columns[] = 'paciente_rut';
            }
            foreach (DB::table($table)->get($columns) as $row) {
                $canonicalRut = Rut::limpiarRut((string) $row->rut);
                if (! preg_match('/^\d{7,8}[0-9K]$/', $canonicalRut)) {
                    throw new RuntimeException("No se puede normalizar el RUT del registro {$table}.{$row->{$primaryKey}}.");
                }
                $uniqueKey = $table === 'personas_autorizadas'
                    ? $row->paciente_rut.':'.$canonicalRut
                    : $canonicalRut;
                if (isset($seen[$uniqueKey])) {
                    throw new RuntimeException("Hay RUT duplicados después de normalizar en la tabla {$table}; resuelve la colisión y vuelve a migrar.");
                }
                $seen[$uniqueKey] = true;
                if ($canonicalRut !== $row->rut) {
                    $changes[] = [$table, $primaryKey, $row->{$primaryKey}, $canonicalRut];
                }
            }
        }

        DB::transaction(/* Agrupa los cambios de datos en una transacción. */ function () use ($changes) {
            foreach ($changes as [$table, $primaryKey, $id, $canonicalRut]) {
                DB::table($table)->where($primaryKey, $id)->update(['rut' => $canonicalRut]);
            }

            foreach (array_keys(self::TABLES) as $table) {
                $uniqueColumns = $table === 'personas_autorizadas'
                    ? ['paciente_rut', 'rut']
                    : ['rut'];
                $hasUniqueIndex = collect(Schema::getIndexes($table))->contains(
                    fn (array $index) => $index['unique'] && $index['columns'] === $uniqueColumns
                );

                if (! $hasUniqueIndex) {
                    Schema::table($table, function ($blueprint) use ($uniqueColumns) {
                        $blueprint->unique($uniqueColumns);
                    });
                }
            }
        });
    }

    // Revierte los cambios de esta migración.
    public function down(): void
    {
        // Canonical RUT values intentionally remain normalized on rollback.
    }
};
