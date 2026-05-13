<?php

declare(strict_types=1);

namespace App\Actions\Continents;

use App\Actions\Concerns\AsAction;
use App\Data\BaseData;
use App\Data\Continents\DeleteContinentData;
use App\Repositories\Contracts\ContinentRepositoryInterface;
use Illuminate\Database\Eloquent\Model;

final class ForceDeleteContinentAction
{
    use AsAction;

    public function __construct(private readonly ContinentRepositoryInterface $continents) {}

    protected function handle(BaseData $dto): Model
    {
        assert($dto instanceof DeleteContinentData);

        $continent = $this->continents->findByIdentifier($dto->identifier);
        $this->continents->forceDelete($continent);

        return $continent;
    }
}
