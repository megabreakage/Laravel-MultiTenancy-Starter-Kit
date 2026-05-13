<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesTenants;

uses(RefreshDatabase::class, CreatesTenants::class);

it('registers a new user and returns a token', function () {
    $tenant = $this->createTenant('auth-reg-1');

    $response = $this->withHeaders(['Host' => 'auth-reg-1.api.test'])
        ->postJson('/v1/auth/register', [
            'name' => 'Alice Smith',
            'email' => 'alice@example.com',
            'password' => 'password123',
        ]);

    expect($response->status())->toBe(201);
    expect($response->json('data'))->toHaveKeys(['token', 'token_type', 'user']);
    expect($response->json('data.token_type'))->toBe('Bearer');

    $tenant->run(fn () => expect(User::where('email', 'alice@example.com')->exists())->toBeTrue());
})->group('serial');

it('rejects registration with invalid data', function () {
    $tenant = $this->createTenant('auth-reg-2');

    $response = $this->withHeaders(['Host' => 'auth-reg-2.api.test'])
        ->postJson('/v1/auth/register', [
            'name' => '',
            'email' => 'not-an-email',
            'password' => 'short',
        ]);

    expect($response->status())->toBe(422);
})->group('serial');
