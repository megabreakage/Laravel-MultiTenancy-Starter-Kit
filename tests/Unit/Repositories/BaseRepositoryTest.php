<?php

declare(strict_types=1);

use App\Models\BaseModel;
use App\Repositories\BaseRepository;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;

uses(RefreshDatabase::class);

beforeEach(function () {
    config()->set('audit.enabled', false);

    Schema::connection('central')->create('widgets', function ($t) {
        $t->id();
        $t->string('identifier')->unique();
        $t->string('name');
        $t->unsignedBigInteger('created_by')->nullable();
        $t->unsignedBigInteger('updated_by')->nullable();
        $t->timestamps();
        $t->softDeletes();
    });
});

afterEach(function () {
    Schema::connection('central')->dropIfExists('widgets');
});

class Widget extends BaseModel
{
    protected $table = 'widgets';

    protected $connection = 'central';

    protected $fillable = ['name'];
}

class WidgetRepository extends BaseRepository
{
    protected function model(): string { return Widget::class; }

    protected function allowedFilters(): array { return ['name']; }

    protected function allowedIncludes(): array { return []; }

    protected function allowedSorts(): array { return ['name', 'created_at']; }
}

it('creates, finds, browses, paginates, deletes, force-deletes', function () {
    $repo = new WidgetRepository();

    $w = $repo->create(['name' => 'Alpha']);
    expect($w->identifier)->not->toBeEmpty();

    $found = $repo->findByIdentifier($w->identifier);
    expect($found->id)->toBe($w->id);

    $repo->create(['name' => 'Beta']);
    $repo->create(['name' => 'Gamma']);

    expect($repo->browseAll())->toHaveCount(3);
    expect($repo->paginate(2)->total())->toBe(3);

    $repo->delete($w);
    expect(Widget::withTrashed()->find($w->id)->trashed())->toBeTrue();

    $repo->forceDelete($w);
    expect(Widget::withTrashed()->find($w->id))->toBeNull();
});
