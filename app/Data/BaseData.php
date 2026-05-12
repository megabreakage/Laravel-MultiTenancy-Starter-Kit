<?php

declare(strict_types=1);

namespace App\Data;

use Spatie\LaravelData\Data;

abstract class BaseData extends Data
{
    public static function forCreation(array $input): static
    {
        return static::from($input);
    }

    public static function forUpdate(array $input): static
    {
        $filtered = array_filter($input, fn ($v) => $v !== null);

        return static::from($filtered);
    }

    public function toArray(): array
    {
        return array_filter(parent::toArray(), fn ($v) => $v !== null);
    }
}
