<?php

declare(strict_types=1);

use App\Http\Controllers\Api\V1\Auth\SuperAdminAuthController;
use App\Http\Controllers\Api\V1\TenantController;
use Illuminate\Support\Facades\Route;

// Central domain API routes (super-admin, tenant management)
// These are accessible from the central domain (api.localhost)

Route::prefix('v1')->group(function () {
    Route::get('/health', [\App\Http\Controllers\Api\V1\HealthController::class, 'index']);

    // Super Admin authentication (public)
    Route::prefix('auth')->group(function () {
        Route::post('/login', [SuperAdminAuthController::class, 'login']);

        Route::middleware('auth:super_admin')->group(function () {
            Route::post('/logout', [SuperAdminAuthController::class, 'logout']);
            Route::get('/me', [SuperAdminAuthController::class, 'me']);
        });
    });

    Route::middleware('auth:super_admin')->group(function () {
        Route::post('/tenants', [TenantController::class, 'store']);
    });
});
