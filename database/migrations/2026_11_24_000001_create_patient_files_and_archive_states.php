<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('fichas_pacientes')) {
            Schema::create('fichas_pacientes', function (Blueprint $table) {
                $table->engine = 'InnoDB';
                $table->bigIncrements('ficha_id');
                $table->string('paciente_rut', 12)->unique();
                $table->timestamps();
                $table->foreign('paciente_rut')
                    ->references('rut')
                    ->on('pacientes');
            });
        }

        if (DB::getDriverName() === 'mysql') {
            $tableMetadata = DB::selectOne(
                'SELECT ENGINE FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?',
                ['fichas_pacientes']
            );
            if ($tableMetadata !== null && $tableMetadata->ENGINE !== 'InnoDB') {
                DB::statement('ALTER TABLE fichas_pacientes ENGINE=InnoDB');
            }
        }

        if (! Schema::hasForeignKey('fichas_pacientes', 'fichas_pacientes_paciente_rut_foreign')) {
            Schema::table('fichas_pacientes', function (Blueprint $table) {
                $table->foreign('paciente_rut')
                    ->references('rut')
                    ->on('pacientes');
            });
        }

        DB::table('pacientes')->orderBy('rut')->chunkById(200, function ($patients) {
            $now = now();
            $files = $patients->map(fn ($patient) => [
                'paciente_rut' => $patient->rut,
                'created_at' => $now,
                'updated_at' => $now,
            ])->all();
            if ($files !== []) {
                DB::table('fichas_pacientes')->insertOrIgnore($files);
            }
        }, 'rut');

        Schema::table('recetas', function (Blueprint $table) {
            if (! Schema::hasColumn('recetas', 'ficha_id')) {
                $table->unsignedBigInteger('ficha_id')->nullable();
            }
            if (! Schema::hasColumn('recetas', 'fecha_registro')) {
                $table->timestamp('fecha_registro')->useCurrent();
            }
            if (! Schema::hasColumn('recetas', 'estado_ficha')) {
                $table->string('estado_ficha', 20)->default('RECIENTE');
            }
            if (! Schema::hasIndex('recetas', 'recetas_estado_ficha_registro_idx')) {
                $table->index(['estado_ficha', 'fecha_registro'], 'recetas_estado_ficha_registro_idx');
            }
            if (DB::getDriverName() !== 'sqlite' && ! Schema::hasForeignKey('recetas', 'recetas_ficha_id_foreign')) {
                $table->foreign('ficha_id')->references('ficha_id')->on('fichas_pacientes');
            }
        });
        Schema::table('entregas', function (Blueprint $table) {
            if (! Schema::hasColumn('entregas', 'ficha_id')) {
                $table->unsignedBigInteger('ficha_id')->nullable();
            }
            if (! Schema::hasColumn('entregas', 'estado_ficha')) {
                $table->string('estado_ficha', 20)->default('RECIENTE');
            }
            if (! Schema::hasIndex('entregas', 'entregas_estado_ficha_fecha_idx')) {
                $table->index(['estado_ficha', 'fecha_hora'], 'entregas_estado_ficha_fecha_idx');
            }
            if (DB::getDriverName() !== 'sqlite' && ! Schema::hasForeignKey('entregas', 'entregas_ficha_id_foreign')) {
                $table->foreign('ficha_id')->references('ficha_id')->on('fichas_pacientes');
            }
        });

        $fichaIds = DB::table('fichas_pacientes')->pluck('ficha_id', 'paciente_rut');
        DB::table('recetas')->orderBy('receta_id')->chunkById(200, function ($recipes) use ($fichaIds) {
            foreach ($recipes as $recipe) {
                if ($recipe->ficha_id === null) {
                    $fichaId = $fichaIds->get($recipe->paciente_rut);
                    if ($fichaId === null) {
                        throw new RuntimeException('No existe una ficha para el paciente de una receta.');
                    }
                    DB::table('recetas')->where('receta_id', $recipe->receta_id)->update([
                        'ficha_id' => $fichaId,
                        'fecha_registro' => $recipe->fecha_emision.' 00:00:00',
                    ]);
                }
            }
        }, 'receta_id');
        DB::table('entregas')->orderBy('entrega_id')->chunkById(200, function ($deliveries) use ($fichaIds) {
            foreach ($deliveries as $delivery) {
                if ($delivery->ficha_id === null) {
                    $fichaId = $fichaIds->get($delivery->paciente_rut);
                    if ($fichaId === null) {
                        throw new RuntimeException('No existe una ficha para el paciente de una dispensación.');
                    }
                    DB::table('entregas')->where('entrega_id', $delivery->entrega_id)->update([
                        'ficha_id' => $fichaId,
                    ]);
                }
            }
        }, 'entrega_id');
    }

    public function down(): void
    {
        if (Schema::hasTable('recetas')) {
            Schema::table('recetas', function (Blueprint $table) {
                if (DB::getDriverName() !== 'sqlite') {
                    $table->dropForeign(['ficha_id']);
                }
                $table->dropIndex('recetas_estado_ficha_registro_idx');
                $table->dropColumn(['ficha_id', 'fecha_registro', 'estado_ficha']);
            });
        }
        if (Schema::hasTable('entregas')) {
            Schema::table('entregas', function (Blueprint $table) {
                if (DB::getDriverName() !== 'sqlite') {
                    $table->dropForeign(['ficha_id']);
                }
                $table->dropIndex('entregas_estado_ficha_fecha_idx');
                $table->dropColumn(['ficha_id', 'estado_ficha']);
            });
        }

        Schema::dropIfExists('fichas_pacientes');
    }
};
