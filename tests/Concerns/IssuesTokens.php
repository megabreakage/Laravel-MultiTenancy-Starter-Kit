<?php

declare(strict_types=1);

namespace Tests\Concerns;

use App\Models\Central\Tenant;
use App\Models\User;

trait IssuesTokens
{
    protected function issuePersonalAccessTokenFor(Tenant $tenant, string $email): string
    {
        return $tenant->run(function () use ($email): string {
            $user = User::factory()->create(['email' => $email]);

            return $user->createToken('test')->accessToken;
        });
    }
}
