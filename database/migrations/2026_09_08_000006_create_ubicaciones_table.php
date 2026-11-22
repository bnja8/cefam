<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('ubicaciones')) {
            Schema::create('ubicaciones', function (Blueprint $table): void {
                $table->bigInteger('ubicacion_id')->autoIncrement();
                $table->string('nombre', 100);
                $table->string('tipo', 30);
                $table->string('descripcion', 255)->nullable();
                $table->unique(['nombre'], 'ubicaciones_nombre_unique');
            });
            if (DB::getDriverName() === 'mysql') {
                DB::statement('ALTER TABLE `ubicaciones` ADD CONSTRAINT `ubicaciones_chk_1` CHECK ((`tipo` in (\'BODEGA\',\'VITRINA\',\'CARRO_PARO\',\'CARRO_UNIDOSIS\')))');
            }
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('farmacia_migration_ownership')
            || ! DB::table('farmacia_migration_ownership')->where('migration', '2026_09_08_000000_prepare_farmacia_schema')->exists()) {
            return;
        }

        Schema::dropIfExists('ubicaciones');
    }
};
