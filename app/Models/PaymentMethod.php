<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * PaymentMethod — a customer's saved card (Stripe), enabling autopay.
 * Only non-sensitive descriptors are stored. Tenant-scoped.
 *
 * @property string|null $brand
 * @property string|null $last4
 * @property bool $is_default
 */
class PaymentMethod extends Model
{
    use BelongsToTenant;

    /** @var list<string> */
    protected $fillable = [
        'customer_id', 'stripe_payment_method_id', 'brand', 'last4', 'exp_month', 'exp_year', 'is_default',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['is_default' => 'boolean'];
    }

    /** @return BelongsTo<Customer, $this> */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }
}
