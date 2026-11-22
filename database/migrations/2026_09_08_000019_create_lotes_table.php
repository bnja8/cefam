<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('lotes')) {
            Schema::create('lotes', function (Blueprint $table): void {
                $table->bigInteger('lote_id')->autoIncrement();
                $table->bigInteger('producto_id');
                $table->string('numero_lote', 100);
                $table->date('fecha_vencimiento');
                $table->unique(['producto_id', 'numero_lote'], 'lotes_producto_id_unique');
                $table->index(['producto_id', 'fecha_vencimiento'], 'idx_lotes_producto_vencimiento');
                $table->foreign('producto_id', 'fk_lotes_producto_id')->references('producto_id')->on('productos');
            });
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('farmacia_migration_ownership')
            || ! DB::table('farmacia_migration_ownership')->where('migration', '2026_09_08_000000_prepare_farmacia_schema')->exists()) {
            return;
        }

        Schema::dropIfExists('lotes');
    }
};
