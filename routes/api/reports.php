<?php

use App\Http\Controllers\Reports\ReportController;
use Illuminate\Support\Facades\Route;

Route::middleware(['web', 'auth'])->prefix('v1')->name('api.v1.')->group(function () {
    Route::get('/reports/deliveries', [ReportController::class, 'index'])->defaults('screen', 'deliveries')->name('reports.deliveries');
    Route::get('/reports/stock', [ReportController::class, 'index'])->defaults('screen', 'stock')->name('reports.stock');
    Route::get('/reports/movements', [ReportController::class, 'index'])->defaults('screen', 'movements')->name('reports.movements');
    Route::get('/reports/controlled', [ReportController::class, 'index'])->defaults('screen', 'controlled')->name('reports.controlled');
    Route::get('/reports/export', [ReportController::class, 'export'])->name('reports.export');
});
