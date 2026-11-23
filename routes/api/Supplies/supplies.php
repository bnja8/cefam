<?php

use App\Http\Controllers\Supplies\SupplyController;
use Illuminate\Support\Facades\Route;

Route::middleware(['web', 'auth'])->prefix('v1')->name('api.v1.')->group(function () {
    Route::get('/supplies/shipments', [SupplyController::class, 'index'])
        ->defaults('screen', 'shipments')->name('supplies.shipments.index');
    Route::get('/supplies/shipments/{shipmentId}', [SupplyController::class, 'shipmentDetail'])
        ->whereNumber('shipmentId')->name('supplies.shipments.show');
    Route::get('/supplies/requests', [SupplyController::class, 'index'])
        ->defaults('screen', 'requests')->name('supplies.requests.index');
    Route::get('/supplies/requests/{requestId}', [SupplyController::class, 'requestDetail'])
        ->whereNumber('requestId')->name('supplies.requests.show');
    Route::get('/supplies/options/request', [SupplyController::class, 'index'])
        ->defaults('screen', 'request-create')->name('supplies.requests.options');
    Route::get('/supplies/options/receive', [SupplyController::class, 'index'])
        ->defaults('screen', 'receive')->name('supplies.receive.options');
    Route::get('/supplies/products/search', [SupplyController::class, 'searchRequestProducts'])
        ->middleware('throttle:60,1')->name('supplies.products.search');
    Route::post('/supplies/requests', [SupplyController::class, 'storeRequest'])->name('supplies.requests.store');
    Route::post('/supplies/shipments', [SupplyController::class, 'receive'])->name('supplies.shipments.store');
});
