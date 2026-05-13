<?php

declare(strict_types=1);

use App\Providers\AppServiceProvider;
use App\Providers\TenancyServiceProvider;
use Stancl\Tenancy\TenancyServiceProvider as StanclTenancyServiceProvider;

return [
    AppServiceProvider::class,
    Spatie\Permission\PermissionServiceProvider::class,
    StanclTenancyServiceProvider::class,
    TenancyServiceProvider::class,
];
