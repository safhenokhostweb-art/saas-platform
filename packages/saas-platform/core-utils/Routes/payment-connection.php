<?php

use Illuminate\Support\Facades\Route;
use SaaSPlatform\CoreUtils\Http\Controllers\PaymentConnectionPreflightController;

Route::post('/payment-connection/preflight', PaymentConnectionPreflightController::class)
    ->middleware(['web', 'throttle:30,1'])
    ->name('payment-connection.preflight');
