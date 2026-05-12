<?php

declare(strict_types=1);

namespace App\Actions\Concerns;

use App\Data\BaseData;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

trait AsAction
{
    public function execute(BaseData $dto): Model
    {
        $actingUserId = $this->resolveActingUserId();

        Log::info(static::class . ' starting', [
            'acting_user_id' => $actingUserId,
            'dto' => $dto->toArray(),
        ]);

        $model = DB::transaction(fn () => $this->handle($dto));

        Log::info(static::class . ' completed', [
            'model_key' => $model->getKey(),
        ]);

        return $model;
    }

    protected function resolveActingUserId(): ?int
    {
        if (auth()->check()) {
            return (int) auth()->id();
        }

        return null;
    }

    abstract protected function handle(BaseData $dto): Model;
}
