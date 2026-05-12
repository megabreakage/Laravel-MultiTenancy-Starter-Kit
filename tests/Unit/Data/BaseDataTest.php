<?php

declare(strict_types=1);

use App\Data\BaseData;

class TestUserData extends BaseData
{
    public function __construct(
        public ?string $name = null,
        public ?string $email = null,
    ) {}
}

it('forCreation hydrates all fields', function () {
    $data = TestUserData::forCreation(['name' => 'Alice', 'email' => 'a@b.com']);
    expect($data->name)->toBe('Alice')->and($data->email)->toBe('a@b.com');
});

it('forUpdate strips nulls', function () {
    $data = TestUserData::forUpdate(['name' => 'Alice', 'email' => null]);
    expect($data->toArray())->toBe(['name' => 'Alice']);
});
