<?php

use App\Http\Controllers\Api\CheckInController;
use App\Http\Controllers\Api\PayMongoWebhookController;
use App\Http\Middleware\EnsureScannerKey;
use Illuminate\Support\Facades\Route;

Route::middleware(['throttle:60,1', EnsureScannerKey::class])
    ->post('/check-in', CheckInController::class)
    ->name('api.check-in');

Route::post('/webhooks/paymongo', PayMongoWebhookController::class)
    ->middleware('throttle:120,1')
    ->name('webhooks.paymongo');
