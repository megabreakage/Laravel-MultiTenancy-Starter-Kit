<?php

declare(strict_types=1);

namespace App\Actions\Continents;

use App\Actions\Concerns\AsAction;
use App\Data\BaseData;
use App\Data\Continents\UpdateContinentData;
use App\Repositories\Contracts\ContinentRepositoryInterface;
use Illuminate\Database\Eloquent\Model;

final class UpdateContinentAction
{
    use AsAction;

    public function __construct(private readonly ContinentRepositoryInterface $continents) {}

    protected function handle(BaseData $dto): Model
    {
        assert($dto instanceof UpdateContinentData);

        $continent = $this->continents->findByIdentifier($dto->identifier);

        return $this->continents->update($continent, [
            'name' => $dto->name,
            'code' => $dto->code !== null ? strtoupper($dto->code) : null,
            'is_active' => $dto->is_active,
        ]);
    }
}
