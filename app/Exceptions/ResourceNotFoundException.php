<?php

declare(strict_types=1);

namespace App\Exceptions;

final class ResourceNotFoundException extends ApiException
{
    protected int $httpStatus = 404;

    protected string $errorCode = 'RESOURCE_NOT_FOUND';
}
