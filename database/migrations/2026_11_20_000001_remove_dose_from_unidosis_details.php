<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('preparacion_unidosis_detalle', 'dosis')) {
            Schema::table('preparacion_unidosis_detalle', function (Blueprint $table) {
                $table->dropColumn('dosis');
            });
        }
    }

    public function down(): void
    {
        if (! Schema::hasColumn('preparacion_unidosis_detalle', 'dosis')) {
            Schema::table('preparacion_unidosis_detalle', function (Blueprint $table) {
                $table->string('dosis', 100)->nullable();
            });
        }
    }
};
