<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('productos', 'presentaciones_comerciales')) {
            Schema::table('productos', function (Blueprint $table): void {
                $table->json('presentaciones_comerciales')->nullable();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('productos', 'presentaciones_comerciales')) {
            Schema::table('productos', function (Blueprint $table): void {
                $table->dropColumn('presentaciones_comerciales');
            });
        }
    }
};
