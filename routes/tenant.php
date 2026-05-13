<?php

declare(strict_types=1);

use App\Http\Controllers\Api\V1\Auth\AuthController;
use App\Http\Controllers\Api\V1\ContinentController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function () {
    // Auth routes (public)
    Route::prefix('auth')->group(function () {
        Route::post('/register', [AuthController::class, 'register']);
        Route::post('/login', [AuthController::class, 'login']);

        Route::middleware(['auth:api', 'tenant.token'])->group(function () {
            Route::post('/logout', [AuthController::class, 'logout']);
            Route::get('/me', [AuthController::class, 'me']);
        });
    });

    Route::middleware(['auth:api', 'tenant.token'])->group(function () {
        Route::get('/continents', [ContinentController::class, 'index']);
        Route::get('/continents/{continent}', [ContinentController::class, 'show']);
        Route::post('/continents', [ContinentController::class, 'store']);
        Route::put('/continents/{continent}', [ContinentController::class, 'update']);
        Route::delete('/continents/{continent}', [ContinentController::class, 'destroy']);
        Route::delete('/continents/{continent}/force', [ContinentController::class, 'forceDestroy']);
    });
});
