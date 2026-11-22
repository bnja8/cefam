<?php

use App\Http\Controllers\DeliveryOrders\DeliveryOrderController;
use Illuminate\Support\Facades\Route;

Route::middleware(['web', 'auth'])->prefix('v1')->group(function () {
    Route::get('/delivery-orders/{orderId}', [DeliveryOrderController::class, 'show'])
        ->whereNumber('orderId')->name('api.v1.delivery-orders.show');
    Route::get('/delivery-orders/{orderId}/labels', [DeliveryOrderController::class, 'labels'])
        ->whereNumber('orderId')->name('api.v1.delivery-orders.labels');
    Route::get('/delivery-orders/{orderId}/details/{detailId}/label', [DeliveryOrderController::class, 'label'])
        ->whereNumber('orderId')->whereNumber('detailId')->name('api.v1.delivery-orders.label');
});
