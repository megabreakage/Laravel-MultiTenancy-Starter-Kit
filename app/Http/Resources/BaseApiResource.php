<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

abstract class BaseApiResource extends JsonResource
{
    final public function toArray(Request $request): array
    {
        $payload = $this->payload($request);

        return array_merge(
            ['id' => $this->resource->identifier ?? null],
            $payload,
            [
                'created_at' => optional($this->resource->created_at)->toIso8601String(),
                'updated_at' => optional($this->resource->updated_at)->toIso8601String(),
            ],
        );
    }

    abstract protected function payload(Request $request): array;
}
