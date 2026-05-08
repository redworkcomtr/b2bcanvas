<?php

use App\Http\Controllers\Api\IssueController;
use App\Http\Controllers\Api\NotificationSubscriptionController;
use App\Http\Controllers\Api\OrderController;
use App\Http\Controllers\Api\PortalController;
use App\Http\Controllers\Api\ProductMappingController;
use Illuminate\Support\Facades\Route;

Route::prefix('api')->group(function () {
    Route::get('/portal', PortalController::class);
    Route::get('/orders', [OrderController::class, 'index']);
    Route::post('/orders', [OrderController::class, 'store']);
    Route::get('/orders/{uuid}', [OrderController::class, 'show']);
    Route::post('/orders/imports/preview', [OrderController::class, 'importPreview']);
    Route::post('/product-mappings', [ProductMappingController::class, 'store']);
    Route::post('/issues/{type}', [IssueController::class, 'store']);
    Route::patch('/notifications/subscriptions/{subscription}', [NotificationSubscriptionController::class, 'update']);
});

Route::get('/{any?}', function () {
    return view('app');
})->where('any', '^(?!api).*$');
