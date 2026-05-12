<?php

declare(strict_types=1);

namespace App\Support\Concerns;

use Illuminate\Support\Str;

trait HasUuidIdentifier
{
    protected static function bootHasUuidIdentifier(): void
    {
        static::creating(function (self $model) {
            if (empty($model->identifier)) {
                $model->identifier = (string) Str::uuid();
            }
            if (auth()->check()) {
                $model->created_by ??= auth()->id();
                $model->updated_by ??= auth()->id();
            }
        });

        static::updating(function (self $model) {
            if (auth()->check()) {
                $model->updated_by = auth()->id();
            }
        });
    }
}
