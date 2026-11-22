<?php

use App\Http\Controllers\Professionals\ProfessionalController;
use Illuminate\Support\Facades\Route;

Route::middleware(['web', 'auth'])->prefix('v1')->group(function () {
    Route::get('/professionals/options', [ProfessionalController::class, 'index'])->defaults('screen', 'create');
    Route::get('/professionals', [ProfessionalController::class, 'index'])->defaults('screen', 'list');
    Route::post('/professionals', [ProfessionalController::class, 'store']);
    Route::get('/professionals/{professionalRut}', [ProfessionalController::class, 'edit'])->where('professionalRut', '[0-9A-Za-z-]+');
    Route::put('/professionals/{professionalRut}', [ProfessionalController::class, 'update'])->where('professionalRut', '[0-9A-Za-z-]+');
    Route::patch('/professionals/{professionalRut}/status', [ProfessionalController::class, 'toggle'])->where('professionalRut', '[0-9A-Za-z-]+');
});
