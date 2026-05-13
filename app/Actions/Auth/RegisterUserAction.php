<?php

declare(strict_types=1);

namespace App\Actions\Auth;

use App\Actions\Concerns\AsAction;
use App\Data\Auth\RegisterUserData;
use App\Data\BaseData;
use App\Models\User;
use App\Repositories\Contracts\UserRepositoryInterface;
use Illuminate\Database\Eloquent\Model;

final class RegisterUserAction
{
    use AsAction;

    public function __construct(private readonly UserRepositoryInterface $users) {}

    protected function handle(BaseData $dto): Model
    {
        assert($dto instanceof RegisterUserData);

        return $this->users->create([
            'name' => $dto->name,
            'email' => $dto->email,
            'password' => $dto->password,
        ]);
    }
}
