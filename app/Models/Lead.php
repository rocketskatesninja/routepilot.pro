<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A public-site lead for a tenant: who they are, what they asked for, and which
 * service they picked (if any). `status` and `customer_id` (set when the lead is
 * converted) change only via controlled updates.
 *
 * @property string $name
 * @property string|null $email
 * @property string $source
 * @property string $status
 * @property array<string, mixed>|null $details
 */
class Lead extends Model
{
    use BelongsToTenant;

    /** @var list<string> */
    protected $fillable = ['name', 'email', 'phone', 'message', 'source', 'details', 'service_type_id'];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['details' => 'array'];
    }

    /** @return BelongsTo<ServiceType, $this> */
    public function serviceType(): BelongsTo
    {
        return $this->belongsTo(ServiceType::class);
    }

    /**
     * The customer this lead became.
     *
     * @return BelongsTo<Customer, $this>
     */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }
}
