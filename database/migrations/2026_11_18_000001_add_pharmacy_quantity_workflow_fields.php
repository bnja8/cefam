<?php
// Actualiza el esquema de datos: 2026 11 18 000001 add pharmacy quantity workflow fields.


use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    // Aplica esta migración al esquema de la base de datos.
    public function up(): void
    {
        if (! Schema::hasColumn('productos', 'tipo_producto')) {
            Schema::table('productos', /* Actualiza la estructura de la tabla productos. */ function (Blueprint $table) {
                $table->string('tipo_producto', 30)->default('INSUMO_CLINICO');
            });
        }

        DB::table('productos')
            ->where('es_medicamento', true)
            ->update(['tipo_producto' => 'MEDICAMENTO']);

        Schema::table('receta_detalle', /* Actualiza la estructura de la tabla productos. */ function (Blueprint $table) {
            if (! Schema::hasColumn('receta_detalle', 'via_administracion')) {
                $table->string('via_administracion', 30)->nullable();
            }
            if (! Schema::hasColumn('receta_detalle', 'unidades_por_toma')) {
                $table->decimal('unidades_por_toma', 12, 2)->nullable();
            }
            if (! Schema::hasColumn('receta_detalle', 'tomas_diarias')) {
                $table->unsignedSmallInteger('tomas_diarias')->nullable();
            }
            if (! Schema::hasColumn('receta_detalle', 'duracion_dias')) {
                $table->unsignedSmallInteger('duracion_dias')->nullable();
            }
            if (! Schema::hasColumn('receta_detalle', 'es_sos')) {
                $table->boolean('es_sos')->default(false);
            }
        });
    }

    // Revierte los cambios de esta migración.
    public function down(): void
    {
        Schema::table('receta_detalle', /* Actualiza la estructura de la tabla receta_detalle. */ function (Blueprint $table) {
            $table->dropColumn(['via_administracion', 'unidades_por_toma', 'tomas_diarias', 'duracion_dias', 'es_sos']);
        });

        Schema::table('productos', /* Actualiza la estructura de la tabla receta_detalle. */ function (Blueprint $table) {
            $table->dropColumn('tipo_producto');
        });
    }
};
