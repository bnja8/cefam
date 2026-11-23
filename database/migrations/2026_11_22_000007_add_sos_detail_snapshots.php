<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('entrega_detalle')) {
            return;
        }

        Schema::table('entrega_detalle', function (Blueprint $table): void {
            if (! Schema::hasColumn('entrega_detalle', 'cantidad_indicada_snapshot')) {
                $table->decimal('cantidad_indicada_snapshot', 12, 2)->nullable();
            }
            if (! Schema::hasColumn('entrega_detalle', 'duracion_dias_snapshot')) {
                $table->unsignedSmallInteger('duracion_dias_snapshot')->nullable();
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('entrega_detalle')) {
            return;
        }

        Schema::table('entrega_detalle', function (Blueprint $table): void {
            $columns = [];
            if (Schema::hasColumn('entrega_detalle', 'cantidad_indicada_snapshot')) {
                $columns[] = 'cantidad_indicada_snapshot';
            }
            if (Schema::hasColumn('entrega_detalle', 'duracion_dias_snapshot')) {
                $columns[] = 'duracion_dias_snapshot';
            }
            if ($columns !== []) {
                $table->dropColumn($columns);
            }
        });
    }
};
