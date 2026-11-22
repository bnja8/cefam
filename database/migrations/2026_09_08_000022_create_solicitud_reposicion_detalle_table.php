<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('solicitud_reposicion_detalle')) {
            Schema::create('solicitud_reposicion_detalle', function (Blueprint $table): void {
                $table->bigInteger('solicitud_detalle_id')->autoIncrement();
                $table->bigInteger('solicitud_id');
                $table->bigInteger('producto_id');
                $table->decimal('cantidad_solicitada', 12, 2);
                $table->unique(['solicitud_id', 'producto_id'], 'solicitud_id');
                $table->index(['producto_id'], 'fk_solicitud_reposicion_detalle_producto_id');
                $table->foreign('producto_id', 'fk_solicitud_reposicion_detalle_producto_id')->references('producto_id')->on('productos');
                $table->foreign('solicitud_id', 'fk_solicitud_reposicion_detalle_solicitud_id')->references('solicitud_id')->on('solicitudes_reposicion');
            });
            if (DB::getDriverName() === 'mysql') {
                DB::statement('ALTER TABLE `solicitud_reposicion_detalle` ADD CONSTRAINT `solicitud_reposicion_detalle_chk_1` CHECK ((`cantidad_solicitada` > 0))');
            }
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('farmacia_migration_ownership')
            || ! DB::table('farmacia_migration_ownership')->where('migration', '2026_09_08_000000_prepare_farmacia_schema')->exists()) {
            return;
        }

        Schema::dropIfExists('solicitud_reposicion_detalle');
    }
};
