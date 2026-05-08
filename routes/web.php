<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\IssueController;
use App\Http\Controllers\Api\NotificationSubscriptionController;
use App\Http\Controllers\Api\OrderController;
use App\Http\Controllers\Api\PortalController;
use App\Http\Controllers\Api\ProductMappingController;
use App\Http\Controllers\Api\UserController;
use App\Http\Middleware\EnsureActiveTenant;
use Illuminate\Support\Facades\Route;

Route::prefix('api')->group(function () {
    Route::post('/auth/login', [AuthController::class, 'login'])->middleware('throttle:10,1');
    Route::post('/auth/forgot-password', [AuthController::class, 'forgotPassword'])->middleware('throttle:5,1');
    Route::post('/auth/reset-password', [AuthController::class, 'resetPassword'])->middleware('throttle:5,1');

    Route::middleware(['auth', EnsureActiveTenant::class])->group(function () {
        Route::get('/auth/session', [AuthController::class, 'session']);
        Route::post('/auth/logout', [AuthController::class, 'logout']);

        Route::get('/portal', PortalController::class);
        Route::get('/workspace', PortalController::class);
        Route::get('/orders', [OrderController::class, 'index']);
        Route::post('/orders', [OrderController::class, 'store']);
        Route::get('/orders/{uuid}', [OrderController::class, 'show']);
        Route::post('/orders/imports/preview', [OrderController::class, 'importPreview']);
        Route::post('/product-mappings', [ProductMappingController::class, 'store']);
        Route::post('/issues/{type}', [IssueController::class, 'store']);
        Route::patch('/notifications/subscriptions/{subscription}', [NotificationSubscriptionController::class, 'update']);

        Route::get('/users', [UserController::class, 'index']);
        Route::post('/users/invites', [UserController::class, 'invite']);
        Route::patch('/users/{user}', [UserController::class, 'update']);
    });
});

Route::get('/{any?}', function () {
    return view('app');
})->where('any', '^(?!api).*$');
