<?php

declare(strict_types=1);

uses(Tests\TestCase::class)->in('Feature', 'Architecture');
uses(Tests\TestCase::class)->in('Unit');

expect()->extend('toBeStandardSuccessEnvelope', function () {
    return $this
        ->toHaveKey('data')
        ->toHaveKey('meta')
        ->and($this->value['meta'])->toHaveKey('version')
        ->toHaveKey('request_id');
});

expect()->extend('toBeStandardErrorEnvelope', function (?string $code = null) {
    $this->toHaveKey('error')->toHaveKey('meta');
    $this->and($this->value['error'])->toHaveKey('code')->toHaveKey('message');
    if ($code !== null) {
        $this->and($this->value['error']['code'])->toBe($code);
    }

    return $this;
});
