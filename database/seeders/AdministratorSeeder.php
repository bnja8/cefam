<?php
// Carga datos iniciales de administrator seeder.


namespace Database\Seeders;

use App\Support\People\Rut;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class AdministratorSeeder extends Seeder
{
    private const PREVIOUS_RUT = '111111111';

    private const ADMIN_RUT = '12345674';

    private const USER_RUT_REFERENCES = [
        'usuario_rol' => ['usuario_rut'],
        'solicitudes_reposicion' => ['usuario_rut'],
        'remesas' => ['usuario_rut'],
        'movimientos_inventario' => ['usuario_rut'],
        'traspasos_inventario' => ['usuario_rut'],
        'entregas' => ['usuario_rut'],
        'preparaciones_unidosis' => ['usuario_prepara_rut', 'usuario_confirma_rut'],
        'movimientos_controlados' => ['usuario_responsable_rut', 'usuario_autoriza_rut'],
        'auditoria' => ['usuario_rut'],
        'alertas' => ['atendida_por_rut'],
    ];

    // Ejecuta la operación de run.
    public function run(): void
    {
        $adminRut = Rut::limpiarRut(self::ADMIN_RUT);
        $previousRut = Rut::limpiarRut(self::PREVIOUS_RUT);
        $previousAccountExists = DB::table('usuarios')->where('rut', $previousRut)->exists();
        $adminAccountExists = DB::table('usuarios')->where('rut', $adminRut)->exists();

        if ($previousAccountExists && $adminAccountExists) {
            throw new \RuntimeException('Existen ambas cuentas administradoras; resuelve la duplicidad antes de ejecutar el seeder.');
        }

        $initialPassword = config('app.administrator_initial_password');
        if (! $previousAccountExists && ! $adminAccountExists
            && (! is_string($initialPassword) || mb_strlen($initialPassword, 'UTF-8') < 16)) {
            throw new \RuntimeException(
                'Define ADMINISTRATOR_INITIAL_PASSWORD con al menos 16 caracteres antes de crear la cuenta administradora.',
            );
        }

        DB::transaction(/* Agrupa los cambios de datos en una transacción. */ function () use (
            $adminRut,
            $previousRut,
            $previousAccountExists,
            $adminAccountExists,
            $initialPassword,
        ) {
            DB::table('roles')->insertOrIgnore([
                'nombre' => 'Administrador',
                'descripcion' => 'Gestión global del sistema',
            ]);

            if ($previousAccountExists) {
                $admin = (array) DB::table('usuarios')->where('rut', $previousRut)->first();
                $admin['rut'] = $adminRut;
                DB::table('usuarios')->insert($admin);

                foreach (self::USER_RUT_REFERENCES as $table => $columns) {
                    foreach ($columns as $column) {
                        DB::table($table)->where($column, $previousRut)->update([$column => $adminRut]);
                    }
                }

                DB::table('usuarios')->where('rut', $previousRut)->delete();
            } elseif (! $adminAccountExists) {
                DB::table('usuarios')->insert([
                    'rut' => $adminRut,
                    'nombre' => 'Administración',
                    'apellidoPaterno' => 'CESFAM',
                    'apellidoMaterno' => 'Farmacia',
                    'telefono' => null,
                    'password_hash' => Hash::make($initialPassword),
                    'activo' => true,
                ]);
            }

            $adminRoleId = DB::table('roles')->where('nombre', 'Administrador')->value('rol_id');
            DB::table('usuario_rol')->insertOrIgnore([
                'usuario_rut' => $adminRut,
                'rol_id' => $adminRoleId,
            ]);
        });
    }
}
