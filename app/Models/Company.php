<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Company — a sub-entity (brand/crew) under a tenant. Tenant-scoped.
 */
class Company extends Model
{
    use BelongsToTenant;

    /** @var list<string> */
    protected $fillable = ['name'];

    /** @return BelongsTo<Tenant, $this> */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }
}
