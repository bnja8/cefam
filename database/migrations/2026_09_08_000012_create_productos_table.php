<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('productos')) {
            Schema::create('productos', function (Blueprint $table): void {
                $table->bigInteger('producto_id')->autoIncrement();
                $table->string('numero_serie', 50);
                $table->string('nombre', 150);
                $table->text('descripcion')->nullable();
                $table->bigInteger('categoria_id');
                $table->boolean('es_controlado')->default(false);
                $table->boolean('activo')->default(true);
                $table->unique(['numero_serie'], 'numero_serie');
                $table->index(['categoria_id'], 'idx_productos_categoria');
                $table->foreign('categoria_id', 'fk_productos_categoria_id')->references('categoria_id')->on('categorias_producto');
            });
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('farmacia_migration_ownership')
            || ! DB::table('farmacia_migration_ownership')->where('migration', '2026_09_08_000000_prepare_farmacia_schema')->exists()) {
            return;
        }

        Schema::dropIfExists('productos');
    }
};
