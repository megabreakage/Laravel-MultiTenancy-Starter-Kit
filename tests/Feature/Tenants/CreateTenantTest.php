<?php

declare(strict_types=1);

use App\Models\Central\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\Concerns\ActsAsSuperAdmin;

uses(RefreshDatabase::class, ActsAsSuperAdmin::class);

it('provisions a new tenant DB and seeds it', function () {
    $this->actingAsSuperAdmin();

    $response = $this->postJson('http://api.test/v1/tenants', [
        'id' => 'acme',
        'plan' => 'free',
    ]);

    expect($response->status())->toBe(201);

    $tenant = Tenant::find('acme');
    expect($tenant)->not->toBeNull();

    expect(Schema::connection('central')->hasTable('users'))->toBeFalse();

    $tenant->run(fn () => expect(Schema::hasTable('users'))->toBeTrue());
})->group('serial');

it('rejects tenant creation without authentication', function () {
    $response = $this->postJson('http://api.test/v1/tenants', [
        'id' => 'unauthenticated',
        'plan' => 'free',
    ]);

    expect($response->status())->toBe(401);
})->group('serial');

it('rejects tenant creation with invalid data', function () {
    $this->actingAsSuperAdmin();

    $response = $this->postJson('http://api.test/v1/tenants', [
        'id' => 'INVALID ID!',
        'plan' => 'unknown',
    ]);

    expect($response->status())->toBe(422);
})->group('serial');
