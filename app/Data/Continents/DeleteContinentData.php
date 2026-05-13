<?php

declare(strict_types=1);

namespace App\Data\Continents;

use App\Data\BaseData;
use Spatie\LaravelData\Attributes\Validation\Max;
use Spatie\LaravelData\Attributes\Validation\Min;
use Spatie\LaravelData\Attributes\Validation\Required;
use Spatie\LaravelData\Attributes\Validation\StringType;

final class DeleteContinentData extends BaseData
{
    public function __construct(
        #[Required, StringType, Min(36), Max(36)]
        public readonly string $identifier,
    ) {}
}
