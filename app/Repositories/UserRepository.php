<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Models\User;
use App\Repositories\Contracts\UserRepositoryInterface;

final class UserRepository extends BaseRepository implements UserRepositoryInterface
{
    protected function model(): string
    {
        return User::class;
    }

    protected function allowedFilters(): array
    {
        return ['name', 'email'];
    }

    protected function allowedIncludes(): array
    {
        return [];
    }

    protected function allowedSorts(): array
    {
        return ['name', 'email', 'created_at'];
    }

    public function findByEmail(string $email): ?User
    {
        return User::query()->where('email', $email)->first();
    }
}
