<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;

/**
 * Company — a sub-entity (brand/crew) under a tenant. Tenant-scoped.
 */
class Company extends Model
{
    use BelongsToTenant;

    /** @var list<string> */
    protected $fillable = ['name'];
}
