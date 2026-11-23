<?php

use App\Http\Controllers\Professionals\ProfessionalController;
use Illuminate\Support\Facades\Route;

Route::middleware(['web', 'auth'])->prefix('v1')->group(function () {
    Route::get('/professionals/options', [ProfessionalController::class, 'index'])->defaults('screen', 'create')->name('api.v1.professionals.create');
    Route::get('/professionals', [ProfessionalController::class, 'index'])->defaults('screen', 'list')->name('api.v1.professionals.index');
    Route::post('/professionals', [ProfessionalController::class, 'store'])->name('api.v1.professionals.store');
    Route::get('/professionals/{professionalRut}', [ProfessionalController::class, 'edit'])->where('professionalRut', '[0-9A-Za-z-]+')->name('api.v1.professionals.edit');
    Route::put('/professionals/{professionalRut}', [ProfessionalController::class, 'update'])->where('professionalRut', '[0-9A-Za-z-]+')->name('api.v1.professionals.update');
    Route::patch('/professionals/{professionalRut}/status', [ProfessionalController::class, 'toggle'])->where('professionalRut', '[0-9A-Za-z-]+')->name('api.v1.professionals.toggle');
});
