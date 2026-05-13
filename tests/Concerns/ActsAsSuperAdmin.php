<?php

declare(strict_types=1);

namespace Tests\Concerns;

use App\Models\Central\SuperAdmin;
use Laravel\Passport\Passport;

trait ActsAsSuperAdmin
{
    protected function actingAsSuperAdmin(): SuperAdmin
    {
        $admin = SuperAdmin::factory()->create();

        Passport::actingAs($admin, [], 'super_admin');

        return $admin;
    }
}
