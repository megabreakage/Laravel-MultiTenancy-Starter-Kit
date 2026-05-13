<?php

declare(strict_types=1);

namespace App\Data\Continents;

use App\Data\BaseData;
use Spatie\LaravelData\Attributes\Validation\Max;
use Spatie\LaravelData\Attributes\Validation\Min;
use Spatie\LaravelData\Attributes\Validation\Regex;
use Spatie\LaravelData\Attributes\Validation\Required;
use Spatie\LaravelData\Attributes\Validation\StringType;

final class CreateContinentData extends BaseData
{
    public function __construct(
        #[Required, StringType, Min(2), Max(120)]
        public readonly string $name,
        #[Required, StringType, Regex('/^[A-Z]{2,3}$/')]
        public readonly string $code,
        public readonly bool $is_active = true,
    ) {}
}
