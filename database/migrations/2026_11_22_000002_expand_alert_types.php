<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private const ALERT_TYPES = [
        'STOCK_CRITICO',
        'VENCIMIENTO',
        'DESCUADRE_CONTROLADO',
        'SIN_EXISTENCIAS',
        'STOCK_BAJO',
        'VENCIDO',
    ];

    public function up(): void
    {
        $this->replaceTypeConstraint(self::ALERT_TYPES);
    }

    public function down(): void
    {
        $this->replaceTypeConstraint(['STOCK_CRITICO', 'VENCIMIENTO', 'DESCUADRE_CONTROLADO']);
    }

    private function replaceTypeConstraint(array $types): void
    {
        if (DB::getDriverName() !== 'mysql') {
            return;
        }

        $constraints = DB::select(
            "SELECT tc.CONSTRAINT_NAME
            FROM information_schema.TABLE_CONSTRAINTS tc
            INNER JOIN information_schema.CHECK_CONSTRAINTS cc
                ON cc.CONSTRAINT_SCHEMA = tc.CONSTRAINT_SCHEMA
                AND cc.CONSTRAINT_NAME = tc.CONSTRAINT_NAME
            WHERE tc.CONSTRAINT_SCHEMA = DATABASE()
                AND tc.TABLE_NAME = 'alertas'
                AND tc.CONSTRAINT_TYPE = 'CHECK'
                AND LOWER(cc.CHECK_CLAUSE) LIKE '%tipo_alerta%'"
        );

        foreach ($constraints as $constraint) {
            $name = $constraint->CONSTRAINT_NAME;
            if (! preg_match('/^[A-Za-z0-9_]+$/', $name)) {
                throw new LogicException('Nombre de restricción de alertas no válido.');
            }

            DB::statement('ALTER TABLE `alertas` DROP CHECK `'.$name.'`');
        }

        $allowedTypes = implode(', ', array_map(
            fn (string $type) => "'".$type."'",
            $types
        ));
        DB::statement(
            'ALTER TABLE `alertas` ADD CONSTRAINT `alertas_tipo_alerta_check` '
            .'CHECK (`tipo_alerta` IN ('.$allowedTypes.'))'
        );
    }
};
