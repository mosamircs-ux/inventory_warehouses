<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\InventoryItemController;
use App\Http\Controllers\WarehouseController;
use App\Http\Controllers\StockTransferController;


Route::controller(AuthController::class)->prefix('auth')->group(function () {
    Route::post('register', 'register');
    Route::post('login', 'login');
});


Route::middleware(['auth:sanctum', 'throttle:60,1'])->group(function () {

    Route::controller(AuthController::class)->prefix('auth')->group(function () {
        Route::post('logout', 'logout');
    });

    Route::get('inventory/{inventoryItem}/stats', [InventoryItemController::class, 'stats']);

    Route::apiResource('inventory', InventoryItemController::class);

    Route::prefix('warehouses')->controller(WarehouseController::class)->group(function () {
        Route::get('/', 'index');
        Route::post('/', 'store');
        Route::get('{warehouse}', 'show');
        Route::put('{warehouse}', 'update');
        Route::delete('{warehouse}', 'destroy');
        Route::get('{warehouse}/inventory', 'getInventory');
    });

    Route::prefix('stock-transfers')->controller(StockTransferController::class)->group(function () {
        Route::get('stats', 'stats');

        Route::get('/', 'index');
        Route::post('/', 'store');

        Route::get('{stockTransfer}', 'show');

        Route::post('{stockTransfer}/cancel', 'cancel');
    });
});
