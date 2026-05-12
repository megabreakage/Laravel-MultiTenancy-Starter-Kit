<?php

declare(strict_types=1);

namespace App\Models;

use App\Support\Concerns\HasAuditTrail;
use App\Support\Concerns\HasUuidIdentifier;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Passport\HasApiTokens;
use OwenIt\Auditing\Contracts\Auditable;

class User extends Authenticatable implements Auditable
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens;
    use HasAuditTrail;
    use HasFactory;
    use HasUuidIdentifier;
    use Notifiable;
    use SoftDeletes;

    protected $guarded = ['id'];

    protected $fillable = [
        'identifier',
        'name',
        'email',
        'password',
        'created_by',
        'updated_by',
    ];

    protected $hidden = ['password', 'remember_token'];

    #[\Override]
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
            'deleted_at' => 'datetime',
        ];
    }
}
