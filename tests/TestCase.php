<?php

declare(strict_types=1);

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $token = (int) (getenv('TEST_TOKEN') ?: 1);

        config()->set('database.connections.central.database', "api_kit_test_central_w{$token}");
        config()->set('database.redis.default.database', 10 + $token);
    }
}
