<?php

declare(strict_types=1);

namespace App\Data\Auth;

use App\Data\BaseData;
use Spatie\LaravelData\Attributes\Validation\Email;
use Spatie\LaravelData\Attributes\Validation\Max;
use Spatie\LaravelData\Attributes\Validation\Min;
use Spatie\LaravelData\Attributes\Validation\Required;
use Spatie\LaravelData\Attributes\Validation\StringType;

final class RegisterUserData extends BaseData
{
    public function __construct(
        #[Required, StringType, Max(255)]
        public string $name,
        #[Required, Email, Max(255)]
        public string $email,
        #[Required, StringType, Min(8), Max(255)]
        public string $password,
    ) {}
}
