<?php

declare(strict_types=1);

use Tests\Concerns\CreatesTenants;

uses(CreatesTenants::class);

it('shows full tenant connection config at feature test time', function () {
    dump(config('database.connections.tenant'));
    expect(config('database.connections.tenant.driver'))->toBe('mysql');
});
