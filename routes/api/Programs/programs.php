<?php

use App\Http\Controllers\Programs\HealthProgramController;
use Illuminate\Support\Facades\Route;

Route::middleware(['web', 'auth'])->prefix('v1')->group(function () {
    Route::get('/programs/affiliations/options', [HealthProgramController::class, 'index'])->defaults('screen', 'enroll')->name('api.v1.programs.enroll.create');
    Route::get('/programs/options', [HealthProgramController::class, 'index'])->defaults('screen', 'create')->name('api.v1.programs.create');
    Route::get('/programs/affiliations', [HealthProgramController::class, 'index'])->defaults('screen', 'affiliations')->name('api.v1.programs.affiliations.index');
    Route::get('/programs', [HealthProgramController::class, 'index'])->defaults('screen', 'programs')->name('api.v1.programs.index');
    Route::post('/programs/affiliations', [HealthProgramController::class, 'enroll'])->name('api.v1.programs.enroll');
    Route::post('/programs', [HealthProgramController::class, 'store'])->name('api.v1.programs.store');
    Route::get('/programs/{programId}', [HealthProgramController::class, 'show'])->whereNumber('programId')->name('api.v1.programs.edit');
    Route::put('/programs/{programId}', [HealthProgramController::class, 'update'])->whereNumber('programId')->name('api.v1.programs.update');
    Route::patch('/programs/{programId}/status', [HealthProgramController::class, 'toggle'])->whereNumber('programId')->name('api.v1.programs.toggle');
});
