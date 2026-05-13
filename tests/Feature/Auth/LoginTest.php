<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesTenants;

uses(RefreshDatabase::class, CreatesTenants::class);

it('issues a token on valid credentials', function () {
    $tenant = $this->createTenant('auth-login-1');

    $tenant->run(function () {
        \App\Models\User::factory()->create([
            'email' => 'user@example.com',
            'password' => 'password123',
        ]);
    });

    $response = $this->withHeaders(['Host' => 'auth-login-1.api.test'])
        ->postJson('/v1/auth/login', [
            'email' => 'user@example.com',
            'password' => 'password123',
        ]);

    expect($response->status())->toBe(200);
    expect($response->json('data'))->toHaveKeys(['token', 'token_type', 'user']);
})->group('serial');

it('rejects invalid credentials', function () {
    $tenant = $this->createTenant('auth-login-2');

    $tenant->run(function () {
        \App\Models\User::factory()->create(['email' => 'user@login-2.test']);
    });

    $response = $this->withHeaders(['Host' => 'auth-login-2.api.test'])
        ->postJson('/v1/auth/login', [
            'email' => 'user@login-2.test',
            'password' => 'wrong-password',
        ]);

    expect($response->status())->toBe(422);
})->group('serial');
