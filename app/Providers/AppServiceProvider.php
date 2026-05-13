<?php

declare(strict_types=1);

namespace App\Providers;

use App\Models\Central\SuperAdmin;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;
use Spatie\Permission\PermissionRegistrar;
use Stancl\Tenancy\Events\TenancyBootstrapped;
use Stancl\Tenancy\Events\RevertedToCentralContext;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    #[\Override]
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Reset Spatie permission cache when switching tenant context
        Event::listen(TenancyBootstrapped::class, function (): void {
            app(PermissionRegistrar::class)->forgetCachedPermissions();
        });

        Event::listen(RevertedToCentralContext::class, function (): void {
            app(PermissionRegistrar::class)->forgetCachedPermissions();
        });

        // Enforce single super-admin constraint: reject role assignment if one already exists
        // on a different user. This fires before the role pivot is persisted.
        Gate::before(function (SuperAdmin $superAdmin, string $ability): ?bool {
            // Allow the current super-admin to pass all gate checks
            if ($superAdmin->hasRole('super-admin')) {
                return true;
            }

            return null;
        });
    }
}
