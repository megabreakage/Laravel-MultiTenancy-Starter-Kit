<?php

declare(strict_types=1);

it('shows tenant connection config', function () {
    $driver = config('database.connections.tenant.driver');
    $host = config('database.connections.tenant.host');
    $template = config('tenancy.database.template_tenant_connection');
    dump("driver: {$driver}", "host: {$host}", "template: {$template}");
    expect($driver)->toBe('mysql');
    expect($host)->toBe('mysql-test');
});
