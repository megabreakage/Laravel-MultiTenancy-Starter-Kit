<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;

final class UserResource extends BaseApiResource
{
    protected function payload(Request $request): array
    {
        return [
            'name' => $this->resource->name,
            'email' => $this->resource->email,
            'email_verified_at' => optional($this->resource->email_verified_at)->toIso8601String(),
        ];
    }
}
