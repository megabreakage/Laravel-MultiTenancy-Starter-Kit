<?php

declare(strict_types=1);

namespace App\Actions\Continents;

use App\Actions\Concerns\AsAction;
use App\Data\BaseData;
use App\Data\Continents\CreateContinentData;
use App\Repositories\Contracts\ContinentRepositoryInterface;
use Illuminate\Database\Eloquent\Model;

final class CreateContinentAction
{
    use AsAction;

    public function __construct(private readonly ContinentRepositoryInterface $continents) {}

    protected function handle(BaseData $dto): Model
    {
        assert($dto instanceof CreateContinentData);

        return $this->continents->create([
            'name' => $dto->name,
            'code' => strtoupper($dto->code),
            'is_active' => $dto->is_active,
        ]);
    }
}
