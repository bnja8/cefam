<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('solicitudes_ajuste_grupos')) {
            Schema::create('solicitudes_ajuste_grupos', function (Blueprint $table): void {
                $table->engine('InnoDB');
                $table->bigIncrements('grupo_ajuste_id');
                $table->string('estado', 20)->default('PENDIENTE');
                $table->string('usuario_solicita_rut', 12);
                $table->boolean('solicitante_es_qf')->default(false);
                $table->string('usuario_autoriza_rut', 12)->nullable();
                $table->text('motivo_resolucion')->nullable();
                $table->timestamp('fecha_solicitud')->useCurrent();
                $table->timestamp('fecha_resolucion')->nullable();
                $table->index(['estado', 'fecha_solicitud'], 'solicitudes_ajuste_grupos_estado_fecha_idx');
                $table->index(['usuario_solicita_rut', 'fecha_solicitud'], 'solicitudes_ajuste_grupos_solicitante_fecha_idx');
            });
        }

        if (in_array(DB::connection()->getDriverName(), ['mysql', 'mariadb'], true)) {
            $groupTable = DB::selectOne(
                'SELECT ENGINE FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?',
                ['solicitudes_ajuste_grupos']
            );
            if (strtoupper($groupTable->ENGINE ?? '') !== 'INNODB') {
                DB::statement('ALTER TABLE solicitudes_ajuste_grupos ENGINE=InnoDB');
            }
        }

        if (! Schema::hasForeignKey('solicitudes_ajuste_grupos', 'solicitudes_ajuste_grupos_solicitante_fk')) {
            Schema::table('solicitudes_ajuste_grupos', function (Blueprint $table): void {
                $table->foreign('usuario_solicita_rut', 'solicitudes_ajuste_grupos_solicitante_fk')
                    ->references('rut')->on('usuarios');
            });
        }
        if (! Schema::hasForeignKey('solicitudes_ajuste_grupos', 'solicitudes_ajuste_grupos_autorizador_fk')) {
            Schema::table('solicitudes_ajuste_grupos', function (Blueprint $table): void {
                $table->foreign('usuario_autoriza_rut', 'solicitudes_ajuste_grupos_autorizador_fk')
                    ->references('rut')->on('usuarios');
            });
        }

        if (! Schema::hasColumn('solicitudes_ajuste_inventario', 'grupo_ajuste_id')) {
            Schema::table('solicitudes_ajuste_inventario', function (Blueprint $table): void {
                $table->unsignedBigInteger('grupo_ajuste_id')->nullable();
            });
        }
        if (! Schema::hasIndex('solicitudes_ajuste_inventario', 'solicitudes_ajuste_grupo_idx')) {
            Schema::table('solicitudes_ajuste_inventario', function (Blueprint $table): void {
                $table->index('grupo_ajuste_id', 'solicitudes_ajuste_grupo_idx');
            });
        }
        if (! Schema::hasForeignKey('solicitudes_ajuste_inventario', 'solicitudes_ajuste_grupo_fk')) {
            Schema::table('solicitudes_ajuste_inventario', function (Blueprint $table): void {
                $table->foreign('grupo_ajuste_id', 'solicitudes_ajuste_grupo_fk')
                    ->references('grupo_ajuste_id')->on('solicitudes_ajuste_grupos');
            });
        }

        DB::table('solicitudes_ajuste_inventario')
            ->whereNull('grupo_ajuste_id')
            ->orderBy('solicitud_ajuste_id')
            ->chunkById(100, function ($requests): void {
                foreach ($requests as $request) {
                    $groupId = DB::table('solicitudes_ajuste_grupos')->insertGetId([
                        'estado' => $request->estado,
                        'usuario_solicita_rut' => $request->usuario_solicita_rut,
                        'solicitante_es_qf' => $request->solicitante_es_qf,
                        'usuario_autoriza_rut' => $request->usuario_autoriza_rut,
                        'motivo_resolucion' => $request->motivo_resolucion,
                        'fecha_solicitud' => $request->fecha_solicitud,
                        'fecha_resolucion' => $request->fecha_resolucion,
                    ]);

                    DB::table('solicitudes_ajuste_inventario')
                        ->where('solicitud_ajuste_id', $request->solicitud_ajuste_id)
                        ->update(['grupo_ajuste_id' => $groupId]);
                }
            }, 'solicitud_ajuste_id');
    }

    public function down(): void
    {
        if (Schema::hasForeignKey('solicitudes_ajuste_inventario', 'solicitudes_ajuste_grupo_fk')) {
            Schema::table('solicitudes_ajuste_inventario', function (Blueprint $table): void {
                $table->dropForeign('solicitudes_ajuste_grupo_fk');
            });
        }
        if (Schema::hasIndex('solicitudes_ajuste_inventario', 'solicitudes_ajuste_grupo_idx')) {
            Schema::table('solicitudes_ajuste_inventario', function (Blueprint $table): void {
                $table->dropIndex('solicitudes_ajuste_grupo_idx');
            });
        }
        if (Schema::hasColumn('solicitudes_ajuste_inventario', 'grupo_ajuste_id')) {
            Schema::table('solicitudes_ajuste_inventario', function (Blueprint $table): void {
                $table->dropColumn('grupo_ajuste_id');
            });
        }
        Schema::dropIfExists('solicitudes_ajuste_grupos');
    }
};
