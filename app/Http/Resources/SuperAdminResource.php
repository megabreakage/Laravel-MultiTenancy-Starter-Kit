<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;

final class SuperAdminResource extends BaseApiResource
{
    protected function payload(Request $request): array
    {
        return [
            'identifier' => $this->resource->identifier,
            'first_name' => $this->resource->first_name,
            'middle_name' => $this->resource->middle_name,
            'last_name' => $this->resource->last_name,
            'full_name' => $this->resource->full_name,
            'username' => $this->resource->username,
            'email' => $this->resource->email,
            'email_verified_at' => optional($this->resource->email_verified_at)->toIso8601String(),
            'country_code' => $this->resource->country_code,
            'phone' => $this->resource->phone,
            'phone_verified_at' => optional($this->resource->phone_verified_at)->toIso8601String(),
            'preferred_timezone' => $this->resource->preferred_timezone,
            'office_location' => $this->resource->office_location,
            'is_active' => $this->resource->is_active,
            'avatar' => $this->resource->avatar,
            'last_login_at' => optional($this->resource->last_login_at)->toIso8601String(),
            'roles' => $this->resource->getRoleNames(),
            'created_at' => optional($this->resource->created_at)->toIso8601String(),
            'updated_at' => optional($this->resource->updated_at)->toIso8601String(),
        ];
    }
}
