<?php

use App\Http\Controllers\Prescriptions\PrescriptionController;
use Illuminate\Support\Facades\Route;

Route::middleware(['web', 'auth'])->prefix('v1')->group(function () {
    Route::get('/prescriptions', [PrescriptionController::class, 'index'])
        ->defaults('screen', 'list')->name('api.v1.prescriptions.index');
    Route::get('/prescriptions/options', [PrescriptionController::class, 'index'])
        ->defaults('screen', 'create')->name('api.v1.prescriptions.options');
    Route::get('/prescriptions/products', [PrescriptionController::class, 'searchProducts'])
        ->middleware('throttle:60,1')->name('api.v1.prescriptions.products');
    Route::get('/prescriptions/{prescriptionId}/print', [PrescriptionController::class, 'print'])
        ->whereNumber('prescriptionId')->name('api.v1.prescriptions.print');
    Route::post('/prescriptions', [PrescriptionController::class, 'store'])
        ->name('api.v1.prescriptions.store');
});
