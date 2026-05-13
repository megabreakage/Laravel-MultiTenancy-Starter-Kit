<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Actions\Tenants\CreateTenantAction;
use App\Data\Tenants\CreateTenantData;
use App\Http\Resources\TenantResource;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

final class TenantController extends BaseApiController
{
    public function store(CreateTenantData $dto, CreateTenantAction $action): JsonResponse
    {
        $tenant = $action->execute($dto);

        return $this->success(TenantResource::make($tenant)->resolve(), Response::HTTP_CREATED);
    }
}
