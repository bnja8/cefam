<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('productos_codigo_barra')) {
            Schema::create('productos_codigo_barra', function (Blueprint $table): void {
                $table->bigInteger('codigo_barra_id')->autoIncrement();
                $table->bigInteger('producto_id');
                $table->string('codigo_barra', 100);
                $table->boolean('activo')->default(true);
                $table->unique(['codigo_barra'], 'codigo_barra');
                $table->index(['producto_id'], 'fk_productos_codigo_barra_producto_id');
                $table->foreign('producto_id', 'fk_productos_codigo_barra_producto_id')->references('producto_id')->on('productos');
            });
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('farmacia_migration_ownership')
            || ! DB::table('farmacia_migration_ownership')->where('migration', '2026_09_08_000000_prepare_farmacia_schema')->exists()) {
            return;
        }

        Schema::dropIfExists('productos_codigo_barra');
    }
};
