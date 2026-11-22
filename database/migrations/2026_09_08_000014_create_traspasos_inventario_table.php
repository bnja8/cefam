<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('traspasos_inventario')) {
            Schema::create('traspasos_inventario', function (Blueprint $table): void {
                $table->bigInteger('traspaso_id')->autoIncrement();
                $table->timestamp('fecha_hora')->useCurrent();
                $table->bigInteger('origen_id');
                $table->bigInteger('destino_id');
                $table->string('usuario_rut', 12);
                $table->text('observacion')->nullable();
                $table->index(['origen_id'], 'fk_traspasos_inventario_origen_id');
                $table->index(['destino_id'], 'fk_traspasos_inventario_destino_id');
                $table->index(['usuario_rut'], 'fk_traspasos_inventario_usuario_rut');
                $table->foreign('destino_id', 'fk_traspasos_inventario_destino_id')->references('ubicacion_id')->on('ubicaciones');
                $table->foreign('origen_id', 'fk_traspasos_inventario_origen_id')->references('ubicacion_id')->on('ubicaciones');
                $table->foreign('usuario_rut', 'fk_traspasos_inventario_usuario_rut')->references('rut')->on('usuarios');
            });
            if (DB::getDriverName() === 'mysql') {
                DB::statement('ALTER TABLE `traspasos_inventario` ADD CONSTRAINT `traspasos_inventario_chk_1` CHECK ((`origen_id` <> `destino_id`))');
            }
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('farmacia_migration_ownership')
            || ! DB::table('farmacia_migration_ownership')->where('migration', '2026_09_08_000000_prepare_farmacia_schema')->exists()) {
            return;
        }

        Schema::dropIfExists('traspasos_inventario');
    }
};
