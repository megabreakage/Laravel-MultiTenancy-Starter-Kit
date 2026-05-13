<?php

declare(strict_types=1);

use App\Models\User;

it('hashes the password on save', function () {
    $user = new User();
    $user->password = 'plaintext';

    expect(Hash::check('plaintext', $user->password))->toBeTrue();
});

it('extends BaseModel', function () {
    expect(User::class)->toExtend(\App\Models\BaseModel::class);
});
