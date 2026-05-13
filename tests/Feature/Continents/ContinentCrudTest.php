<?php

declare(strict_types=1);

use App\Models\Continent;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesTenants;
use Tests\Concerns\IssuesTokens;

uses(RefreshDatabase::class, CreatesTenants::class, IssuesTokens::class);

it('creates, lists, updates, and soft deletes a continent', function () {
    $tenant = $this->createTenant('continent-crud-1');
    $token = $this->issuePersonalAccessTokenFor($tenant, 'continent@example.com');

    $create = $this->withHeaders(['Authorization' => "Bearer {$token}"])
        ->postJson('http://continent-crud-1.api.test/v1/continents', [
            'name' => 'Africa',
            'code' => 'AF',
            'is_active' => true,
        ]);

    expect($create->status())->toBe(201);
    expect($create->json('data'))->toHaveKeys(['id', 'name', 'code', 'is_active']);
    expect($create->json('data.code'))->toBe('AF');

    $identifier = (string) $create->json('data.id');

    $list = $this->withHeaders(['Authorization' => "Bearer {$token}"])
        ->getJson('http://continent-crud-1.api.test/v1/continents');

    expect($list->status())->toBe(200);
    expect($list->json('data.0.id'))->toBe($identifier);

    $update = $this->withHeaders(['Authorization' => "Bearer {$token}"])
        ->putJson("http://continent-crud-1.api.test/v1/continents/{$identifier}", [
            'name' => 'Africa Updated',
            'code' => 'AFR',
            'is_active' => false,
        ]);

    expect($update->status())->toBe(200);
    expect($update->json('data.name'))->toBe('Africa Updated');
    expect($update->json('data.code'))->toBe('AFR');
    expect($update->json('data.is_active'))->toBeFalse();

    $delete = $this->withHeaders(['Authorization' => "Bearer {$token}"])
        ->deleteJson("http://continent-crud-1.api.test/v1/continents/{$identifier}");

    expect($delete->status())->toBe(204);

    $tenant->run(function () use ($identifier): void {
        $continent = Continent::withTrashed()->where('identifier', $identifier)->first();

        expect($continent)->not->toBeNull();
        expect($continent?->deleted_at)->not->toBeNull();
    });
})->group('serial');

it('requires auth token for continent routes', function () {
    $this->createTenant('continent-auth-1');

    $response = $this->postJson('http://continent-auth-1.api.test/v1/continents', [
        'name' => 'Europe',
        'code' => 'EU',
    ]);

    expect($response->status())->toBe(401);
})->group('serial');

it('validates continent payload', function () {
    $tenant = $this->createTenant('continent-validation-1');
    $token = $this->issuePersonalAccessTokenFor($tenant, 'continent-validation@example.com');

    $response = $this->withHeaders(['Authorization' => "Bearer {$token}"])
        ->postJson('http://continent-validation-1.api.test/v1/continents', [
            'name' => '',
            'code' => 'toolong',
        ]);

    expect($response->status())->toBe(422);
})->group('serial');
