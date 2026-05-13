<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Actions\Continents\CreateContinentAction;
use App\Actions\Continents\DeleteContinentAction;
use App\Actions\Continents\ForceDeleteContinentAction;
use App\Actions\Continents\UpdateContinentAction;
use App\Data\Continents\CreateContinentData;
use App\Data\Continents\DeleteContinentData;
use App\Data\Continents\UpdateContinentData;
use App\Http\Resources\ContinentResource;
use App\Services\ContinentQueryService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class ContinentController extends BaseApiController
{
    public function index(Request $request, ContinentQueryService $query): JsonResponse
    {
        return $this->paginated(
            $query->paginate($request->integer('per_page', 15)),
            ContinentResource::class,
        );
    }

    public function show(string $continent, ContinentQueryService $query): JsonResponse
    {
        $model = $query->findByIdentifier($continent);

        return $this->success(ContinentResource::make($model)->resolve());
    }

    public function store(CreateContinentData $dto, CreateContinentAction $action): JsonResponse
    {
        $model = $action->execute($dto);

        return $this->success(ContinentResource::make($model)->resolve(), Response::HTTP_CREATED);
    }

    public function update(Request $request, string $continent, UpdateContinentAction $action): JsonResponse
    {
        $dto = UpdateContinentData::forUpdate(array_merge($request->all(), ['identifier' => $continent]));
        $model = $action->execute($dto);

        return $this->success(ContinentResource::make($model)->resolve());
    }

    public function destroy(string $continent, DeleteContinentAction $action): JsonResponse
    {
        $action->execute(DeleteContinentData::forCreation(['identifier' => $continent]));

        return $this->success(status: Response::HTTP_NO_CONTENT);
    }

    public function forceDestroy(string $continent, ForceDeleteContinentAction $action): JsonResponse
    {
        $action->execute(DeleteContinentData::forCreation(['identifier' => $continent]));

        return $this->success(status: Response::HTTP_NO_CONTENT);
    }
}
