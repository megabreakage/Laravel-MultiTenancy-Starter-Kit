<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Auth;

use App\Data\Auth\SuperAdminLoginData;
use App\Exceptions\DomainException;
use App\Http\Controllers\Api\V1\BaseApiController;
use App\Http\Resources\SuperAdminResource;
use App\Models\Central\SuperAdmin;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

final class SuperAdminAuthController extends BaseApiController
{
    public function login(SuperAdminLoginData $dto): JsonResponse
    {
        if (! Auth::guard('super_admin')->attempt(['email' => $dto->email, 'password' => $dto->password])) {
            throw new DomainException('Invalid credentials.');
        }

        /** @var SuperAdmin $superAdmin */
        $superAdmin = Auth::guard('super_admin')->user();

        if (! $superAdmin->is_active) {
            Auth::guard('super_admin')->logout();
            throw new DomainException('Account is inactive.');
        }

        $superAdmin->update(['last_login_at' => now()]);

        $token = $superAdmin->createToken('super-admin-token')->accessToken;

        return $this->success([
            'token' => $token,
            'token_type' => 'Bearer',
            'user' => SuperAdminResource::make($superAdmin)->resolve(),
        ]);
    }

    public function logout(Request $request): JsonResponse
    {
        $request->user('super_admin')->token()->revoke();

        return $this->success(['message' => 'Logged out successfully.']);
    }

    public function me(Request $request): JsonResponse
    {
        return $this->success(SuperAdminResource::make($request->user('super_admin'))->resolve());
    }
}
