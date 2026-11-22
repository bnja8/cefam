<?php
// Actualiza el esquema de datos: 2026 11 15 000001 add inventory base units and presentations.


use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    // Aplica esta migración al esquema de la base de datos.
    public function up(): void
    {
        Schema::table('productos', /* Actualiza la estructura de la tabla productos. */ function (Blueprint $table) {
            if (! Schema::hasColumn('productos', 'unidad_base_inventario_id')) {
                $table->unsignedBigInteger('unidad_base_inventario_id')->nullable();
            }
        });

        if (! Schema::hasTable('presentaciones_producto')) {
            Schema::create('presentaciones_producto', /* Define la estructura de la tabla presentaciones_producto. */ function (Blueprint $table) {
                $table->bigIncrements('presentacion_id');
                $table->bigInteger('producto_id');
                $table->string('nombre', 100);
                $table->decimal('factor_conversion', 12, 4);
                $table->boolean('activo')->default(true);
                $table->unique(['producto_id', 'nombre']);
                $table->index(['producto_id', 'activo']);
                $table->foreign('producto_id')->references('producto_id')->on('productos')->cascadeOnDelete();
            });
        }

        Schema::table('remesa_detalle', /* Actualiza la estructura de la tabla remesa_detalle. */ function (Blueprint $table) {
            foreach ([
                'cantidad_envases' => /* Actualiza la estructura de la tabla remesa_detalle. */ fn () => $table->decimal('cantidad_envases', 12, 2)->default(0),
                'unidades_sueltas' => /* Actualiza la estructura de la tabla remesa_detalle. */ fn () => $table->decimal('unidades_sueltas', 12, 2)->default(0),
                'presentacion_nombre' => /* Actualiza la estructura de la tabla remesa_detalle. */ fn () => $table->string('presentacion_nombre', 100)->nullable(),
                'factor_conversion' => /* Callback que completa un paso de la operación actual. */ fn () => $table->decimal('factor_conversion', 12, 4)->nullable(),
                'unidad_base_codigo' => /* Callback que completa un paso de la operación actual. */ fn () => $table->string('unidad_base_codigo', 30)->nullable(),
            ] as $column => $addColumn) {
                if (! Schema::hasColumn('remesa_detalle', $column)) {
                    $addColumn();
                }
            }
        });

        Schema::table('movimientos_inventario', /* Actualiza la estructura de la tabla movimientos_inventario. */ function (Blueprint $table) {
            foreach ([
                'cantidad_original' => /* Actualiza la estructura de la tabla movimientos_inventario. */ fn () => $table->decimal('cantidad_original', 12, 2)->nullable(),
                'unidad_ingresada' => /* Actualiza la estructura de la tabla movimientos_inventario. */ fn () => $table->string('unidad_ingresada', 100)->nullable(),
                'factor_conversion' => /* Actualiza la estructura de la tabla movimientos_inventario. */ fn () => $table->decimal('factor_conversion', 12, 4)->nullable(),
                'cantidad_envases' => /* Callback que completa un paso de la operación actual. */ fn () => $table->decimal('cantidad_envases', 12, 2)->nullable(),
                'unidades_sueltas' => /* Callback que completa un paso de la operación actual. */ fn () => $table->decimal('unidades_sueltas', 12, 2)->nullable(),
                'presentacion_nombre' => /* Callback que completa un paso de la operación actual. */ fn () => $table->string('presentacion_nombre', 100)->nullable(),
                'unidad_base_codigo' => /* Callback que completa un paso de la operación actual. */ fn () => $table->string('unidad_base_codigo', 30)->nullable(),
            ] as $column => $addColumn) {
                if (! Schema::hasColumn('movimientos_inventario', $column)) {
                    $addColumn();
                }
            }
        });

        $defaultUnitId = DB::table('unidades_medida')->where('codigo', 'unidad')->value('unidad_medida_id');
        if (! $defaultUnitId && DB::table('productos')->exists()) {
            throw new RuntimeException('No existe la unidad base "unidad" en unidades_medida.');
        }

        if ($defaultUnitId) {
            DB::table('productos')->whereNull('unidad_base_inventario_id')->update([
                'unidad_base_inventario_id' => $defaultUnitId,
            ]);
        }
        DB::table('productos')
            ->where('es_medicamento', true)
            ->whereNotNull('contenido_envase_unidad_id')
            ->update(['unidad_base_inventario_id' => DB::raw('contenido_envase_unidad_id')]);

        DB::table('productos')->orderBy('producto_id')->chunkById(500, /* Añade una condición a la consulta. */ function ($products) {
            foreach ($products as $product) {
                DB::table('presentaciones_producto')->insertOrIgnore([
                    'producto_id' => $product->producto_id,
                    'nombre' => 'Unidad base',
                    'factor_conversion' => 1,
                    'activo' => true,
                ]);

                if (! $product->es_medicamento || ! $product->contenido_envase_cantidad || ! $product->contenido_envase_unidad_id) {
                    continue;
                }

                $unitCode = DB::table('unidades_medida')
                    ->where('unidad_medida_id', $product->contenido_envase_unidad_id)
                    ->value('codigo');
                $presentationName = trim(($product->tipo_presentacion ?: 'Envase').' · '.
                    rtrim(rtrim(number_format((float) $product->contenido_envase_cantidad, 3, '.', ''), '0'), '.').' '.$unitCode);
                DB::table('presentaciones_producto')->insertOrIgnore([
                    'producto_id' => $product->producto_id,
                    'nombre' => $presentationName,
                    'factor_conversion' => $product->contenido_envase_cantidad,
                    'activo' => true,
                ]);
            }
        }, 'producto_id', 'producto_id');
    }

    // Revierte los cambios de esta migración.
    public function down(): void
    {
        Schema::dropIfExists('presentaciones_producto');

        Schema::table('remesa_detalle', /* Actualiza la estructura de la tabla remesa_detalle. */ function (Blueprint $table) {
            $table->dropColumn([
                'cantidad_envases',
                'unidades_sueltas',
                'presentacion_nombre',
                'factor_conversion',
                'unidad_base_codigo',
            ]);
        });

        Schema::table('movimientos_inventario', /* Actualiza la estructura de la tabla remesa_detalle. */ function (Blueprint $table) {
            $table->dropColumn([
                'cantidad_original',
                'unidad_ingresada',
                'factor_conversion',
                'cantidad_envases',
                'unidades_sueltas',
                'presentacion_nombre',
                'unidad_base_codigo',
            ]);
        });

        Schema::table('productos', /* Actualiza la estructura de la tabla productos. */ function (Blueprint $table) {
            $table->dropColumn('unidad_base_inventario_id');
        });
    }
};
