<?php

declare(strict_types=1);

namespace App\Exceptions;

final class TenantMismatchException extends ApiException
{
    protected int $httpStatus = 403;

    protected string $errorCode = 'TENANT_MISMATCH';
}
