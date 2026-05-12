<?php

declare(strict_types=1);

use App\Providers\AppServiceProvider;
use App\Providers\TenancyServiceProvider;
use Stancl\Tenancy\TenancyServiceProvider as StanclTenancyServiceProvider;

return [
    AppServiceProvider::class,
    StanclTenancyServiceProvider::class,
    TenancyServiceProvider::class,
];
