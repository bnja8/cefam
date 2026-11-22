<?php

use App\Http\Controllers\Notifications\NotificationController;
use Illuminate\Support\Facades\Route;

Route::middleware(['web', 'auth'])->prefix('v1')->name('api.v1.')->group(function () {
    Route::get('/notifications', [NotificationController::class, 'index'])->name('notifications.index');
    Route::post('/notifications/read-all', [NotificationController::class, 'markAllRead'])->name('notifications.read-all');
    Route::post('/notifications/{notificationId}/open', [NotificationController::class, 'openJson'])
        ->whereNumber('notificationId')
        ->name('notifications.open');
});
