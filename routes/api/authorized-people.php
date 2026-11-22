<?php

use App\Http\Controllers\AuthorizedPeople\AuthorizedPersonController;
use Illuminate\Support\Facades\Route;

Route::middleware(['web', 'auth'])->prefix('v1')->group(function () {
    Route::get('/authorized-people/options', [AuthorizedPersonController::class, 'index'])->defaults('screen', 'create');
    Route::get('/authorized-people', [AuthorizedPersonController::class, 'index'])->defaults('screen', 'list');
    Route::post('/authorized-people', [AuthorizedPersonController::class, 'store']);
    Route::get('/authorized-people/{authorizedPersonId}', [AuthorizedPersonController::class, 'edit'])->whereNumber('authorizedPersonId');
    Route::put('/authorized-people/{authorizedPersonId}', [AuthorizedPersonController::class, 'update'])->whereNumber('authorizedPersonId');
    Route::patch('/authorized-people/{authorizedPersonId}/status', [AuthorizedPersonController::class, 'toggle'])->whereNumber('authorizedPersonId');
});
