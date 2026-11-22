<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('alertas', function (Blueprint $table) {
            if (! Schema::hasColumn('alertas', 'prioridad')) {
                $table->string('prioridad', 20)->nullable()->after('tipo_alerta');
            }
            if (! Schema::hasColumn('alertas', 'responsable_rut')) {
                $table->string('responsable_rut', 12)->nullable()->after('ubicacion_id');
            }
            if (! Schema::hasColumn('alertas', 'estado')) {
                $table->string('estado', 20)->nullable()->after('responsable_rut');
            }
            if (! Schema::hasColumn('alertas', 'clave_deduplicacion')) {
                $table->string('clave_deduplicacion', 180)->nullable()->after('estado');
            }
            if (! Schema::hasColumn('alertas', 'reconocida_por_rut')) {
                $table->string('reconocida_por_rut', 12)->nullable()->after('clave_deduplicacion');
            }
            if (! Schema::hasColumn('alertas', 'reconocida_en')) {
                $table->timestamp('reconocida_en')->nullable();
            }
            if (! Schema::hasColumn('alertas', 'detectada_en')) {
                $table->timestamp('detectada_en')->nullable();
            }
            if (! Schema::hasColumn('alertas', 'ultima_evaluacion_en')) {
                $table->timestamp('ultima_evaluacion_en')->nullable();
            }
        });

        if (! Schema::hasTable('historial_alertas')) {
            Schema::create('historial_alertas', function (Blueprint $table) {
                $table->bigIncrements('historial_id');
                $table->bigInteger('alerta_id');
                $table->string('usuario_rut', 12)->nullable();
                $table->string('tipo_evento', 30);
                $table->string('estado_anterior', 20)->nullable();
                $table->string('estado_nuevo', 20)->nullable();
                $table->string('responsable_anterior_rut', 12)->nullable();
                $table->string('responsable_nuevo_rut', 12)->nullable();
                $table->unsignedBigInteger('movimiento_id')->nullable();
                $table->text('descripcion')->nullable();
                $table->json('datos')->nullable();
                $table->timestamp('created_at')->useCurrent();

                $table->index(['alerta_id', 'created_at']);
                $table->foreign('alerta_id')->references('alerta_id')->on('alertas')->cascadeOnDelete();
                $table->foreign('usuario_rut')->references('rut')->on('usuarios');
                $table->foreign('responsable_anterior_rut')->references('rut')->on('usuarios');
                $table->foreign('responsable_nuevo_rut')->references('rut')->on('usuarios');
            });
        }

        \Illuminate\Support\Facades\DB::table('alertas')
            ->whereNull('estado')
            ->update([
                'estado' => \Illuminate\Support\Facades\DB::raw("CASE WHEN resuelta_at IS NULL THEN 'ACTIVA' ELSE 'RESUELTA' END"),
                'prioridad' => \Illuminate\Support\Facades\DB::raw("CASE WHEN tipo_alerta = 'STOCK_CRITICO' THEN 'ALTA' WHEN tipo_alerta = 'VENCIMIENTO' THEN 'MEDIA' ELSE 'ALTA' END"),
                'detectada_en' => \Illuminate\Support\Facades\DB::raw('fecha_generacion'),
                'ultima_evaluacion_en' => now(),
            ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('historial_alertas');
        Schema::table('alertas', function (Blueprint $table) {
            foreach ([
                'prioridad',
                'responsable_rut',
                'estado',
                'clave_deduplicacion',
                'reconocida_por_rut',
                'reconocida_en',
                'detectada_en',
                'ultima_evaluacion_en',
            ] as $column) {
                if (Schema::hasColumn('alertas', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
