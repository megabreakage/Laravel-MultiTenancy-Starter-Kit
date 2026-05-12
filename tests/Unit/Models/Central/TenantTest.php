<?php

declare(strict_types=1);

use App\Models\Central\Tenant;

it('lives on the central connection', function (): void {
    $tenant = new Tenant();
    expect($tenant->getConnectionName())->toBe('central');
});

it('has HasDatabase and HasDomains concerns', function (): void {
    $tenant = new Tenant();
    expect(in_array(\Stancl\Tenancy\Database\Concerns\HasDatabase::class, class_uses_recursive($tenant)))->toBeTrue();
    expect(in_array(\Stancl\Tenancy\Database\Concerns\HasDomains::class, class_uses_recursive($tenant)))->toBeTrue();
});
