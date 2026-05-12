<?php

declare(strict_types=1);

use App\Models\BaseModel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;

uses(RefreshDatabase::class);

beforeEach(function () {
    config()->set('audit.enabled', false);

    Schema::connection('central')->create('test_widgets', function ($t) {
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
    Schema::connection('central')->dropIfExists('test_widgets');
});

it('auto-populates identifier as a uuid on creation', function () {
    $widget = TestWidget::create(['name' => 'Foo']);
    expect($widget->identifier)->toMatch('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/');
});

class TestWidget extends BaseModel
{
    protected $table = 'test_widgets';

    protected $connection = 'central';

    protected $fillable = ['name'];
}
