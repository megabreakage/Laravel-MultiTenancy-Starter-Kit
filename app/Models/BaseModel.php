<?php

declare(strict_types=1);

namespace App\Models;

use App\Support\Concerns\HasAuditTrail;
use App\Support\Concerns\HasUuidIdentifier;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use OwenIt\Auditing\Contracts\Auditable;

abstract class BaseModel extends Model implements Auditable
{
    use HasAuditTrail;
    use HasUuidIdentifier;
    use SoftDeletes;

    protected $guarded = ['id'];
}
