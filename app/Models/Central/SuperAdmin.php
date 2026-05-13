<?php

declare(strict_types=1);

namespace App\Models\Central;

use App\Models\BaseModel;
use Illuminate\Auth\Authenticatable;
use Illuminate\Contracts\Auth\Authenticatable as AuthenticatableContract;
use Illuminate\Notifications\Notifiable;
use Laravel\Passport\HasApiTokens;

final class SuperAdmin extends BaseModel implements AuthenticatableContract
{
    use Authenticatable;
    use HasApiTokens;
    use Notifiable;

    protected $connection = 'central';

    protected $fillable = ['name', 'email', 'password', 'email_verified_at'];

    protected $hidden = ['password', 'remember_token'];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }
}
