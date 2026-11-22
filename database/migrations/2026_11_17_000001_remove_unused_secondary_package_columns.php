<?php
// Actualiza el esquema de datos: 2026 11 17 000001 remove unused secondary package columns.


use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    // Aplica esta migración al esquema de la base de datos.
    public function up(): void
    {
        $columns = array_values(array_filter(
            ['cantidad_envases_secundarios', 'tipo_envase_secundario'],
            // Callback que completa un paso de la operación actual.
            fn (string $column) => Schema::hasColumn('productos', $column)
        ));

        if ($columns !== []) {
            Schema::table('productos', /* Actualiza la estructura de la tabla productos. */ function (Blueprint $table) use ($columns) {
                $table->dropColumn($columns);
            });
        }
    }

    // Revierte los cambios de esta migración.
    public function down(): void
    {
        if (! Schema::hasTable('productos')) {
            return;
        }

        if (! Schema::hasColumn('productos', 'cantidad_envases_secundarios')) {
            Schema::table('productos', /* Actualiza la estructura de la tabla productos. */ function (Blueprint $table) {
                $table->unsignedInteger('cantidad_envases_secundarios')->nullable();
            });
        }

        if (! Schema::hasColumn('productos', 'tipo_envase_secundario')) {
            Schema::table('productos', /* Actualiza la estructura de la tabla productos. */ function (Blueprint $table) {
                $table->string('tipo_envase_secundario', 30)->nullable();
            });
        }
    }
};
