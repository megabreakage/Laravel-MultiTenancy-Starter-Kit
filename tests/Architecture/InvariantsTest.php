<?php

declare(strict_types=1);

use Symfony\Component\Finder\Finder;

it('DB::transaction lives only in AsAction and Services', function (): void {
    $finder = new Finder()
        ->in(app_path())
        ->files()
        ->name('*.php')
        ->notPath('Actions/Concerns/AsAction.php')
        ->notPath('Services');

    $offenders = [];
    foreach ($finder as $file) {
        if (preg_match('/DB::transaction\s*\(/', $file->getContents())) {
            $offenders[] = $file->getRelativePathname();
        }
    }

    expect($offenders)->toBeEmpty(
        'DB::transaction() found outside AsAction/Services: ' . implode(', ', $offenders)
    );
});

it('no manual transaction management anywhere in app', function (): void {
    $finder = new Finder()
        ->in(app_path())
        ->files()
        ->name('*.php');

    $offenders = [];
    foreach ($finder as $file) {
        if (preg_match('/DB::(beginTransaction|commit(?!ted)|rollBack)\s*\(/', $file->getContents())) {
            $offenders[] = $file->getRelativePathname();
        }
    }

    expect($offenders)->toBeEmpty(
        'Manual transaction management found: ' . implode(', ', $offenders)
    );
});

it('controllers do not call response()->json directly', function (): void {
    $finder = new Finder()
        ->in(app_path('Http/Controllers'))
        ->files()
        ->name('*.php');

    $offenders = [];
    foreach ($finder as $file) {
        if (preg_match('/response\s*\(\s*\)\s*->\s*json\s*\(/', $file->getContents())) {
            $offenders[] = $file->getRelativePathname();
        }
    }

    expect($offenders)->toBeEmpty(
        'Controllers calling response()->json() directly: ' . implode(', ', $offenders)
    );
});
