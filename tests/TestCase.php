<?php

declare(strict_types=1);

namespace Tests;

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    /**
     * Runs before RefreshDatabase and other trait setups — safe place to
     * override DB connection config so migrations hit the right worker DB.
     */
    protected function defineEnvironment(Application $app): void
    {
        $token = (int) (getenv('TEST_TOKEN') ?: 1);

        $app['config']->set('database.connections.central.database', "api_kit_test_central_w{$token}");
        $app['config']->set('database.redis.default.database', 10 + $token);

        $this->applyTenantTemplateConnection($app);

        $app['config']->set('tenancy.bootstrappers', array_values(array_filter(
            $app['config']->get('tenancy.bootstrappers', []),
            fn ($b) => $b !== \Stancl\Tenancy\Bootstrappers\FilesystemTenancyBootstrapper::class,
        )));
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->ensurePassportPersonalAccessClient();
    }

    private function ensurePassportPersonalAccessClient(): void
    {
        // Passport::useClientModel() points at PassportClient (central connection).
        // The personal access client must exist on the central DB before createToken() works.
        // Seed it once per test run if missing — idempotent.
        $client = \Laravel\Passport\Passport::client();
        $exists = $client->newQuery()
            ->where('revoked', false)
            ->get()
            ->contains(fn ($c) => $c->hasGrantType('personal_access'));

        if (! $exists) {
            app(\Laravel\Passport\ClientRepository::class)
                ->createPersonalAccessGrantClient('Test Personal Access Client');
        }
    }

    private function registerTenantTemplateConnection(): void
    {
        $tenantTemplate = [
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

        // Register as 'tenant' (which purgeTenantConnection will wipe) AND as
        // 'tenant_template' (which survives purge and is used as the template).
        config()->set('database.connections.tenant', $tenantTemplate);
        config()->set('database.connections.tenant_template', $tenantTemplate);
        config()->set('tenancy.database.template_tenant_connection', 'tenant_template');
    }
}
