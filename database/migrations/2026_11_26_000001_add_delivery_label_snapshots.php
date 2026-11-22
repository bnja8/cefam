<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('entregas', function (Blueprint $table) {
            if (! Schema::hasColumn('entregas', 'paciente_nombre_snapshot')) {
                $table->string('paciente_nombre_snapshot', 160)->nullable();
            }
            if (! Schema::hasColumn('entregas', 'persona_autorizada_nombre_snapshot')) {
                $table->string('persona_autorizada_nombre_snapshot', 160)->nullable();
            }
            if (! Schema::hasColumn('entregas', 'persona_autorizada_rut_snapshot')) {
                $table->string('persona_autorizada_rut_snapshot', 12)->nullable();
            }
        });
        Schema::table('entrega_detalle', function (Blueprint $table) {
            if (! Schema::hasColumn('entrega_detalle', 'producto_nombre_snapshot')) {
                $table->string('producto_nombre_snapshot', 150)->nullable();
            }
            if (! Schema::hasColumn('entrega_detalle', 'unidad_base_codigo_snapshot')) {
                $table->string('unidad_base_codigo_snapshot', 30)->nullable();
            }
            if (! Schema::hasColumn('entrega_detalle', 'numero_lote_snapshot')) {
                $table->string('numero_lote_snapshot', 100)->nullable();
            }
            if (! Schema::hasColumn('entrega_detalle', 'fecha_vencimiento_lote_snapshot')) {
                $table->date('fecha_vencimiento_lote_snapshot')->nullable();
            }
        });

        DB::table('entregas')
            ->join('pacientes', 'pacientes.rut', '=', 'entregas.paciente_rut')
            ->leftJoin('personas_autorizadas', 'personas_autorizadas.persona_autorizada_id', '=', 'entregas.persona_autorizada_id')
            ->orderBy('entregas.entrega_id')
            ->select([
                'entregas.entrega_id',
                'pacientes.nombre as paciente_nombre',
                'pacientes.apellidoPaterno as paciente_apellido_paterno',
                'pacientes.apellidoMaterno as paciente_apellido_materno',
                'personas_autorizadas.rut as persona_autorizada_rut',
                'personas_autorizadas.nombre as persona_autorizada_nombre',
                'personas_autorizadas.apellidoPaterno as persona_autorizada_apellido_paterno',
                'personas_autorizadas.apellidoMaterno as persona_autorizada_apellido_materno',
            ])
            ->chunk(500, function ($deliveries) {
                foreach ($deliveries as $delivery) {
                    $snapshots = [
                        'paciente_nombre_snapshot' => $this->fullName(
                            $delivery->paciente_nombre,
                            $delivery->paciente_apellido_paterno,
                            $delivery->paciente_apellido_materno
                        ),
                        'persona_autorizada_nombre_snapshot' => $delivery->persona_autorizada_rut === null
                            ? null
                            : $this->fullName(
                                $delivery->persona_autorizada_nombre,
                                $delivery->persona_autorizada_apellido_paterno,
                                $delivery->persona_autorizada_apellido_materno
                            ),
                        'persona_autorizada_rut_snapshot' => $delivery->persona_autorizada_rut,
                    ];

                    foreach ($snapshots as $column => $value) {
                        if ($value !== null) {
                            DB::table('entregas')
                                ->where('entrega_id', $delivery->entrega_id)
                                ->whereNull($column)
                                ->update([$column => $value]);
                        }
                    }
                }
            });

        DB::table('entrega_detalle')
            ->join('productos', 'productos.producto_id', '=', 'entrega_detalle.producto_id')
            ->join('lotes', 'lotes.lote_id', '=', 'entrega_detalle.lote_id')
            ->leftJoin('unidades_medida', 'unidades_medida.unidad_medida_id', '=', 'productos.unidad_base_inventario_id')
            ->orderBy('entrega_detalle.entrega_detalle_id')
            ->select([
                'entrega_detalle.entrega_detalle_id',
                'productos.nombre as producto_nombre',
                'unidades_medida.codigo as unidad_base_codigo',
                'lotes.numero_lote',
                'lotes.fecha_vencimiento',
            ])
            ->chunk(500, function ($details) {
                foreach ($details as $detail) {
                    $snapshots = [
                        'producto_nombre_snapshot' => $detail->producto_nombre,
                        'unidad_base_codigo_snapshot' => $detail->unidad_base_codigo,
                        'numero_lote_snapshot' => $detail->numero_lote,
                        'fecha_vencimiento_lote_snapshot' => $detail->fecha_vencimiento,
                    ];

                    foreach ($snapshots as $column => $value) {
                        if ($value !== null) {
                            DB::table('entrega_detalle')
                                ->where('entrega_detalle_id', $detail->entrega_detalle_id)
                                ->whereNull($column)
                                ->update([$column => $value]);
                        }
                    }
                }
            });
    }

    public function down(): void
    {
        Schema::table('entrega_detalle', function (Blueprint $table) {
            $table->dropColumn([
                'producto_nombre_snapshot',
                'unidad_base_codigo_snapshot',
                'numero_lote_snapshot',
                'fecha_vencimiento_lote_snapshot',
            ]);
        });
        Schema::table('entregas', function (Blueprint $table) {
            $table->dropColumn([
                'paciente_nombre_snapshot',
                'persona_autorizada_nombre_snapshot',
                'persona_autorizada_rut_snapshot',
            ]);
        });
    }

    private function fullName(?string $firstName, ?string $paternalSurname, ?string $maternalSurname): string
    {
        return implode(' ', array_filter(array_map(
            static fn (?string $part): string => trim((string) $part),
            [$firstName, $paternalSurname, $maternalSurname]
        ), static fn (string $part): bool => $part !== ''));
    }
};
