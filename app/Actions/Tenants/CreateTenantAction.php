<?php

declare(strict_types=1);

namespace App\Actions\Tenants;

use App\Actions\Concerns\AsAction;
use App\Data\BaseData;
use App\Data\Tenants\CreateTenantData;
use App\Models\Central\Tenant;
use App\Repositories\Contracts\TenantRepositoryInterface;
use Illuminate\Database\Eloquent\Model;

final class CreateTenantAction
{
    use AsAction;

    public function __construct(private readonly TenantRepositoryInterface $tenants) {}

    protected function handle(BaseData $dto): Model
    {
        assert($dto instanceof CreateTenantData);

        /** @var Tenant $tenant */
        $tenant = $this->tenants->create([
            'id' => $dto->id,
            'plan' => $dto->plan,
        ]);

        $tenant->domains()->create(['domain' => $dto->id . '.api.test']);

        return $tenant;
    }
}
