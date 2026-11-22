<?php

use App\Http\Controllers\Dispensations\DispensationController;
use Illuminate\Support\Facades\Route;

Route::middleware(['web', 'auth'])->prefix('v1')->group(function () {
    Route::get('/dispensations', [DispensationController::class, 'index'])
        ->defaults('screen', 'create')->name('api.v1.dispensations.options');
    Route::get('/dispensations/deliveries', [DispensationController::class, 'index'])
        ->defaults('screen', 'deliveries')->name('api.v1.dispensations.index');
    Route::get('/dispensations/patients/{patientRut}/options', [DispensationController::class, 'patientOptions'])
        ->where('patientRut', '[0-9A-Za-z-]+')->name('api.v1.dispensations.patient-options');
    Route::get('/dispensations/products', [DispensationController::class, 'searchProducts'])
        ->middleware('throttle:60,1')->name('api.v1.dispensations.products');
    Route::post('/dispensations', [DispensationController::class, 'store'])
        ->name('api.v1.dispensations.store');
});
