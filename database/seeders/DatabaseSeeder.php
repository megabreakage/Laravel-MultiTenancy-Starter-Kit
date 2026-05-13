<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Central\SuperAdmin;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $email = (string) env('SUPER_ADMIN_EMAIL', 'admin@api.localhost');
        $password = (string) env('SUPER_ADMIN_PASSWORD', 'password');
        $name = (string) env('SUPER_ADMIN_NAME', 'Super Admin');

        SuperAdmin::withoutEvents(function () use ($email, $password, $name): void {
            SuperAdmin::firstOrCreate(
                ['email' => $email],
                [
                    'identifier' => (string) Str::uuid(),
                    'name' => $name,
                    'password' => Hash::make($password),
                    'email_verified_at' => now(),
                ]
            );
        });
    }
}
