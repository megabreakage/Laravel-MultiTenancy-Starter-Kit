<?php

declare(strict_types=1);

namespace App\Services;

use App\Repositories\Contracts\ContinentRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Model;

final class ContinentQueryService
{
    public function __construct(private readonly ContinentRepositoryInterface $continents) {}

    public function paginate(int $perPage = 15): LengthAwarePaginator
    {
        return $this->continents->paginate($perPage);
    }

    public function findByIdentifier(string $identifier): Model
    {
        return $this->continents->findByIdentifier($identifier);
    }
}
