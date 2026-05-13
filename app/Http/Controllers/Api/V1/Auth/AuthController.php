<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Auth;

use App\Actions\Auth\RegisterUserAction;
use App\Data\Auth\LoginData;
use App\Data\Auth\RegisterUserData;
use App\Exceptions\DomainException;
use App\Http\Controllers\Api\V1\BaseApiController;
use App\Http\Resources\UserResource;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

final class AuthController extends BaseApiController
{
    public function register(RegisterUserData $dto, RegisterUserAction $action): JsonResponse
    {
        $user = $action->execute($dto);

        $token = $user->createToken('api-token')->accessToken;

        return $this->success([
            'token' => $token,
            'token_type' => 'Bearer',
            'user' => UserResource::make($user)->resolve(),
        ], Response::HTTP_CREATED);
    }

    public function login(LoginData $dto): JsonResponse
    {
        if (! Auth::attempt(['email' => $dto->email, 'password' => $dto->password])) {
            throw new DomainException('Invalid credentials.');
        }

        /** @var User $user */
        $user = Auth::user();
        $token = $user->createToken('api-token')->accessToken;

        return $this->success([
            'token' => $token,
            'token_type' => 'Bearer',
            'user' => UserResource::make($user)->resolve(),
        ]);
    }

    public function logout(Request $request): JsonResponse
    {
        $request->user()->token()->revoke();

        return $this->success(['message' => 'Logged out successfully.']);
    }

    public function me(Request $request): JsonResponse
    {
        return $this->success(UserResource::make($request->user())->resolve());
    }
}
