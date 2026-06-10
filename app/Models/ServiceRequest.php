<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * A homeowner request (new service / vacation hold) for the tenant to action.
 *
 * @property string $type
 * @property string $message
 * @property string $status
 * @property Carbon|null $preferred_date
 * @property Carbon|null $resolved_at
 */
class ServiceRequest extends Model
{
    use BelongsToTenant;

    /** @var list<string> */
    protected $fillable = ['customer_id', 'pool_id', 'type', 'message', 'preferred_date'];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'preferred_date' => 'date',
            'resolved_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<Customer, $this> */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    /** @return BelongsTo<Pool, $this> */
    public function pool(): BelongsTo
    {
        return $this->belongsTo(Pool::class);
    }
}
