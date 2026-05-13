<?php

declare(strict_types=1);

namespace Tests\Concerns;

use App\Models\Central\Tenant;
use Illuminate\Support\Facades\Artisan;

trait CreatesTenants
{
    protected function createTenant(string $slug): Tenant
    {
        $tenant = Tenant::create(['id' => $slug, 'plan' => 'free']);
        $tenant->domains()->create(['domain' => "{$slug}.api.test"]);
        $tenant->run(fn () => Artisan::call('migrate', [
            '--database' => 'tenant',
            '--path' => 'database/migrations/tenant',
            '--realpath' => true,
        ]));

        return $tenant;
    }
}
