<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Command;

final class MigrateSeedCommand extends Command
{
    protected $signature = 'db:migrate-seed
                            {--force : Force the operation to run in production}';

    protected $description = 'Run migrations and seeders for central and all tenant databases (non-destructive).';

    public function handle(): int
    {
        $forceArgs = $this->option('force') ? ['--force' => true] : [];

        $this->components->info('Migrating central database...');
        $this->call('migrate', $forceArgs);

        $this->components->info('Seeding central database...');
        $this->call('db:seed', $forceArgs);

        $this->components->info('Migrating tenant databases...');
        $this->call('tenants:migrate', $forceArgs);

        $this->components->info('Done.');

        return self::SUCCESS;
    }
}
