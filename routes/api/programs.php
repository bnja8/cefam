<?php

use App\Http\Controllers\Programs\HealthProgramController;
use Illuminate\Support\Facades\Route;

Route::middleware(['web', 'auth'])->prefix('v1')->group(function () {
    Route::get('/programs/affiliations/options', [HealthProgramController::class, 'index'])->defaults('screen', 'enroll');
    Route::get('/programs/options', [HealthProgramController::class, 'index'])->defaults('screen', 'create');
    Route::get('/programs/affiliations', [HealthProgramController::class, 'index'])->defaults('screen', 'affiliations');
    Route::get('/programs', [HealthProgramController::class, 'index'])->defaults('screen', 'programs');
    Route::post('/programs/affiliations', [HealthProgramController::class, 'enroll']);
    Route::post('/programs', [HealthProgramController::class, 'store']);
    Route::get('/programs/{programId}', [HealthProgramController::class, 'show'])->whereNumber('programId');
    Route::put('/programs/{programId}', [HealthProgramController::class, 'update'])->whereNumber('programId');
    Route::patch('/programs/{programId}/status', [HealthProgramController::class, 'toggle'])->whereNumber('programId');
});
