<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('inventario')) {
            Schema::create('inventario', function (Blueprint $table): void {
                $table->bigInteger('inventario_id')->autoIncrement();
                $table->bigInteger('lote_id');
                $table->bigInteger('ubicacion_id');
                $table->decimal('cantidad', 12, 2)->default('0.00');
                $table->decimal('cantidad_recepcion', 12, 2)->default('0.00');
                $table->unique(['lote_id', 'ubicacion_id'], 'lote_id');
                $table->index(['lote_id'], 'idx_inventario_lote');
                $table->index(['ubicacion_id'], 'idx_inventario_ubicacion');
                $table->foreign('lote_id', 'fk_inventario_lote_id')->references('lote_id')->on('lotes');
                $table->foreign('ubicacion_id', 'fk_inventario_ubicacion_id')->references('ubicacion_id')->on('ubicaciones');
            });
            if (DB::getDriverName() === 'mysql') {
                DB::statement('ALTER TABLE `inventario` ADD CONSTRAINT `inventario_chk_1` CHECK ((`cantidad` >= 0))');
            }
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('farmacia_migration_ownership')
            || ! DB::table('farmacia_migration_ownership')->where('migration', '2026_09_08_000000_prepare_farmacia_schema')->exists()) {
            return;
        }

        Schema::dropIfExists('inventario');
    }
};
