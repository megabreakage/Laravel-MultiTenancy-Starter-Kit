<?php

declare(strict_types=1);

namespace App\Repositories\Contracts;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Spatie\QueryBuilder\QueryBuilder;

interface BaseRepositoryInterface
{
    public function newQuery(): QueryBuilder;

    public function findByIdentifier(string $identifier): Model;

    public function browseAll(): Collection;

    public function paginate(int $perPage = 15): LengthAwarePaginator;

    public function create(array $data): Model;

    public function update(Model $model, array $data): Model;

    public function delete(Model $model): void;

    public function forceDelete(Model $model): void;
}
