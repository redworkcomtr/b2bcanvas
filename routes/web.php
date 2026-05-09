<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\ClaimController;
use App\Http\Controllers\Api\IssueController;
use App\Http\Controllers\Api\MediaUploadController;
use App\Http\Controllers\Api\NotificationController;
use App\Http\Controllers\Api\NotificationSubscriptionController;
use App\Http\Controllers\Api\OrderController;
use App\Http\Controllers\Api\PaymentController;
use App\Http\Controllers\Api\PortalController;
use App\Http\Controllers\Api\ProductCatalogController;
use App\Http\Controllers\Api\ProductMappingController;
use App\Http\Controllers\Api\RequiredActionController;
use App\Http\Controllers\Api\UserController;
use App\Http\Middleware\EnsureActiveTenant;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Support\Facades\Route;

Route::prefix('api')->middleware('throttle:120,1')->group(function () {
    Route::post('/auth/login', [AuthController::class, 'login'])->middleware('throttle:10,1');
    Route::post('/auth/forgot-password', [AuthController::class, 'forgotPassword'])->middleware('throttle:5,1');
    Route::post('/auth/reset-password', [AuthController::class, 'resetPassword'])->middleware('throttle:5,1');

    Route::middleware(['auth', EnsureActiveTenant::class])->group(function () {
        Route::get('/auth/session', [AuthController::class, 'session']);
        Route::post('/auth/logout', [AuthController::class, 'logout']);

        Route::get('/portal', PortalController::class);
        Route::get('/workspace', PortalController::class);
        Route::get('/orders', [OrderController::class, 'index']);
        Route::get('/orders/export', [OrderController::class, 'export']);
        Route::get('/orders/saved-views', [OrderController::class, 'savedViews']);
        Route::post('/orders/saved-views', [OrderController::class, 'storeSavedView']);
        Route::delete('/orders/saved-views/{savedView}', [OrderController::class, 'destroySavedView']);
        Route::get('/orders/imports', [OrderController::class, 'importHistory']);
        Route::get('/orders/imports/template', [OrderController::class, 'importTemplate']);
        Route::post('/orders/imports/preview', [OrderController::class, 'importPreview']);
        Route::post('/orders/imports/{import}/commit', [OrderController::class, 'commitImport']);
        Route::get('/orders/imports/{import}/errors', [OrderController::class, 'importErrorReport']);
        Route::post('/orders', [OrderController::class, 'store']);
        Route::get('/orders/{uuid}', [OrderController::class, 'show']);
        Route::patch('/orders/{uuid}/address', [OrderController::class, 'updateAddress']);
        Route::patch('/orders/{uuid}/notes', [OrderController::class, 'updateNotes']);
        Route::post('/orders/{uuid}/transition', [OrderController::class, 'transition']);
        Route::post('/orders/{uuid}/payment/intent', [PaymentController::class, 'intent']);
        Route::post('/orders/{uuid}/payment/confirm', [PaymentController::class, 'confirm']);
        Route::get('/products', [ProductCatalogController::class, 'index']);
        Route::post('/products/types', [ProductCatalogController::class, 'storeType']);
        Route::patch('/products/types/{productType}', [ProductCatalogController::class, 'updateType']);
        Route::delete('/products/types/{productType}', [ProductCatalogController::class, 'destroyType']);
        Route::post('/products/types/{productType}/variants', [ProductCatalogController::class, 'storeVariant']);
        Route::patch('/products/variants/{variant}', [ProductCatalogController::class, 'updateVariant']);
        Route::delete('/products/variants/{variant}', [ProductCatalogController::class, 'destroyVariant']);
        Route::post('/products/types/{productType}/options', [ProductCatalogController::class, 'storeOption']);
        Route::patch('/products/options/{option}', [ProductCatalogController::class, 'updateOption']);
        Route::delete('/products/options/{option}', [ProductCatalogController::class, 'destroyOption']);
        Route::get('/product-mappings', [ProductMappingController::class, 'index']);
        Route::post('/product-mappings', [ProductMappingController::class, 'store']);
        Route::post('/product-mappings/simulate', [ProductMappingController::class, 'simulate']);
        Route::post('/product-mappings/conflicts', [ProductMappingController::class, 'conflicts']);
        Route::patch('/product-mappings/{mapping}', [ProductMappingController::class, 'update']);
        Route::delete('/product-mappings/{mapping}', [ProductMappingController::class, 'destroy']);
        Route::post('/uploads', [MediaUploadController::class, 'store']);
        Route::get('/required-actions', [RequiredActionController::class, 'index']);
        Route::get('/required-actions/{requiredAction}', [RequiredActionController::class, 'show']);
        Route::post('/required-actions/{requiredAction}/comments', [RequiredActionController::class, 'comment']);
        Route::post('/required-actions/{requiredAction}/resolve', [RequiredActionController::class, 'resolve']);
        Route::post('/required-actions/{requiredAction}/reopen', [RequiredActionController::class, 'reopen']);
        Route::post('/required-actions/{requiredAction}/escalate', [RequiredActionController::class, 'escalate']);
        Route::get('/issues/{issue}', [IssueController::class, 'show'])->whereNumber('issue');
        Route::patch('/issues/{issue}', [IssueController::class, 'update'])->whereNumber('issue');
        Route::post('/issues/{issue}/comments', [IssueController::class, 'comment'])->whereNumber('issue');
        Route::post('/issues/{issue}/read', [IssueController::class, 'markRead'])->whereNumber('issue');
        Route::post('/issues/{type}', [IssueController::class, 'store']);
        Route::post('/claims/{issue}/resolution', [ClaimController::class, 'resolve'])->whereNumber('issue');
        Route::patch('/notifications/subscriptions/{subscription}', [NotificationSubscriptionController::class, 'update']);
        Route::get('/notifications/logs', [NotificationController::class, 'logs']);
        Route::get('/notifications/logs/{log}', [NotificationController::class, 'preview']);
        Route::post('/notifications/logs/{log}/retry', [NotificationController::class, 'retry']);

        Route::get('/users', [UserController::class, 'index']);
        Route::post('/users/invites', [UserController::class, 'invite']);
        Route::patch('/users/{user}', [UserController::class, 'update']);
    });
    Route::withoutMiddleware([VerifyCsrfToken::class])->post('/payments/stripe/webhook', [PaymentController::class, 'webhook']);

    Route::get('/notifications/unsubscribe/{token}', [NotificationController::class, 'unsubscribe'])->name('notifications.unsubscribe');
});

Route::get('/{any?}', function () {
    return view('app');
})->where('any', '^(?!api).*$');
