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
        return ['first_name', 'last_name', 'username', 'email', 'phone', 'is_active'];
    }

    protected function allowedIncludes(): array
    {
        return ['roles'];
    }

    protected function allowedSorts(): array
    {
        return ['first_name', 'last_name', 'username', 'email', 'created_at', 'last_login_at'];
    }

    public function findByEmail(string $email): ?User
    {
        return User::query()->where('email', $email)->first();
    }
}
