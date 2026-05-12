<?php

declare(strict_types=1);

namespace App\Exceptions;

class DomainException extends ApiException
{
    protected int $httpStatus = 422;

    protected string $errorCode = 'DOMAIN_ERROR';
}
