<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

final class MakeApiResourceCommand extends Command
{
    protected $signature = 'make:api-resource {name : Resource name, e.g. Continent}';

    protected $description = 'Scaffold API resource stack files using base kit patterns.';

    public function handle(): int
    {
        $name = Str::studly((string) $this->argument('name'));
        $plural = Str::pluralStudly($name);

        $targets = [
            app_path("Models/{$name}.php") => $this->renderModel($name),
            app_path("Data/{$plural}/Create{$name}Data.php") => $this->renderCreateData($name, $plural),
            app_path("Data/{$plural}/Update{$name}Data.php") => $this->renderUpdateData($name, $plural),
            app_path("Data/{$plural}/Delete{$name}Data.php") => $this->renderDeleteData($name, $plural),
            app_path("Repositories/Contracts/{$name}RepositoryInterface.php") => $this->renderRepositoryContract($name),
            app_path("Repositories/{$name}Repository.php") => $this->renderRepository($name),
            app_path("Actions/{$plural}/Create{$name}Action.php") => $this->renderCreateAction($name, $plural),
            app_path("Actions/{$plural}/Update{$name}Action.php") => $this->renderUpdateAction($name, $plural),
            app_path("Actions/{$plural}/Delete{$name}Action.php") => $this->renderDeleteAction($name, $plural),
            app_path("Actions/{$plural}/ForceDelete{$name}Action.php") => $this->renderForceDeleteAction($name, $plural),
            app_path("Services/{$name}QueryService.php") => $this->renderQueryService($name),
            app_path("Http/Resources/{$name}Resource.php") => $this->renderResource($name),
            app_path("Http/Controllers/Api/V1/{$name}Controller.php") => $this->renderController($name, $plural),
        ];

        $created = 0;

        foreach ($targets as $path => $content) {
            if (File::exists($path)) {
                $this->line("skip {$path} (exists)");
                continue;
            }

            File::ensureDirectoryExists(dirname($path));
            File::put($path, $content);
            $this->info("created {$path}");
            $created++;
        }

        if ($created === 0) {
            $this->warn('No files created.');
            return self::FAILURE;
        }

        $this->newLine();
        $this->line("Next: bind {$name}RepositoryInterface in RepositoryServiceProvider.");
        $this->line('Next: add routes and a tenant migration for table schema.');

        return self::SUCCESS;
    }

    private function renderModel(string $name): string
    {
        return str_replace(['{{NAME}}'], [$name], <<<'PHP'
<?php

declare(strict_types=1);

namespace App\Models;

final class {{NAME}} extends BaseModel
{
    protected $fillable = [
        'name',
        'code',
        'is_active',
        'created_by',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
            'deleted_at' => 'datetime',
        ];
    }
}
PHP
);
    }

    private function renderCreateData(string $name, string $plural): string
    {
        return str_replace(['{{NAME}}', '{{PLURAL}}'], [$name, $plural], <<<'PHP'
<?php

declare(strict_types=1);

namespace App\Data\{{PLURAL}};

use App\Data\BaseData;
use Spatie\LaravelData\Attributes\Validation\Max;
use Spatie\LaravelData\Attributes\Validation\Min;
use Spatie\LaravelData\Attributes\Validation\Regex;
use Spatie\LaravelData\Attributes\Validation\Required;
use Spatie\LaravelData\Attributes\Validation\StringType;

final class Create{{NAME}}Data extends BaseData
{
    public function __construct(
        #[Required, StringType, Min(2), Max(120)]
        public readonly string $name,
        #[Required, StringType, Regex('/^[A-Z]{2,3}$/')]
        public readonly string $code,
        public readonly bool $is_active = true,
    ) {}
}
PHP
);
    }

    private function renderUpdateData(string $name, string $plural): string
    {
        return str_replace(['{{NAME}}', '{{PLURAL}}'], [$name, $plural], <<<'PHP'
<?php

declare(strict_types=1);

namespace App\Data\{{PLURAL}};

use App\Data\BaseData;
use Spatie\LaravelData\Attributes\Validation\Max;
use Spatie\LaravelData\Attributes\Validation\Min;
use Spatie\LaravelData\Attributes\Validation\Regex;
use Spatie\LaravelData\Attributes\Validation\Required;
use Spatie\LaravelData\Attributes\Validation\StringType;

final class Update{{NAME}}Data extends BaseData
{
    public function __construct(
        #[Required, StringType, Min(36), Max(36)]
        public readonly string $identifier,
        #[StringType, Min(2), Max(120)]
        public readonly ?string $name = null,
        #[StringType, Regex('/^[A-Z]{2,3}$/')]
        public readonly ?string $code = null,
        public readonly ?bool $is_active = null,
    ) {}
}
PHP
);
    }

    private function renderDeleteData(string $name, string $plural): string
    {
        return str_replace(['{{NAME}}', '{{PLURAL}}'], [$name, $plural], <<<'PHP'
<?php

declare(strict_types=1);

namespace App\Data\{{PLURAL}};

use App\Data\BaseData;
use Spatie\LaravelData\Attributes\Validation\Max;
use Spatie\LaravelData\Attributes\Validation\Min;
use Spatie\LaravelData\Attributes\Validation\Required;
use Spatie\LaravelData\Attributes\Validation\StringType;

final class Delete{{NAME}}Data extends BaseData
{
    public function __construct(
        #[Required, StringType, Min(36), Max(36)]
        public readonly string $identifier,
    ) {}
}
PHP
);
    }

    private function renderRepositoryContract(string $name): string
    {
        return str_replace(['{{NAME}}'], [$name], <<<'PHP'
<?php

declare(strict_types=1);

namespace App\Repositories\Contracts;

interface {{NAME}}RepositoryInterface extends BaseRepositoryInterface
{
}
PHP
);
    }

    private function renderRepository(string $name): string
    {
        return str_replace(['{{NAME}}'], [$name], <<<'PHP'
<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Models\{{NAME}};
use App\Repositories\Contracts\{{NAME}}RepositoryInterface;

final class {{NAME}}Repository extends BaseRepository implements {{NAME}}RepositoryInterface
{
    protected function model(): string
    {
        return {{NAME}}::class;
    }

    protected function allowedFilters(): array
    {
        return ['name', 'code', 'is_active'];
    }

    protected function allowedIncludes(): array
    {
        return [];
    }

    protected function allowedSorts(): array
    {
        return ['name', 'code', 'created_at', 'updated_at'];
    }

    protected function defaultSort(): string
    {
        return 'name';
    }
}
PHP
);
    }

    private function renderCreateAction(string $name, string $plural): string
    {
        return str_replace(['{{NAME}}', '{{PLURAL}}'], [$name, $plural], <<<'PHP'
<?php

declare(strict_types=1);

namespace App\Actions\{{PLURAL}};

use App\Actions\Concerns\AsAction;
use App\Data\BaseData;
use App\Data\{{PLURAL}}\Create{{NAME}}Data;
use App\Repositories\Contracts\{{NAME}}RepositoryInterface;
use Illuminate\Database\Eloquent\Model;

final class Create{{NAME}}Action
{
    use AsAction;

    public function __construct(private readonly {{NAME}}RepositoryInterface $repository) {}

    protected function handle(BaseData $dto): Model
    {
        assert($dto instanceof Create{{NAME}}Data);

        return $this->repository->create([
            'name' => $dto->name,
            'code' => strtoupper($dto->code),
            'is_active' => $dto->is_active,
        ]);
    }
}
PHP
);
    }

    private function renderUpdateAction(string $name, string $plural): string
    {
        return str_replace(['{{NAME}}', '{{PLURAL}}'], [$name, $plural], <<<'PHP'
<?php

declare(strict_types=1);

namespace App\Actions\{{PLURAL}};

use App\Actions\Concerns\AsAction;
use App\Data\BaseData;
use App\Data\{{PLURAL}}\Update{{NAME}}Data;
use App\Repositories\Contracts\{{NAME}}RepositoryInterface;
use Illuminate\Database\Eloquent\Model;

final class Update{{NAME}}Action
{
    use AsAction;

    public function __construct(private readonly {{NAME}}RepositoryInterface $repository) {}

    protected function handle(BaseData $dto): Model
    {
        assert($dto instanceof Update{{NAME}}Data);

        $model = $this->repository->findByIdentifier($dto->identifier);

        return $this->repository->update($model, [
            'name' => $dto->name,
            'code' => $dto->code !== null ? strtoupper($dto->code) : null,
            'is_active' => $dto->is_active,
        ]);
    }
}
PHP
);
    }

    private function renderDeleteAction(string $name, string $plural): string
    {
        return str_replace(['{{NAME}}', '{{PLURAL}}'], [$name, $plural], <<<'PHP'
<?php

declare(strict_types=1);

namespace App\Actions\{{PLURAL}};

use App\Actions\Concerns\AsAction;
use App\Data\BaseData;
use App\Data\{{PLURAL}}\Delete{{NAME}}Data;
use App\Repositories\Contracts\{{NAME}}RepositoryInterface;
use Illuminate\Database\Eloquent\Model;

final class Delete{{NAME}}Action
{
    use AsAction;

    public function __construct(private readonly {{NAME}}RepositoryInterface $repository) {}

    protected function handle(BaseData $dto): Model
    {
        assert($dto instanceof Delete{{NAME}}Data);

        $model = $this->repository->findByIdentifier($dto->identifier);
        $this->repository->delete($model);

        return $model;
    }
}
PHP
);
    }

    private function renderForceDeleteAction(string $name, string $plural): string
    {
        return str_replace(['{{NAME}}', '{{PLURAL}}'], [$name, $plural], <<<'PHP'
<?php

declare(strict_types=1);

namespace App\Actions\{{PLURAL}};

use App\Actions\Concerns\AsAction;
use App\Data\BaseData;
use App\Data\{{PLURAL}}\Delete{{NAME}}Data;
use App\Repositories\Contracts\{{NAME}}RepositoryInterface;
use Illuminate\Database\Eloquent\Model;

final class ForceDelete{{NAME}}Action
{
    use AsAction;

    public function __construct(private readonly {{NAME}}RepositoryInterface $repository) {}

    protected function handle(BaseData $dto): Model
    {
        assert($dto instanceof Delete{{NAME}}Data);

        $model = $this->repository->findByIdentifier($dto->identifier);
        $this->repository->forceDelete($model);

        return $model;
    }
}
PHP
);
    }

    private function renderResource(string $name): string
    {
        return str_replace(['{{NAME}}'], [$name], <<<'PHP'
<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;

final class {{NAME}}Resource extends BaseApiResource
{
    protected function payload(Request $request): array
    {
        return [
            'name' => $this->resource->name,
            'code' => $this->resource->code,
            'is_active' => (bool) $this->resource->is_active,
        ];
    }
}
PHP
);
    }

    private function renderQueryService(string $name): string
    {
        return str_replace(['{{NAME}}'], [$name], <<<'PHP'
<?php

declare(strict_types=1);

namespace App\Services;

use App\Repositories\Contracts\{{NAME}}RepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Model;

final class {{NAME}}QueryService
{
    public function __construct(private readonly {{NAME}}RepositoryInterface $repository) {}

    public function paginate(int $perPage = 15): LengthAwarePaginator
    {
        return $this->repository->paginate($perPage);
    }

    public function findByIdentifier(string $identifier): Model
    {
        return $this->repository->findByIdentifier($identifier);
    }
}
PHP
);
    }

    private function renderController(string $name, string $plural): string
    {
        return str_replace(['{{NAME}}', '{{PLURAL}}'], [$name, $plural], <<<'PHP'
<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Actions\{{PLURAL}}\Create{{NAME}}Action;
use App\Actions\{{PLURAL}}\Delete{{NAME}}Action;
use App\Actions\{{PLURAL}}\ForceDelete{{NAME}}Action;
use App\Actions\{{PLURAL}}\Update{{NAME}}Action;
use App\Data\{{PLURAL}}\Create{{NAME}}Data;
use App\Data\{{PLURAL}}\Delete{{NAME}}Data;
use App\Data\{{PLURAL}}\Update{{NAME}}Data;
use App\Http\Resources\{{NAME}}Resource;
use App\Services\{{NAME}}QueryService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class {{NAME}}Controller extends BaseApiController
{
    public function index(Request $request, {{NAME}}QueryService $query): JsonResponse
    {
        return $this->paginated($query->paginate($request->integer('per_page', 15)), {{NAME}}Resource::class);
    }

    public function show(string $identifier, {{NAME}}QueryService $query): JsonResponse
    {
        return $this->success({{NAME}}Resource::make($query->findByIdentifier($identifier))->resolve());
    }

    public function store(Create{{NAME}}Data $dto, Create{{NAME}}Action $action): JsonResponse
    {
        return $this->success({{NAME}}Resource::make($action->execute($dto))->resolve(), Response::HTTP_CREATED);
    }

    public function update(Request $request, string $identifier, Update{{NAME}}Action $action): JsonResponse
    {
        $dto = Update{{NAME}}Data::forUpdate(array_merge($request->all(), ['identifier' => $identifier]));

        return $this->success({{NAME}}Resource::make($action->execute($dto))->resolve());
    }

    public function destroy(string $identifier, Delete{{NAME}}Action $action): JsonResponse
    {
        $action->execute(Delete{{NAME}}Data::forCreation(['identifier' => $identifier]));

        return $this->success(status: Response::HTTP_NO_CONTENT);
    }

    public function forceDestroy(string $identifier, ForceDelete{{NAME}}Action $action): JsonResponse
    {
        $action->execute(Delete{{NAME}}Data::forCreation(['identifier' => $identifier]));

        return $this->success(status: Response::HTTP_NO_CONTENT);
    }
}
PHP
);
    }
}
