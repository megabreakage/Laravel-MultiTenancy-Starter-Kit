<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Central\Tenant;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

final class FreshMigrateSeedCommand extends Command
{
    protected $signature = 'db:fresh-migrate-seed
                            {--force : Skip confirmation prompt}';

    protected $description = 'Drop and recreate central and all tenant databases, then migrate and seed.';

    public function handle(): int
    {
        if (
            ! $this->option('force')
            && ! $this->confirm('This will DROP all central and tenant databases. Are you sure?')
        ) {
            $this->components->warn('Aborted.');

            return self::SUCCESS;
        }

        // Drop all tenant databases BEFORE wiping central — we need the tenants
        // table to enumerate which databases to delete.
        $this->components->info('Dropping tenant databases...');
        $prefix = config('tenancy.database.prefix', 'tenant_');
        $suffix = config('tenancy.database.suffix', '');
        Tenant::all()->each(function (Tenant $tenant) use ($prefix, $suffix): void {
            $dbName = $prefix . $tenant->getTenantKey() . $suffix;
            DB::statement("DROP DATABASE IF EXISTS `{$dbName}`");
            $this->line("  Dropped: {$dbName}");
        });

        $this->components->info('Fresh migrating central database...');
        $this->call('migrate:fresh', ['--force' => true]);

        $this->components->info('Seeding central database...');
        $this->call('db:seed', ['--force' => true]);

        if (app()->environment('local', 'staging')) {
            $this->components->info('Creating test tenant...');
            $tenant = Tenant::create([
                'id'     => 'test-corp',
                'plan'   => 'free',
                'status' => 'active',
            ]);
            $tenant->domains()->create(['domain' => 'test-corp.localhost']);
            $this->components->info('Test tenant created: test-corp (test-corp.localhost)');
        }

        $this->components->info('Done.');

        return self::SUCCESS;
    }
}
