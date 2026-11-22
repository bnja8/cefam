<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('carros')) {
            Schema::create('carros', function (Blueprint $table): void {
                $table->bigInteger('carro_id')->autoIncrement();
                $table->bigInteger('ubicacion_id');
                $table->string('tipo_carro', 30);
                $table->string('nombre', 100);
                $table->unique(['ubicacion_id'], 'ubicacion_id');
                $table->unique(['nombre'], 'carros_nombre_unique');
                $table->foreign('ubicacion_id', 'fk_carros_ubicacion_id')->references('ubicacion_id')->on('ubicaciones');
            });
            if (DB::getDriverName() === 'mysql') {
                DB::statement('ALTER TABLE `carros` ADD CONSTRAINT `carros_chk_1` CHECK ((`tipo_carro` in (\'PARO\',\'UNIDOSIS\')))');
            }
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('farmacia_migration_ownership')
            || ! DB::table('farmacia_migration_ownership')->where('migration', '2026_09_08_000000_prepare_farmacia_schema')->exists()) {
            return;
        }

        Schema::dropIfExists('carros');
    }
};
