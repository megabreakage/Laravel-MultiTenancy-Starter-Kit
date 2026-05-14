<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Tenant;
use Illuminate\Console\Command;

class MigrateAllDatabases extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'migrate:all
                            {--seed : Seed the databases after migration}
                            {--central-only : Only migrate the central database}
                            {--tenants-only : Only migrate tenant databases}
                            {--tenant= : Migrate a specific tenant by ID}
                            {--force : Force the operation to run in production}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Run pending migrations for central and tenant databases without dropping existing tables, then seed if requested';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $this->info('🚀 Starting database migration sync...');
        $this->newLine();

        $centralOnly = $this->option('central-only');
        $tenantsOnly = $this->option('tenants-only');
        $specificTenant = $this->option('tenant');
        $shouldSeed = $this->option('seed');
        $force = $this->option('force');

        if ($centralOnly && $tenantsOnly) {
            $this->error('Cannot use --central-only and --tenants-only together.');

            return self::FAILURE;
        }

        $failed = false;

        if (! $tenantsOnly) {
            $result = $this->migrateCentral($shouldSeed, $force);
            if ($result !== self::SUCCESS) {
                $failed = true;
            }
        }

        if (! $centralOnly) {
            $result = $this->migrateTenants($shouldSeed, $force, $specificTenant);
            if ($result !== self::SUCCESS) {
                $failed = true;
            }
        }

        $this->newLine();

        if ($failed) {
            $this->error('⚠️  Migration completed with errors. Review the output above.');

            return self::FAILURE;
        }

        $this->info('✅ All database migrations completed successfully!');

        return self::SUCCESS;
    }

    /**
     * Migrate the central database.
     */
    protected function migrateCentral(bool $shouldSeed, bool $force): int
    {
        $this->info('━━━ Central Database ━━━');

        $migrateOptions = [];
        if ($force) {
            $migrateOptions['--force'] = true;
        }

        $result = $this->call('migrate', $migrateOptions);
        if ($result !== self::SUCCESS) {
            $this->error('Central database migration failed.');

            return self::FAILURE;
        }

        if ($shouldSeed) {
            $this->info('Seeding central database...');
            $seedResult = $this->seedCentral($force);
            if ($seedResult !== self::SUCCESS) {
                $this->warn('Central database seeding encountered issues.');

                return self::FAILURE;
            }
        }

        $this->info('Central database migration complete.');
        $this->newLine();

        return self::SUCCESS;
    }

    /**
     * Seed the central database with only additive seeders.
     */
    protected function seedCentral(bool $force): int
    {
        $seedOptions = [
            '--class' => 'Database\\Seeders\\RolePermissionsSeeder',
        ];
        if ($force) {
            $seedOptions['--force'] = true;
        }
        $result = $this->call('db:seed', $seedOptions);
        if ($result !== self::SUCCESS) {
            return self::FAILURE;
        }
        $seedOptions['--class'] = 'Database\\Seeders\\ModuleSeeder';
        $result = $this->call('db:seed', $seedOptions);
        if ($result !== self::SUCCESS) {
            return self::FAILURE;
        }
        $seedOptions['--class'] = 'Database\\Seeders\\ContinentSeeder';
        $result = $this->call('db:seed', $seedOptions);
        if ($result !== self::SUCCESS) {
            return self::FAILURE;
        }
        $seedOptions['--class'] = 'Database\\Seeders\\CountrySeeder';

        return $this->call('db:seed', $seedOptions);
    }

    /**
     * Migrate tenant databases.
     */
    protected function migrateTenants(bool $shouldSeed, bool $force, ?string $specificTenant): int
    {
        $this->info('━━━ Tenant Databases ━━━');

        $query = Tenant::query();
        if ($specificTenant) {
            $query->where('id', $specificTenant);
        }
        $tenants = $query->get();
        if ($tenants->isEmpty()) {
            $this->warn($specificTenant
                ? "Tenant '{$specificTenant}' not found."
                : 'No tenants found.');

            return self::SUCCESS;
        }
        $migratable = $tenants->filter(fn (Tenant $t) => $this->isMigratableTenant($t));
        $skipped = $tenants->count() - $migratable->count();
        if ($skipped > 0) {
            $this->warn("Skipping {$skipped} non-migratable tenant(s) (SYSTEM / test leftovers).");
        }
        if ($migratable->isEmpty()) {
            $this->warn('No migratable tenants found.');

            return self::SUCCESS;
        }
        $this->info("Found {$migratable->count()} tenant(s) to migrate.");
        $this->newLine();
        $migrateOptions = [
            '--path' => [database_path('migrations/tenant')],
            '--realpath' => true,
        ];
        if ($force) {
            $migrateOptions['--force'] = true;
        }
        $failedTenants = [];
        foreach ($migratable as $tenant) {
            $tenantId = $tenant->getTenantKey();
            $this->info("Migrating tenant: {$tenantId}");
            try {
                $tenant->run(function () use ($migrateOptions, $shouldSeed, $tenantId, &$failedTenants) {
                    $result = $this->call('migrate', $migrateOptions);
                    if ($result !== self::SUCCESS) {
                        $failedTenants[] = $tenantId;
                        $this->error("  Migration failed for tenant: {$tenantId}");

                        return;
                    }
                    if ($shouldSeed) {
                        $this->info("  Seeding tenant: {$tenantId}");
                        $seedResult = $this->call('db:seed', [
                            '--class' => 'Database\\Seeders\\TenantDatabaseSeeder',
                        ]);
                        if ($seedResult !== self::SUCCESS) {
                            $this->warn("  Seeding issues for tenant: {$tenantId}");
                        }
                    }
                    $this->info("  ✓ Tenant {$tenantId} complete.");
                });
            } catch (\Throwable $e) {
                $failedTenants[] = $tenantId;
                $this->error("  ✗ Tenant {$tenantId} failed: {$e->getMessage()}");
            }
        }
        $this->newLine();
        if (! empty($failedTenants)) {
            $this->error('Failed tenants: ' . implode(', ', $failedTenants));

            return self::FAILURE;
        }
        $this->info("All {$migratable->count()} tenant(s) migrated successfully.");

        return self::SUCCESS;
    }

    /**
     * Determine if a tenant should be migrated.
     *
     * Skips the SYSTEM tenant (no database) and TEST* tenants (test leftovers).
     */
    protected function isMigratableTenant(Tenant $tenant): bool
    {
        $tenantId = $tenant->getTenantKey();
        if ($tenantId === 'SYSTEM') {
            return false;
        }
        if (str_starts_with($tenantId, 'TEST')) {
            return false;
        }

        return true;
    }
}
