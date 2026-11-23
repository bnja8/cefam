<?php

use App\Http\Controllers\Patients\PatientController;
use Illuminate\Support\Facades\Route;

Route::middleware(['web', 'auth'])->prefix('v1')->group(function () {
    Route::get('/patients/search', [PatientController::class, 'search'])
        ->middleware('throttle:60,1')->name('api.v1.patients.search');
    Route::get('/patients/options', [PatientController::class, 'index'])->defaults('screen', 'create')->name('api.v1.patients.create');
    Route::get('/patients', [PatientController::class, 'index'])->defaults('screen', 'list')->name('api.v1.patients.index');
    Route::post('/patients', [PatientController::class, 'store'])->name('api.v1.patients.store');
    Route::get('/patients/{patientRut}', [PatientController::class, 'edit'])->where('patientRut', '[0-9A-Za-z-]+')->name('api.v1.patients.edit');
    Route::put('/patients/{patientRut}', [PatientController::class, 'update'])->where('patientRut', '[0-9A-Za-z-]+')->name('api.v1.patients.update');
});
