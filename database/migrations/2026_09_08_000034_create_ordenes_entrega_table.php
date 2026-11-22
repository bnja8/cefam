<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('ordenes_entrega')) {
            Schema::create('ordenes_entrega', function (Blueprint $table): void {
                $table->bigInteger('orden_entrega_id')->autoIncrement();
                $table->bigInteger('entrega_id');
                $table->string('numero_orden', 100);
                $table->timestamp('fecha_generacion')->useCurrent();
                $table->string('plantilla', 100);
                $table->unique(['entrega_id'], 'entrega_id');
                $table->unique(['numero_orden'], 'numero_orden');
                $table->foreign('entrega_id', 'fk_ordenes_entrega_entrega_id')->references('entrega_id')->on('entregas');
            });
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('farmacia_migration_ownership')
            || ! DB::table('farmacia_migration_ownership')->where('migration', '2026_09_08_000000_prepare_farmacia_schema')->exists()) {
            return;
        }

        Schema::dropIfExists('ordenes_entrega');
    }
};
