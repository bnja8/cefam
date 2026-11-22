<?php

use App\Http\Controllers\ControlledLedger\ControlledLedgerController;
use Illuminate\Support\Facades\Route;

Route::middleware(['web', 'auth'])->prefix('v1')->group(function () {
    Route::get('/controlled-ledger', [ControlledLedgerController::class, 'index'])
        ->defaults('screen', 'movements')->name('api.v1.controlled-ledger.index');
    Route::get('/controlled-ledger/options', [ControlledLedgerController::class, 'index'])
        ->defaults('screen', 'create')->name('api.v1.controlled-ledger.options');
    Route::get('/controlled-ledger/reconciliation-options', [ControlledLedgerController::class, 'index'])
        ->defaults('screen', 'reconcile')->name('api.v1.controlled-ledger.reconciliation-options');
    Route::post('/controlled-ledger', [ControlledLedgerController::class, 'store'])
        ->name('api.v1.controlled-ledger.store');
    Route::post('/controlled-ledger/reconcile', [ControlledLedgerController::class, 'reconcile'])
        ->name('api.v1.controlled-ledger.reconcile');
});
