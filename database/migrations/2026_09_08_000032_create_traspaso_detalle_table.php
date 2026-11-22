<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('traspaso_detalle')) {
            Schema::create('traspaso_detalle', function (Blueprint $table): void {
                $table->bigInteger('traspaso_detalle_id')->autoIncrement();
                $table->bigInteger('traspaso_id');
                $table->bigInteger('lote_id');
                $table->decimal('cantidad', 12, 2);
                $table->index(['traspaso_id'], 'fk_traspaso_detalle_traspaso_id');
                $table->index(['lote_id'], 'fk_traspaso_detalle_lote_id');
                $table->foreign('lote_id', 'fk_traspaso_detalle_lote_id')->references('lote_id')->on('lotes');
                $table->foreign('traspaso_id', 'fk_traspaso_detalle_traspaso_id')->references('traspaso_id')->on('traspasos_inventario');
            });
            if (DB::getDriverName() === 'mysql') {
                DB::statement('ALTER TABLE `traspaso_detalle` ADD CONSTRAINT `traspaso_detalle_chk_1` CHECK ((`cantidad` > 0))');
            }
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('farmacia_migration_ownership')
            || ! DB::table('farmacia_migration_ownership')->where('migration', '2026_09_08_000000_prepare_farmacia_schema')->exists()) {
            return;
        }

        Schema::dropIfExists('traspaso_detalle');
    }
};
