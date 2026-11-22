<?php

use App\Http\Controllers\Search\GlobalSearchController;
use App\Http\Controllers\Search\PersonLookupController;
use Illuminate\Support\Facades\Route;

Route::middleware(['web', 'auth'])->prefix('v1')->name('api.v1.')->group(function () {
    Route::get('/search', GlobalSearchController::class)->middleware('throttle:60,1')->name('search');
    Route::get('/people/lookup', PersonLookupController::class)->middleware('throttle:60,1')->name('people.lookup');
});
