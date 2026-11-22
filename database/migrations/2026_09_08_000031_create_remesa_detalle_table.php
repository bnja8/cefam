<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('remesa_detalle')) {
            Schema::create('remesa_detalle', function (Blueprint $table): void {
                $table->bigInteger('remesa_detalle_id')->autoIncrement();
                $table->bigInteger('remesa_id');
                $table->bigInteger('producto_id');
                $table->string('numero_lote', 100);
                $table->date('fecha_vencimiento');
                $table->decimal('cantidad', 12, 2);
                $table->unique(['remesa_id', 'producto_id', 'numero_lote'], 'remesa_id');
                $table->index(['producto_id'], 'fk_remesa_detalle_producto_id');
                $table->foreign('producto_id', 'fk_remesa_detalle_producto_id')->references('producto_id')->on('productos');
                $table->foreign('remesa_id', 'fk_remesa_detalle_remesa_id')->references('remesa_id')->on('remesas');
            });
            if (DB::getDriverName() === 'mysql') {
                DB::statement('ALTER TABLE `remesa_detalle` ADD CONSTRAINT `remesa_detalle_chk_1` CHECK ((`cantidad` > 0))');
            }
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('farmacia_migration_ownership')
            || ! DB::table('farmacia_migration_ownership')->where('migration', '2026_09_08_000000_prepare_farmacia_schema')->exists()) {
            return;
        }

        Schema::dropIfExists('remesa_detalle');
    }
};
