<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('stock_minimos_ubicacion')) {
            Schema::create('stock_minimos_ubicacion', function (Blueprint $table): void {
                $table->bigInteger('stock_minimo_id')->autoIncrement();
                $table->bigInteger('producto_id');
                $table->bigInteger('ubicacion_id');
                $table->decimal('stock_minimo', 12, 2)->default('0.00');
                $table->unique(['producto_id', 'ubicacion_id'], 'stock_minimos_ubicacion_producto_id_unique');
                $table->index(['ubicacion_id'], 'fk_stock_minimo_ubicacion_id');
                $table->foreign('producto_id', 'fk_stock_minimo_producto_id')->references('producto_id')->on('productos');
                $table->foreign('ubicacion_id', 'fk_stock_minimo_ubicacion_id')->references('ubicacion_id')->on('ubicaciones');
            });
            if (DB::getDriverName() === 'mysql') {
                DB::statement('ALTER TABLE `stock_minimos_ubicacion` ADD CONSTRAINT `stock_minimos_ubicacion_chk_1` CHECK ((`stock_minimo` >= 0))');
            }
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('farmacia_migration_ownership')
            || ! DB::table('farmacia_migration_ownership')->where('migration', '2026_09_08_000000_prepare_farmacia_schema')->exists()) {
            return;
        }

        Schema::dropIfExists('stock_minimos_ubicacion');
    }
};
