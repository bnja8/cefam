<?php

use App\Http\Controllers\Inventory\CategoryController;
use App\Http\Controllers\Inventory\InventoryController;
use App\Http\Controllers\Inventory\InventoryOperationController;
use App\Http\Controllers\Inventory\LocationController;
use App\Http\Controllers\Inventory\ProductController;
use Illuminate\Support\Facades\Route;

Route::middleware(['web', 'auth'])->prefix('v1')->name('api.v1.')->group(function () {
    Route::get('/inventory', [InventoryController::class, 'apiIndex'])->name('inventory.index');
    Route::get('/inventory/options', [InventoryOperationController::class, 'apiOptions'])->name('inventory.options');
    Route::get('/inventory/adjustments', [InventoryOperationController::class, 'adjustmentRequests'])->name('inventory.adjustments.index');

    Route::get('/inventory/catalog', [ProductController::class, 'index'])->name('inventory.catalog.index');
    Route::get('/inventory/catalog/options', [ProductController::class, 'create'])->name('inventory.catalog.options');
    Route::post('/inventory/catalog', [ProductController::class, 'store'])->name('inventory.catalog.store');
    Route::get('/inventory/catalog/{productId}', [ProductController::class, 'edit'])
        ->whereNumber('productId')->name('inventory.catalog.show');
    Route::put('/inventory/catalog/{productId}', [ProductController::class, 'update'])
        ->whereNumber('productId')->name('inventory.catalog.update');

    Route::get('/inventory/categories', [CategoryController::class, 'index'])->name('inventory.categories.index');
    Route::get('/inventory/categories/options', [CategoryController::class, 'create'])->name('inventory.categories.options');
    Route::post('/inventory/categories', [CategoryController::class, 'store'])->name('inventory.categories.store');
    Route::get('/inventory/categories/{categoryId}', [CategoryController::class, 'edit'])
        ->whereNumber('categoryId')->name('inventory.categories.show');
    Route::put('/inventory/categories/{categoryId}', [CategoryController::class, 'update'])
        ->whereNumber('categoryId')->name('inventory.categories.update');

    Route::get('/inventory/locations', [LocationController::class, 'index'])->name('inventory.locations.index');
    Route::get('/inventory/locations/options', [LocationController::class, 'create'])->name('inventory.locations.options');
    Route::post('/inventory/locations', [LocationController::class, 'store'])->name('inventory.locations.store');
    Route::get('/inventory/locations/{locationId}', [LocationController::class, 'edit'])
        ->whereNumber('locationId')->name('inventory.locations.show');
    Route::put('/inventory/locations/{locationId}', [LocationController::class, 'update'])
        ->whereNumber('locationId')->name('inventory.locations.update');

    Route::get('/inventory/{productId}', [InventoryController::class, 'apiProductDetail'])
        ->whereNumber('productId')->name('inventory.show');
    Route::get('/inventory/{productId}/lots', [InventoryController::class, 'apiProductLots'])
        ->whereNumber('productId')->name('inventory.lots');
    Route::get('/inventory/{productId}/fefo', [InventoryController::class, 'apiFefoLots'])
        ->whereNumber('productId')->name('inventory.fefo');

    Route::post('/inventory/receipts', [InventoryOperationController::class, 'receive'])->name('inventory.receipts.store');
    Route::post('/inventory/adjustments', [InventoryOperationController::class, 'adjust'])->name('inventory.adjustments.store');
    Route::patch('/inventory/adjustments/{adjustmentId}/resolve', [InventoryOperationController::class, 'resolveAdjustment'])
        ->whereNumber('adjustmentId')->name('inventory.adjustments.resolve');
    Route::post('/inventory/transfers', [InventoryOperationController::class, 'transfer'])->name('inventory.transfers.store');
    Route::put('/inventory/{productId}/stock-limits', [InventoryOperationController::class, 'updateStockLimits'])
        ->whereNumber('productId')->name('inventory.stock-limits.update');
});
