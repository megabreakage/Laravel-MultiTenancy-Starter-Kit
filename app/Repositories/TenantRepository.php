<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Models\Central\Tenant;
use App\Repositories\Contracts\TenantRepositoryInterface;
use Illuminate\Database\Eloquent\Model;

final class TenantRepository extends BaseRepository implements TenantRepositoryInterface
{
    protected function model(): string
    {
        return Tenant::class;
    }

    protected function allowedFilters(): array
    {
        return ['plan', 'status'];
    }

    protected function allowedIncludes(): array
    {
        return [];
    }

    protected function allowedSorts(): array
    {
        return ['id', 'created_at'];
    }

    public function findByIdentifier(string $identifier): Model
    {
        return Tenant::query()->where('id', $identifier)->firstOrFail();
    }
}
