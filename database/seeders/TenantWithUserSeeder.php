<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class TenantWithUserSeeder extends Seeder
{
    /** Number of additional regular users to seed per tenant. */
    private const REGULAR_USER_COUNT = 3;

    public function run(): void
    {
        $tenantDomain = tenant()?->domains?->first()?->domain ?? 'tenant.localhost';
        $devEmail = "dev@{$tenantDomain}";

        // Guard against duplicate execution: skip if dev user already exists.
        if (User::where('email', $devEmail)->exists()) {
            $this->command->line("TenantWithUserSeeder: dev user [{$devEmail}] already exists. Skipping.");

            return;
        }

        // Create a dev tenant-admin for testing / local development.
        $devUser = User::withoutEvents(function () use ($devEmail): User {
            return User::create([
                'first_name' => 'Dev',
                'last_name' => 'User',
                'username' => 'dev_' . Str::random(6),
                'email' => $devEmail,
                'email_verified_at' => now(),
                'password' => Hash::make((string) env('TEST_TENANT_ADMIN_PASSWORD', 'password')),
                'is_active' => true,
                'country_code' => '+254',
                'preferred_timezone' => 'Africa/Nairobi',
            ]);
        });

        if (! $devUser->hasRole('tenant-admin')) {
            $devUser->assignRole('tenant-admin');
        }

        $this->command->info("Created dev tenant-admin: {$devEmail}");

        // Create additional regular users via factory.
        User::factory(self::REGULAR_USER_COUNT)->create()->each(function (User $user): void {
            if (! $user->hasRole('user')) {
                $user->assignRole('user');
            }
        });

        $this->command->info('Created ' . self::REGULAR_USER_COUNT . " regular users for tenant [{$tenantDomain}].");
    }
}
