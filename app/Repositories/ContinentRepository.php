<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Models\Continent;
use App\Repositories\Contracts\ContinentRepositoryInterface;

final class ContinentRepository extends BaseRepository implements ContinentRepositoryInterface
{
    protected function model(): string
    {
        return Continent::class;
    }

    protected function allowedFilters(): array
    {
        return ['name', 'code', 'is_active'];
    }

    protected function allowedIncludes(): array
    {
        return [];
    }

    protected function allowedSorts(): array
    {
        return ['name', 'code', 'created_at', 'updated_at'];
    }

    protected function defaultSort(): string
    {
        return 'name';
    }
}
