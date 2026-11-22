<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('profesionales_salud')) {
            Schema::create('profesionales_salud', function (Blueprint $table): void {
                $table->string('rut', 12);
                $table->string('nombre', 50)->nullable();
                $table->string('apellidoPaterno', 50);
                $table->string('apellidoMaterno', 50);
                $table->string('tipo_profesional', 50);
                $table->string('registro_profesional', 50)->nullable();
                $table->primary('rut');
            });
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('farmacia_migration_ownership')
            || ! DB::table('farmacia_migration_ownership')->where('migration', '2026_09_08_000000_prepare_farmacia_schema')->exists()) {
            return;
        }

        Schema::dropIfExists('profesionales_salud');
    }
};
