<?php

use App\Http\Controllers\Auth\AuthController;
use Illuminate\Support\Facades\Route;

Route::middleware('web')->prefix('v1')->group(function () {
    Route::get('/csrf-cookie', static fn () => response()->noContent())
        ->name('api.v1.csrf-cookie');

    Route::post('/login', [AuthController::class, 'store'])
        ->middleware('throttle:login')
        ->name('api.v1.login');

    Route::middleware('auth')->group(function () {
        Route::get('/user', [AuthController::class, 'user'])->name('api.user');
        Route::post('/logout', [AuthController::class, 'destroy'])->name('api.logout');
    });
});
