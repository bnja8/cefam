<?php

use App\Http\Controllers\Carts\CartController;
use Illuminate\Support\Facades\Route;

Route::middleware(['web', 'auth'])->prefix('v1')->group(function () {
    Route::get('/carts/stock', [CartController::class, 'index'])
        ->defaults('screen', 'stock')->name('api.v1.carts.stock');
    Route::get('/carts/usage', [CartController::class, 'index'])
        ->defaults('screen', 'usage')->name('api.v1.carts.usage');
    Route::get('/carts/paro/options', [CartController::class, 'index'])
        ->defaults('screen', 'paro-create')->name('api.v1.carts.paro.options');
    Route::get('/carts/treatments/options', [CartController::class, 'index'])
        ->defaults('screen', 'treatment-create')->name('api.v1.carts.treatments.options');
    Route::get('/carts/unidosis/options', [CartController::class, 'index'])
        ->defaults('screen', 'unidosis-create')->name('api.v1.carts.unidosis.options');
    Route::get('/carts/preparations', [CartController::class, 'index'])
        ->defaults('screen', 'preparations')->name('api.v1.carts.preparations');
    Route::get('/carts/unidosis/products', [CartController::class, 'searchUnidosisProducts'])
        ->middleware('throttle:60,1')->name('api.v1.carts.unidosis.products');
    Route::post('/carts/treatments', [CartController::class, 'storeTreatment'])
        ->name('api.v1.carts.treatments.store');
    Route::post('/carts/paro/dispense', [CartController::class, 'dispenseFromParo'])
        ->name('api.v1.carts.paro.dispense');
    Route::post('/carts/unidosis', [CartController::class, 'prepare'])
        ->name('api.v1.carts.unidosis.prepare');
    Route::patch('/carts/unidosis/{preparationId}/confirm', [CartController::class, 'confirm'])
        ->whereNumber('preparationId')->name('api.v1.carts.unidosis.confirm');
});
