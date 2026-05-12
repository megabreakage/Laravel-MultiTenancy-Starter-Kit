<?php

declare(strict_types=1);

use App\Actions\Concerns\AsAction;
use App\Data\BaseData;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class FakeActionData extends BaseData
{
    public function __construct(public ?string $name = null) {}
}

class FakeActionModel extends Model
{
    public $exists = true;

    public function getKey(): int { return 42; }
}

class FakeAction
{
    use AsAction;

    public bool $handled = false;

    protected function handle(BaseData $dto): Model
    {
        $this->handled = true;

        return new FakeActionModel();
    }
}

it('wraps handle() in DB::transaction and logs around it', function () {
    DB::shouldReceive('transaction')->once()->andReturnUsing(fn ($cb) => $cb());
    Log::shouldReceive('info')->twice();

    $action = new FakeAction();
    $model = $action->execute(FakeActionData::from(['name' => 'x']));

    expect($action->handled)->toBeTrue();
    expect($model)->toBeInstanceOf(Model::class);
});
