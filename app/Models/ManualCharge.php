<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * ManualCharge — an ad-hoc charge added to a customer's account (e.g. a
 * repair), feeding an invoice line item. Tenant-scoped.
 *
 * @property string $description
 * @property string $amount
 * @property bool $taxable
 */
class ManualCharge extends Model
{
    use BelongsToTenant;

    /** @var list<string> */
    protected $fillable = [
        'customer_id', 'description', 'amount', 'taxable', 'occurred_on', 'invoice_id', 'created_by',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'taxable' => 'boolean',
            'occurred_on' => 'date',
        ];
    }

    /** @return BelongsTo<Customer, $this> */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }
}
