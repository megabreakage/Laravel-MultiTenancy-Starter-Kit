<?php

declare(strict_types=1);

namespace App\Exceptions;

final class TenantResolutionException extends ApiException
{
    protected int $httpStatus = 404;

    protected string $errorCode = 'TENANT_NOT_FOUND';
}
