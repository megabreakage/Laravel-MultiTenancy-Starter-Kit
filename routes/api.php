<?php

declare(strict_types=1);

use App\Http\Controllers\Api\V1\TenantController;
use Illuminate\Support\Facades\Route;

// Central domain API routes (super-admin, tenant management)
// These are accessible from the central domain (api.localhost)

Route::prefix('v1')->group(function () {
    Route::get('/health', [\App\Http\Controllers\Api\V1\HealthController::class, 'index']);

    Route::middleware('auth:super_admin')->group(function () {
        Route::post('/tenants', [TenantController::class, 'store']);
    });
});
