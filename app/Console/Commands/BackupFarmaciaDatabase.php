<?php


namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Symfony\Component\Process\Exception\ProcessStartFailedException;
use Symfony\Component\Process\Process;

class BackupFarmaciaDatabase extends Command
{
    protected $signature = 'farmacia:backup';

    protected $description = 'Genera y conserva un respaldo privado de la base MySQL de farmacia';

    public function handle(): int
    {
        if (config('database.default') !== 'mysql') {
            $this->error('El respaldo requiere que DB_CONNECTION=mysql.');

            return self::FAILURE;
        }

        $connection = config('database.connections.mysql');
        $server = app('db')->connection('mysql')->selectOne('select version() as version, @@version_comment as product');
        if (str_contains(strtolower($server->product.' '.$server->version), 'mariadb')) {
            $this->error('El servidor configurado no es MySQL; no se generó el respaldo.');

            return self::FAILURE;
        }

        $directory = storage_path('app/private/backups');
        File::ensureDirectoryExists($directory, 0700, true);

        $suffix = now()->format('Ymd_His_u');
        $backupPath = $directory.DIRECTORY_SEPARATOR."farmacia-{$suffix}.sql";
        $temporaryPath = $backupPath.'.partial';
        $credentialsPath = $directory.DIRECTORY_SEPARATOR.'.mysql-credentials-'.Str::uuid().'.cnf';
        $credentials = $this->credentialsFile($connection);

        if (file_put_contents($credentialsPath, $credentials, LOCK_EX) === false) {
            $this->error('No se pudo crear el archivo temporal privado de conexión MySQL.');

            return self::FAILURE;
        }
        if (DIRECTORY_SEPARATOR !== '\\' && ! chmod($credentialsPath, 0600)) {
            File::delete($credentialsPath);
            $this->error('No se pudieron restringir los permisos del archivo temporal de credenciales.');

            return self::FAILURE;
        }

        $process = new Process([
            config('backup.mysql_dump_binary'),
            '--defaults-extra-file='.$credentialsPath,
            '--single-transaction',
            '--routines',
            '--triggers',
            '--hex-blob',
            '--default-character-set=utf8mb4',
            '--result-file='.$temporaryPath,
            $connection['database'],
        ]);
        $process->setTimeout(1200);

        try {
            $process->run();
        } catch (ProcessStartFailedException $exception) {
            File::delete([$credentialsPath, $temporaryPath]);
            $this->error('No se pudo iniciar mysqldump. Instala MySQL Client o define MYSQLDUMP_BINARY en .env.');

            return self::FAILURE;
        } finally {
            File::delete($credentialsPath);
        }

        if (! $process->isSuccessful()) {
            File::delete($temporaryPath);
            $this->error('El respaldo MySQL falló: '.$process->getErrorOutput());

            return self::FAILURE;
        }
        if (! is_file($temporaryPath) || filesize($temporaryPath) === 0) {
            File::delete($temporaryPath);
            $this->error('mysqldump finalizó sin generar un archivo de respaldo válido.');

            return self::FAILURE;
        }
        if (! rename($temporaryPath, $backupPath)) {
            File::delete($temporaryPath);
            $this->error('No se pudo publicar el archivo de respaldo terminado.');

            return self::FAILURE;
        }

        $this->removeExpiredBackups($directory);
        $this->info('Respaldo MySQL generado en almacenamiento privado: '.$backupPath);

        return self::SUCCESS;
    }

    // Resuelve la ruta del archivo de credenciales.
    private function credentialsFile(array $connection): string
    {
        foreach (['host', 'username', 'password', 'database'] as $key) {
            $value = (string) ($connection[$key] ?? '');
            if (str_contains($value, "\n") || str_contains($value, "\r")) {
                throw new \InvalidArgumentException("La configuración MySQL {$key} contiene caracteres no permitidos.");
            }
        }

        $options = [
            'host' => $connection['host'],
            'port' => $connection['port'],
            'user' => $connection['username'],
            'password' => $connection['password'],
            'default-character-set' => 'utf8mb4',
        ];
        if (! empty($connection['unix_socket'])) {
            $options['socket'] = $connection['unix_socket'];
        }

        $lines = ['[client]'];
        foreach ($options as $key => $value) {
            $escaped = str_replace(['\\', '"'], ['\\\\', '\\"'], (string) $value);
            $lines[] = $key.'="'.$escaped.'"';
        }

        return implode(PHP_EOL, $lines).PHP_EOL;
    }

    // Elimina los respaldos que ya vencieron.
    private function removeExpiredBackups(string $directory): void
    {
        $expiry = now()->subDays((int) config('backup.retention_days'))->timestamp;
        foreach (File::files($directory) as $file) {
            if (preg_match('/^farmacia-[0-9]{8}_[0-9]{6}_[0-9]{6}\.sql$/', $file->getFilename())
                && $file->getMTime() < $expiry) {
                File::delete($file->getPathname());
            }
        }
    }
}
