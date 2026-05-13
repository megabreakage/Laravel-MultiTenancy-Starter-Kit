<?php

declare(strict_types=1);

namespace App\Data\Tenants;

use App\Data\BaseData;
use Spatie\LaravelData\Attributes\Validation\In;
use Spatie\LaravelData\Attributes\Validation\Max;
use Spatie\LaravelData\Attributes\Validation\Min;
use Spatie\LaravelData\Attributes\Validation\Regex;
use Spatie\LaravelData\Attributes\Validation\Required;
use Spatie\LaravelData\Attributes\Validation\StringType;

final class CreateTenantData extends BaseData
{
    public function __construct(
        #[Required, StringType, Min(2), Max(63), Regex('/^[a-z0-9-]+$/')]
        public readonly string $id,
        #[Required, In(['free', 'pro', 'enterprise'])]
        public readonly string $plan,
    ) {}
}
