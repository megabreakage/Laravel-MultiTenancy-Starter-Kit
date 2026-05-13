<?php

declare(strict_types=1);

namespace App\Data\Continents;

use App\Data\BaseData;
use Spatie\LaravelData\Attributes\Validation\Max;
use Spatie\LaravelData\Attributes\Validation\Min;
use Spatie\LaravelData\Attributes\Validation\Regex;
use Spatie\LaravelData\Attributes\Validation\Required;
use Spatie\LaravelData\Attributes\Validation\StringType;

final class UpdateContinentData extends BaseData
{
    public function __construct(
        #[Required, StringType, Min(36), Max(36)]
        public readonly string $identifier,
        #[StringType, Min(2), Max(120)]
        public readonly ?string $name = null,
        #[StringType, Regex('/^[A-Z]{2,3}$/')]
        public readonly ?string $code = null,
        public readonly ?bool $is_active = null,
    ) {}
}
