<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        foreach ([
            'personas_autorizadas' => ['persona_autorizada_id', 'paciente_rut'],
            'entregas' => ['persona_autorizada_id'],
        ] as $table => $columns) {
            if (! Schema::hasTable($table)) {
                throw new RuntimeException("No existe la tabla requerida para el esquema actual: {$table}.");
            }

            foreach ($columns as $column) {
                if (! Schema::hasColumn($table, $column)) {
                    throw new RuntimeException("Falta la columna requerida para el esquema actual: {$table}.{$column}.");
                }
            }
        }
    }

    public function down(): void
    {
        // El nombre canónico no se revierte a un nombre histórico.
    }
};
