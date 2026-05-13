<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;

final class TenantResource extends BaseApiResource
{
    final public function toArray(Request $request): array
    {
        return [
            'id' => $this->resource->id,
            'plan' => $this->resource->plan,
            'status' => $this->resource->status,
            'created_at' => optional($this->resource->created_at)->toIso8601String(),
            'updated_at' => optional($this->resource->updated_at)->toIso8601String(),
        ];
    }

    protected function payload(Request $request): array
    {
        return [];
    }
}
