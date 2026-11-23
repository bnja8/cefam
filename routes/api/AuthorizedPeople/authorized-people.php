<?php

use App\Http\Controllers\AuthorizedPeople\AuthorizedPersonController;
use Illuminate\Support\Facades\Route;

Route::middleware(['web', 'auth'])->prefix('v1')->group(function () {
    Route::get('/authorized-people/options', [AuthorizedPersonController::class, 'index'])->defaults('screen', 'create')->name('api.v1.authorized-people.create');
    Route::get('/authorized-people', [AuthorizedPersonController::class, 'index'])->defaults('screen', 'list')->name('api.v1.authorized-people.index');
    Route::post('/authorized-people', [AuthorizedPersonController::class, 'store'])->name('api.v1.authorized-people.store');
    Route::get('/authorized-people/{authorizedPersonId}', [AuthorizedPersonController::class, 'edit'])->whereNumber('authorizedPersonId')->name('api.v1.authorized-people.edit');
    Route::put('/authorized-people/{authorizedPersonId}', [AuthorizedPersonController::class, 'update'])->whereNumber('authorizedPersonId')->name('api.v1.authorized-people.update');
    Route::patch('/authorized-people/{authorizedPersonId}/status', [AuthorizedPersonController::class, 'toggle'])->whereNumber('authorizedPersonId')->name('api.v1.authorized-people.toggle');
});
