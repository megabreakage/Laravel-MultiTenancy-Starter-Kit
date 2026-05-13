<?php

declare(strict_types=1);

namespace App\Models;

use App\Support\Concerns\HasAuditTrail;
use App\Support\Concerns\HasUuidIdentifier;
use Auth;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;
use OwenIt\Auditing\Contracts\Auditable;

abstract class BaseModel extends Model implements Auditable
{
    use HasAuditTrail;
    use HasUuidIdentifier;
    use SoftDeletes;

    protected $guarded = ['id'];

    protected $fillable = [
        'name',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];

    /**
     * Boot the model.
     */
    protected static function booted(): void
    {

        static::creating(function ($model) {
            if (is_null($model->identifier)) {
                $model->identifier = (string) Str::uuid();
            }

            if (is_null($model->created_by) && Auth::check()) {
                $model->created_by = Auth::id();
            }
        });

        static::updating(function ($model) {
            if (Auth::check()) {
                $model->updated_by = Auth::id();
            }
        });

        static::restoring(function ($model) {
            if (Auth::check()) {
                $model->updated_by = Auth::id();
            }
        });
    }

    /**
     * Get the route key name for route model binding.
     */
    public function getRouteKeyName(): string
    {
        return 'identifier';
    }

    /**
     * Get the user who created the record.
     */
    public function createdBy()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Get the user who last updated the record.
     */
    public function updatedBy()
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    /**
     * Get the user who created the record.
     */
    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Get the user who last updated the record.
     */
    public function updater()
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    /**
     * Scope to get records created by a specific user.
     */
    public function scopeCreatedBy($query, $userId)
    {
        return $query->where('created_by', $userId);
    }

    /**
     * Scope to get records updated by a specific user.
     */
    public function scopeUpdatedBy($query, $userId)
    {
        return $query->where('updated_by', $userId);
    }
}
