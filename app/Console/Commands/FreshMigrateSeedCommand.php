<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Command;

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

        // Tenant DBs must be wiped before central is dropped — the tenants table
        // is needed to enumerate which databases to delete.
        $this->components->info('Dropping and recreating tenant databases...');
        $this->call('tenants:migrate-fresh', ['--force' => true]);

        $this->components->info('Fresh migrating central database...');
        $this->call('migrate:fresh', ['--force' => true]);

        $this->components->info('Seeding central database...');
        $this->call('db:seed', ['--force' => true]);

        $this->components->info('Done.');

        return self::SUCCESS;
    }
}
