<?php

use App\Http\Controllers\Patients\PatientController;
use Illuminate\Support\Facades\Route;

Route::middleware(['web', 'auth'])->prefix('v1')->group(function () {
    Route::get('/patients/search', [PatientController::class, 'search'])
        ->middleware('throttle:60,1')->name('api.v1.patients.search');
    Route::get('/patients/options', [PatientController::class, 'index'])->defaults('screen', 'create');
    Route::get('/patients', [PatientController::class, 'index'])->defaults('screen', 'list');
    Route::post('/patients', [PatientController::class, 'store']);
    Route::get('/patients/{patientRut}', [PatientController::class, 'edit'])->where('patientRut', '[0-9A-Za-z-]+');
    Route::put('/patients/{patientRut}', [PatientController::class, 'update'])->where('patientRut', '[0-9A-Za-z-]+');
});
