<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('profesionales_salud', 'registro_profesional')) {
            Schema::table('profesionales_salud', function (Blueprint $table) {
                $table->dropColumn('registro_profesional');
            });
        }
    }

    public function down(): void
    {
        if (! Schema::hasColumn('profesionales_salud', 'registro_profesional')) {
            Schema::table('profesionales_salud', function (Blueprint $table) {
                $table->string('registro_profesional', 50)->nullable();
            });
        }
    }
};
