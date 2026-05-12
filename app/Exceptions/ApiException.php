<?php

declare(strict_types=1);

namespace App\Exceptions;

use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

abstract class ApiException extends Exception
{
    protected int $httpStatus = 500;

    protected string $errorCode = 'INTERNAL_ERROR';

    /** @var array<string, mixed> */
    protected array $details = [];

    public function render(Request $request): JsonResponse
    {
        return new JsonResponse([
            'error' => [
                'code' => $this->errorCode,
                'message' => $this->getMessage(),
                'details' => $this->details ?: null,
            ],
            'meta' => [
                'request_id' => $request->header('X-Request-Id', (string) Str::ulid()),
                'version' => $request->attributes->get('api_version', 'v1'),
            ],
        ], $this->httpStatus);
    }

    /** @param array<string, mixed> $details */
    public function withDetails(array $details): static
    {
        $this->details = $details;

        return $this;
    }
}
