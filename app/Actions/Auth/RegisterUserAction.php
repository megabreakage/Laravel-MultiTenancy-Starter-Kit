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

        $user = $this->users->create([
            'first_name' => $dto->first_name,
            'middle_name' => $dto->middle_name,
            'last_name' => $dto->last_name,
            'username' => $dto->username,
            'email' => $dto->email,
            'password' => $dto->password,
            'country_code' => $dto->country_code ?? '+254',
            'phone' => $dto->phone,
        ]);

        $user->assignRole('user');

        return $user;
    }
}
