<?php

declare(strict_types=1);

namespace Tests\Concerns;

use App\Models\Central\Tenant;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;

trait CreatesTenants
{
    /** @var array<string> */
    private array $createdTenantIds = [];

    protected function setUpCreatesTenants(): void
    {
        $this->createdTenantIds = [];
    }

    protected function tearDownCreatesTenants(): void
    {
        foreach ($this->createdTenantIds as $tenantId) {
            $prefix = config('tenancy.database.prefix', 'tenant_');
            $suffix = config('tenancy.database.suffix', '');
            $dbName = $prefix . $tenantId . $suffix;

            try {
                DB::connection('central')->statement("DROP DATABASE IF EXISTS `{$dbName}`");
            } catch (\Throwable) {
            }

            try {
                Tenant::where('id', $tenantId)->forceDelete();
            } catch (\Throwable) {
            }
        }
    }

    protected function createTenant(string $slug): Tenant
    {
        $this->createdTenantIds[] = $slug;
        $tenant = Tenant::create(['id' => $slug]);
        $tenant->domains()->create(['domain' => "{$slug}.api.test"]);

        return $tenant;
    }
}
