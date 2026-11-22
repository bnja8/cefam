<?php
// Registra las rutas de console.


use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;
use App\Services\Patients\PatientRecordArchiver;

Artisan::command('inspire', /* Define la tarea que ejecuta el comando. */ function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('farmacia:backup')->dailyAt('02:00')->withoutOverlapping();

Artisan::command('farmacia:archivar-registros-paciente', function (PatientRecordArchiver $archiver) {
    $archiver->archiveExpired();
    $this->info('Registros de pacientes con más de 30 días archivados.');
})->purpose('Archiva recetas y dispensaciones antiguas en las fichas de pacientes');

Schedule::command('farmacia:archivar-registros-paciente')->dailyAt('00:10')->withoutOverlapping();
