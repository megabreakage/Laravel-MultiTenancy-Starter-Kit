<?php

declare(strict_types=1);

use Tests\Concerns\CreatesTenants;
use Tests\Concerns\IssuesTokens;

uses(CreatesTenants::class, IssuesTokens::class);

it('rejects a token issued for tenant A when used on tenant B', function () {
    $a = $this->createTenant('acme');
    $b = $this->createTenant('beta');

    $token = $this->issuePersonalAccessTokenFor($a, 'user@a.test');

    $response = $this->withHeaders(['Authorization' => 'Bearer ' . $token])
        ->getJson('http://beta.api.test/v1/auth/me');

    expect($response->status())->toBe(403);
    expect($response->json())->toBeStandardErrorEnvelope('TENANT_MISMATCH');
})->skip('User model + auth routes not yet defined — revisit in Task 7.3');
