<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;

final class ContinentResource extends BaseApiResource
{
    protected function payload(Request $request): array
    {
        return [
            'name' => $this->resource->name,
            'code' => $this->resource->code,
            'is_active' => (bool) $this->resource->is_active,
        ];
    }
}
