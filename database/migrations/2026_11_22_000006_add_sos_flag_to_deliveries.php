<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('entregas') && ! Schema::hasColumn('entregas', 'es_sos')) {
            Schema::table('entregas', function (Blueprint $table): void {
                $table->boolean('es_sos')->default(false);
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('entregas') && Schema::hasColumn('entregas', 'es_sos')) {
            Schema::table('entregas', function (Blueprint $table): void {
                $table->dropColumn('es_sos');
            });
        }
    }
};
