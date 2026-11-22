<?php
// Actualiza el esquema de datos: 2026 11 12 000001 expand pharmaceutical product catalog.


use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    // Aplica esta migración al esquema de la base de datos.
    public function up(): void
    {
        if (! Schema::hasTable('formas_farmaceuticas')) {
            Schema::create('formas_farmaceuticas', /* Define la estructura de la tabla formas_farmaceuticas. */ function (Blueprint $table) {
                $table->engine('InnoDB');
                $table->bigIncrements('forma_farmaceutica_id');
                $table->string('nombre', 80)->unique();
                $table->boolean('activo')->default(true);
            });
        }

        if (! Schema::hasTable('unidades_medida')) {
            Schema::create('unidades_medida', /* Define la estructura de la tabla unidades_medida. */ function (Blueprint $table) {
                $table->engine('InnoDB');
                $table->bigIncrements('unidad_medida_id');
                $table->string('codigo', 30)->unique();
                $table->string('nombre', 80);
                $table->string('tipo', 20)->index();
                $table->boolean('activo')->default(true);
            });
        }

        if (! Schema::hasTable('principios_activos')) {
            Schema::create('principios_activos', /* Define la estructura de la tabla principios_activos. */ function (Blueprint $table) {
                $table->engine('InnoDB');
                $table->bigIncrements('principio_activo_id');
                $table->string('nombre', 150);
                $table->string('nombre_normalizado', 150)->unique();
            });
        }

        $this->ensureInnoDbCatalogTables();

        Schema::table('productos', /* Actualiza la estructura de la tabla productos. */ function (Blueprint $table) {
            if (! Schema::hasColumn('productos', 'es_medicamento')) {
                $table->boolean('es_medicamento')->nullable()->default(null);
            }
            if (! Schema::hasColumn('productos', 'forma_farmaceutica_id')) {
                $table->unsignedBigInteger('forma_farmaceutica_id')->nullable();
            }
            if (! Schema::hasColumn('productos', 'via_administracion')) {
                $table->string('via_administracion', 30)->nullable();
            }
            if (! Schema::hasColumn('productos', 'contenido_envase_cantidad')) {
                $table->decimal('contenido_envase_cantidad', 10, 3)->nullable();
            }
            if (! Schema::hasColumn('productos', 'contenido_envase_unidad_id')) {
                $table->unsignedBigInteger('contenido_envase_unidad_id')->nullable();
            }
            if (! Schema::hasColumn('productos', 'tipo_presentacion')) {
                $table->string('tipo_presentacion', 30)->nullable();
            }
            if (! Schema::hasColumn('productos', 'cantidad_envases_secundarios')) {
                $table->unsignedInteger('cantidad_envases_secundarios')->nullable();
            }
            if (! Schema::hasColumn('productos', 'tipo_envase_secundario')) {
                $table->string('tipo_envase_secundario', 30)->nullable();
            }
            if (! Schema::hasColumn('productos', 'bioequivalencia_estado')) {
                $table->string('bioequivalencia_estado', 30)->default('PENDIENTE');
            }
            if (! Schema::hasColumn('productos', 'tipo_control_legal')) {
                $table->string('tipo_control_legal', 30)->nullable();
            }
            if (! Schema::hasColumn('productos', 'registro_sanitario_isp')) {
                $table->string('registro_sanitario_isp', 80)->nullable();
            }
            if (! Schema::hasColumn('productos', 'estado_registro_sanitario')) {
                $table->string('estado_registro_sanitario', 30)->default('PENDIENTE');
            }
        });
        if (! Schema::hasIndex('productos', 'productos_registro_sanitario_isp_index')) {
            Schema::table('productos', /* Actualiza la estructura de la tabla productos. */ function (Blueprint $table) {
                $table->index('registro_sanitario_isp');
            });
        }

        if (DB::getDriverName() !== 'sqlite') {
            if (! Schema::hasForeignKey('productos', 'productos_forma_farmaceutica_fk')) {
                Schema::table('productos', /* Actualiza la estructura de la tabla productos. */ function (Blueprint $table) {
                    $table->foreign('forma_farmaceutica_id', 'productos_forma_farmaceutica_fk')
                        ->references('forma_farmaceutica_id')->on('formas_farmaceuticas');
                });
            }
            if (! Schema::hasForeignKey('productos', 'productos_contenido_unidad_fk')) {
                Schema::table('productos', /* Actualiza la estructura de la tabla productos. */ function (Blueprint $table) {
                    $table->foreign('contenido_envase_unidad_id', 'productos_contenido_unidad_fk')
                        ->references('unidad_medida_id')->on('unidades_medida');
                });
            }
        }

        if (! Schema::hasTable('productos_principios_activos')) {
            Schema::create('productos_principios_activos', /* Define la estructura de la tabla productos_principios_activos. */ function (Blueprint $table) {
                $table->engine('InnoDB');
                $table->bigInteger('producto_id');
                $table->unsignedBigInteger('principio_activo_id');
                $table->decimal('concentracion_cantidad', 10, 4);
                $table->unsignedBigInteger('concentracion_unidad_id');
                $table->decimal('concentracion_base_cantidad', 10, 4)->nullable();
                $table->unsignedBigInteger('concentracion_base_unidad_id')->nullable();
                $table->primary(['producto_id', 'principio_activo_id']);
                $table->foreign('producto_id', 'producto_principios_producto_fk')
                    ->references('producto_id')->on('productos')->cascadeOnDelete();
                $table->foreign('principio_activo_id', 'producto_principios_principio_fk')
                    ->references('principio_activo_id')->on('principios_activos');
                $table->foreign('concentracion_unidad_id', 'producto_principios_unidad_fk')
                    ->references('unidad_medida_id')->on('unidades_medida');
                $table->foreign('concentracion_base_unidad_id', 'producto_principios_unidad_base_fk')
                    ->references('unidad_medida_id')->on('unidades_medida');
            });
        }

        DB::table('formas_farmaceuticas')->insertOrIgnore(array_map(
            // Transforma cada elemento de la colección.
            fn (string $name) => ['nombre' => $name, 'activo' => true],
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

    // Ejecuta la operación de ensure inno db catalog tables.
    private function ensureInnoDbCatalogTables(): void
    {
        if (! in_array(DB::getDriverName(), ['mysql', 'mariadb'], true)) {
            return;
        }

        foreach (['formas_farmaceuticas', 'unidades_medida', 'principios_activos'] as $table) {
            DB::statement("ALTER TABLE `{$table}` ENGINE=InnoDB");
        }
    }

    // Revierte los cambios de esta migración.
    public function down(): void
    {
        Schema::dropIfExists('productos_principios_activos');

        if (DB::getDriverName() !== 'sqlite') {
            Schema::table('productos', /* Actualiza la estructura de la tabla productos. */ function (Blueprint $table) {
                $table->dropForeign('productos_forma_farmaceutica_fk');
                $table->dropForeign('productos_contenido_unidad_fk');
            });
        }
        Schema::table('productos', /* Actualiza la estructura de la tabla productos. */ function (Blueprint $table) {
            $table->dropIndex(['registro_sanitario_isp']);
        });
        if (DB::getDriverName() === 'sqlite') {
            foreach ([
                'estado_registro_sanitario',
                'registro_sanitario_isp',
                'tipo_control_legal',
                'bioequivalencia_estado',
                'tipo_envase_secundario',
                'cantidad_envases_secundarios',
                'tipo_presentacion',
                'contenido_envase_unidad_id',
                'contenido_envase_cantidad',
                'via_administracion',
                'forma_farmaceutica_id',
                'es_medicamento',
            ] as $column) {
                DB::statement("ALTER TABLE productos DROP COLUMN {$column}");
            }
        } else {
            Schema::table('productos', /* Actualiza la estructura de la tabla productos. */ function (Blueprint $table) {
                $table->dropColumn([
                    'es_medicamento',
                    'forma_farmaceutica_id',
                    'via_administracion',
                    'contenido_envase_cantidad',
                    'contenido_envase_unidad_id',
                    'tipo_presentacion',
                    'cantidad_envases_secundarios',
                    'tipo_envase_secundario',
                    'bioequivalencia_estado',
                    'tipo_control_legal',
                    'registro_sanitario_isp',
                    'estado_registro_sanitario',
                ]);
            });
        }

        Schema::dropIfExists('principios_activos');
        Schema::dropIfExists('unidades_medida');
        Schema::dropIfExists('formas_farmaceuticas');
    }
};
