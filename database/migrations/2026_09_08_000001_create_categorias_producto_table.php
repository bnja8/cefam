<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('categorias_producto')) {
            Schema::create('categorias_producto', function (Blueprint $table): void {
                $table->bigInteger('categoria_id')->autoIncrement();
                $table->string('nombre', 100);
                $table->string('descripcion', 255)->nullable();
                $table->unique(['nombre'], 'categorias_producto_nombre_unique');
            });
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('farmacia_migration_ownership')
            || ! DB::table('farmacia_migration_ownership')->where('migration', '2026_09_08_000000_prepare_farmacia_schema')->exists()) {
            return;
        }

        Schema::dropIfExists('categorias_producto');
    }
};
