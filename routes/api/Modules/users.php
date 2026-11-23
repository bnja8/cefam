<?php

use App\Http\Controllers\Modules\ModuleController;
use Illuminate\Support\Facades\Route;

Route::middleware(['web', 'auth'])->prefix('v1')->group(function () {
    Route::get('/users/options', [ModuleController::class, 'show'])
        ->defaults('module', 'administracion')
        ->defaults('screen', 'create-user')->name('api.v1.users.create');
    Route::get('/users', [ModuleController::class, 'show'])
        ->defaults('module', 'administracion')
        ->defaults('screen', 'list')->name('api.v1.users.index');
    Route::post('/users', [ModuleController::class, 'storeUser'])->name('api.v1.users.store');
    Route::get('/users/{userRut}', [ModuleController::class, 'editUser'])->where('userRut', '[0-9A-Za-z-]+')->name('api.v1.users.edit');
    Route::put('/users/{userRut}', [ModuleController::class, 'updateUser'])->where('userRut', '[0-9A-Za-z-]+')->name('api.v1.users.update');
    Route::patch('/users/{userRut}/status', [ModuleController::class, 'toggleUser'])->where('userRut', '[0-9A-Za-z-]+')->name('api.v1.users.toggle');
});
