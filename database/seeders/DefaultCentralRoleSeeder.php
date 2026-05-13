<?php

declare(strict_types=1);

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;

class DefaultCentralRoleSeeder extends Seeder
{
    public function run(): void
    {
        $roles = [
            [
                'name' => 'super-admin',
                'display_name' => 'Super Admin',
                'description' => 'Has access to all system features and settings',
                'guard_name' => 'api',
            ],
            [
                'name' => 'admin',
                'display_name' => 'Admin',
                'description' => 'Can manage users, roles, and permissions',
                'guard_name' => 'api',
            ],
            [
                'name' => 'user',
                'display_name' => 'User',
                'description' => 'Can access own data and perform basic operations',
                'guard_name' => 'api',
            ],
        ];

        foreach ($roles as $roleData) {
            $role = Role::on('central')->firstOrCreate(
                ['name' => $roleData['name'], 'guard_name' => $roleData['guard_name']],
                ['display_name' => $roleData['display_name'], 'description' => $roleData['description']],
            );

            if ($role->wasRecentlyCreated) {
                $this->command->info("Created central role: {$roleData['name']}");
            } else {
                $this->command->line("Central role already exists: {$roleData['name']}");
            }
        }
    }
}
