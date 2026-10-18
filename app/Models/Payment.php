<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Payment — money received from a customer (in-app card via Stripe, or a
 * recorded cash/check). Tenant-scoped.
 *
 * @property string $amount
 * @property string $status
 * @property string $method
 * @property string|null $reference a check number or transfer reference the office noted
 * @property string|null $stripe_payment_intent_id Stripe's id for a card payment
 * @property int|null $invoice_id
 * @property Carbon|null $paid_at
 */
class Payment extends Model
{
    use BelongsToTenant;

    /** @var list<string> */
    protected $fillable = [
        'customer_id', 'invoice_id', 'amount', 'status', 'method', 'reference',
        'stripe_payment_intent_id', 'failure_reason', 'paid_at', 'recorded_by',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['amount' => 'decimal:2', 'paid_at' => 'datetime'];
    }

    /**
     * The staff member who recorded it (null for card payments made online).
     *
     * @return BelongsTo<User, $this>
     */
    public function recorder(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }

    /** @return BelongsTo<Customer, $this> */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    /** @return BelongsTo<Invoice, $this> */
    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }
}
