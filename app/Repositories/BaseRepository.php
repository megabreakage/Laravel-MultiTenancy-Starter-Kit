<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Repositories\Contracts\BaseRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Log;
use Spatie\QueryBuilder\QueryBuilder;

abstract class BaseRepository implements BaseRepositoryInterface
{
    abstract protected function model(): string;

    /** @return array<int, string|\Spatie\QueryBuilder\AllowedFilter> */
    abstract protected function allowedFilters(): array;

    /** @return array<int, string|\Spatie\QueryBuilder\AllowedInclude> */
    abstract protected function allowedIncludes(): array;

    /** @return array<int, string|\Spatie\QueryBuilder\AllowedSort> */
    abstract protected function allowedSorts(): array;

    protected function defaultSort(): string
    {
        return '-created_at';
    }

    public function newQuery(): QueryBuilder
    {
        return QueryBuilder::for($this->model())
            ->allowedFilters(...$this->allowedFilters())
            ->allowedIncludes(...$this->allowedIncludes())
            ->allowedSorts(...$this->allowedSorts())
            ->defaultSort($this->defaultSort());
    }

    public function findByIdentifier(string $identifier): Model
    {
        return $this->model()::query()->where('identifier', $identifier)->firstOrFail();
    }

    public function browseAll(): Collection
    {
        $threshold = (int) config('api.browse_all_warn_threshold', 1000);
        $results = $this->newQuery()->get();

        if ($results->count() > $threshold) {
            Log::warning('browseAll() returned a large result set', [
                'model' => $this->model(),
                'count' => $results->count(),
                'threshold' => $threshold,
            ]);
        }

        return $results;
    }

    public function paginate(int $perPage = 15): LengthAwarePaginator
    {
        return $this->newQuery()->paginate($perPage);
    }

    public function create(array $data): Model
    {
        return $this->model()::query()->create($data);
    }

    public function update(Model $model, array $data): Model
    {
        $model->fill($data)->save();

        return $model;
    }

    public function delete(Model $model): void
    {
        $model->delete();
    }

    public function forceDelete(Model $model): void
    {
        $model->forceDelete();
    }
}
