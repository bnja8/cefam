<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('programas_salud')) {
            Schema::create('programas_salud', function (Blueprint $table): void {
                $table->bigInteger('programa_id')->autoIncrement();
                $table->string('nombre', 100);
                $table->string('descripcion', 255)->nullable();
                $table->boolean('activo')->default(true);
                $table->unique(['nombre'], 'programas_salud_nombre_unique');
            });
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('farmacia_migration_ownership')
            || ! DB::table('farmacia_migration_ownership')->where('migration', '2026_09_08_000000_prepare_farmacia_schema')->exists()) {
            return;
        }

        Schema::dropIfExists('programas_salud');
    }
};
