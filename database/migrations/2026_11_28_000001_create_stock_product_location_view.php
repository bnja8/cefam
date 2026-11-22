<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasView('vw_stock_producto_ubicacion')) {
            return;
        }

        DB::statement(
            'CREATE VIEW vw_stock_producto_ubicacion AS
            SELECT
                p.producto_id AS producto_id,
                p.nombre AS producto,
                u.ubicacion_id AS ubicacion_id,
                u.nombre AS ubicacion,
                COALESCE(SUM(
                    CASE
                        WHEN l.estado = \'ACTIVO\' AND l.fecha_vencimiento >= CURRENT_DATE
                            THEN COALESCE(i.cantidad, 0)
                        ELSE 0
                    END
                ), 0) AS stock_disponible,
                sm.stock_minimo AS stock_minimo,
                CASE
                    WHEN COALESCE(SUM(
                        CASE
                            WHEN l.estado = \'ACTIVO\' AND l.fecha_vencimiento >= CURRENT_DATE
                                THEN COALESCE(i.cantidad, 0)
                            ELSE 0
                        END
                    ), 0) <= sm.stock_minimo
                        THEN 1
                    ELSE 0
                END AS es_stock_critico
            FROM stock_minimos_ubicacion sm
            INNER JOIN productos p ON p.producto_id = sm.producto_id
            INNER JOIN ubicaciones u ON u.ubicacion_id = sm.ubicacion_id
            LEFT JOIN lotes l ON l.producto_id = sm.producto_id
            LEFT JOIN inventario i
                ON i.lote_id = l.lote_id
                AND i.ubicacion_id = sm.ubicacion_id
            WHERE p.activo = 1
            GROUP BY
                p.producto_id,
                p.nombre,
                u.ubicacion_id,
                u.nombre,
                sm.stock_minimo'
        );
    }

    public function down(): void
    {
        if (Schema::hasView('vw_stock_producto_ubicacion')) {
            DB::statement('DROP VIEW `vw_stock_producto_ubicacion`');
        }
    }
};
