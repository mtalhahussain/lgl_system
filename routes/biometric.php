<?php

use App\Http\Controllers\BiometricBridgeController;
use App\Http\Controllers\BiometricHookController;
use Illuminate\Support\Facades\Route;

// Loaded without the web group: no session, cookies or CSRF. Secret tokens identify the machine / bridge.

// Machine push endpoint (Hikvision HTTP Listening).
Route::match(['get', 'post'], '/biometric/hook/{token}', [BiometricHookController::class, 'receive'])
    ->where('token', '[A-Za-z0-9]{32,64}')
    ->middleware('throttle:600,1')
    ->name('biometric.hook');

// Institute-PC bridge: pending machine users and their results.
Route::middleware('throttle:120,1')->where(['token' => '[A-Za-z0-9]{32,64}'])->group(function () {
    Route::get('/biometric/bridge/{token}/pending', [BiometricBridgeController::class, 'pending'])->name('biometric.bridge.pending');
    Route::post('/biometric/bridge/{token}/results', [BiometricBridgeController::class, 'results'])->name('biometric.bridge.results');
});
