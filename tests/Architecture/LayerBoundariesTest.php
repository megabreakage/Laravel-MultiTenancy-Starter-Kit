<?php

declare(strict_types=1);

// Controllers may not import repositories directly — must go through actions/services
arch('controllers do not depend on repositories directly')
    ->expect('App\Http\Controllers')
    ->not->toUse('App\Repositories');

// Repositories may not import services — repositories are the bottom of the stack
arch('repositories do not depend on services')
    ->expect('App\Repositories')
    ->not->toUse('App\Services');

// DTOs are pure data — no Eloquent
arch('data objects do not depend on eloquent')
    ->expect('App\Data')
    ->not->toUse('Illuminate\Database\Eloquent');

// Models in App\Models\Central must declare strict_types
arch('central models declare strict types')
    ->expect('App\Models\Central')
    ->toUseStrictTypes();

// AsAction trait owns DB transactions; controllers must not import the DB facade at all.
// Repositories may use DB::raw(), DB::table(), etc. for complex queries — only DB::transaction
// is prohibited (enforced by content-scanning in tests/Architecture/InvariantsTest.php).
arch('controllers do not directly use DB facade')
    ->expect('App\Http\Controllers')
    ->not->toUse(\Illuminate\Support\Facades\DB::class);
