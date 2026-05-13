<?php

declare(strict_types=1);

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\Event;
use Stancl\Tenancy\Events\TenancyEnded;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $token = (int) (getenv('TEST_TOKEN') ?: 1);

        // Per-worker central DB for parallel test runs.
        config()->set('database.connections.central.database', "api_kit_test_central_w{$token}");
        config()->set('database.redis.default.database', 10 + $token);

        // Stancl's DatabaseManager::purgeTenantConnection() calls unset() on
        // database.connections.tenant whenever tenancy ends (tenant->run() completes,
        // or any tenancy context revert). This wipes the driver, making it impossible
        // to create subsequent tenants. Re-register the tenant connection config
        // after each tenancy end event so createTenant() always finds a valid driver.
        $tenantConfig = config('database.connections.tenant') ?? $this->defaultTenantConnectionConfig();
        Event::listen(TenancyEnded::class, function () use ($tenantConfig): void {
            config()->set('database.connections.tenant', $tenantConfig);
        });
    }

    private function defaultTenantConnectionConfig(): array
    {
        return [
            'driver'         => 'mysql',
            'host'           => env('DB_TENANT_HOST', 'mysql-test'),
            'port'           => env('DB_TENANT_PORT', '3306'),
            'database'       => null,
            'username'       => env('DB_TENANT_USERNAME', 'api_kit'),
            'password'       => env('DB_TENANT_PASSWORD', 'testsecret'),
            'unix_socket'    => '',
            'charset'        => 'utf8mb4',
            'collation'      => 'utf8mb4_unicode_ci',
            'prefix'         => '',
            'prefix_indexes' => true,
            'strict'         => true,
            'engine'         => 'InnoDB',
        ];
    }
}
