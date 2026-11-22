<?php

use App\Http\Controllers\Modules\ModuleController;
use Illuminate\Support\Facades\Route;

Route::middleware(['web', 'auth'])->prefix('v1')->group(function () {
    Route::get('/users/options', [ModuleController::class, 'show'])
        ->defaults('module', 'administracion')
        ->defaults('screen', 'create-user');
    Route::get('/users', [ModuleController::class, 'show'])
        ->defaults('module', 'administracion')
        ->defaults('screen', 'list');
    Route::post('/users', [ModuleController::class, 'storeUser']);
    Route::get('/users/{userRut}', [ModuleController::class, 'editUser'])->where('userRut', '[0-9A-Za-z-]+');
    Route::put('/users/{userRut}', [ModuleController::class, 'updateUser'])->where('userRut', '[0-9A-Za-z-]+');
    Route::patch('/users/{userRut}/status', [ModuleController::class, 'toggleUser'])->where('userRut', '[0-9A-Za-z-]+');
});
