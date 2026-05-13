<?php

declare(strict_types=1);

namespace App\Models\Central;

use App\Models\BaseModel;
use Illuminate\Auth\Authenticatable;
use Illuminate\Contracts\Auth\Authenticatable as AuthenticatableContract;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Notifications\Notifiable;
use Laravel\Passport\HasApiTokens;
use OwenIt\Auditing\Contracts\Auditable;
use Spatie\Permission\Traits\HasRoles;

final class SuperAdmin extends BaseModel implements Auditable, AuthenticatableContract
{
    use Authenticatable;
    use HasApiTokens;
    use HasFactory;
    use HasRoles {
        assignRole as traitAssignRole;
    }
    use Notifiable;

    protected static function newFactory(): \Database\Factories\SuperAdminFactory
    {
        return \Database\Factories\SuperAdminFactory::new();
    }

    protected $connection = 'central';

    protected $fillable = [
        'title',
        'first_name',
        'middle_name',
        'last_name',
        'username',
        'email',
        'email_verified_at',
        'country_code',
        'phone',
        'phone_verified_at',
        'password',
        'preferred_timezone',
        'office_location',
        'is_active',
        'avatar',
        'notes',
        'last_login_at',
        'created_by',
        'updated_by',
    ];

    protected $hidden = ['password', 'remember_token'];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'phone_verified_at' => 'datetime',
            'last_login_at' => 'datetime',
            'is_active' => 'boolean',
            'password' => 'hashed',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
            'deleted_at' => 'datetime',
        ];
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(self::class, 'created_by');
    }

    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(self::class, 'updated_by');
    }

    public function getFullNameAttribute(): string
    {
        return trim(implode(' ', array_filter([$this->first_name, $this->middle_name, $this->last_name])));
    }

    /**
     * Ensure only one SuperAdmin can hold the super-admin role.
     * Override assignRole to enforce the constraint before delegating.
     */
    public function assignRole(mixed ...$roles): static
    {
        $roleNames = collect($roles)->flatten()->map(fn ($role) => is_string($role) ? $role : (is_object($role) ? $role->name : $role));

        if ($roleNames->contains('super-admin') && ! $this->hasRole('super-admin')) {
            $existing = self::on('central')->role('super-admin')->where('id', '!=', $this->id)->exists();

            if ($existing) {
                throw new \App\Exceptions\DomainException('A super-admin already exists. Only one super-admin is allowed in the system.');
            }
        }

        return $this->traitAssignRole(...$roles);
    }

    /**
     * Check whether the super-admin role can be assigned (no other user holds it).
     */
    public static function canAssignSuperAdminRole(): bool
    {
        return ! self::on('central')->role('super-admin')->exists();
    }
}
