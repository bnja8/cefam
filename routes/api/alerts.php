<?php

use App\Http\Controllers\Alerts\AlertController;
use Illuminate\Support\Facades\Route;

Route::middleware(['web', 'auth'])->prefix('v1')->name('api.v1.')->group(function () {
    Route::get('/alerts/map', [AlertController::class, 'index'])->name('alerts.map');
    Route::get('/alerts/history', [AlertController::class, 'history'])->name('alerts.history');
    Route::get('/alerts/{alertId}', [AlertController::class, 'show'])->whereNumber('alertId')->name('alerts.show');
    Route::post('/alerts/{alertId}/replenish', [AlertController::class, 'replenish'])->whereNumber('alertId')->name('alerts.replenish');
    Route::get('/alerts/{alertId}/expiry-withdrawal', [AlertController::class, 'requestExpiryWithdrawal'])->whereNumber('alertId')->name('alerts.expiry-withdrawal');
    Route::patch('/alerts/{alertId}/assign', [AlertController::class, 'assign'])->whereNumber('alertId')->name('alerts.assign');
    Route::patch('/alerts/{alertId}/acknowledge', [AlertController::class, 'acknowledge'])->whereNumber('alertId')->name('alerts.acknowledge');
    Route::post('/alerts/{alertId}/follow', [AlertController::class, 'follow'])->whereNumber('alertId')->name('alerts.follow');
});
