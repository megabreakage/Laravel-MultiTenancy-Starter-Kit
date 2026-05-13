<?php

declare(strict_types=1);

namespace App\Data\Auth;

use App\Data\BaseData;
use Spatie\LaravelData\Attributes\Validation\Email;
use Spatie\LaravelData\Attributes\Validation\Max;
use Spatie\LaravelData\Attributes\Validation\Required;
use Spatie\LaravelData\Attributes\Validation\StringType;

final class SuperAdminLoginData extends BaseData
{
    public function __construct(
        #[Required, Email, Max(255)]
        public string $email,
        #[Required, StringType, Max(255)]
        public string $password,
    ) {}
}
