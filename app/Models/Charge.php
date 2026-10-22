<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * One line on a customer's account: a completed visit, a one-off charge, or a
 * credit (negative). Amount and tax are fixed when it's created and never
 * re-derived. A charge is never deleted once a customer could have seen it —
 * it's voided, with a reason. invoice_id is set once it's been billed.
 *
 * @property int|null $work_order_id the work order it billed
 * @property string $kind
 * @property string $description
 * @property string $amount
 * @property bool $taxable
 * @property string $tax
 * @property Carbon $occurred_on
 * @property int|null $invoice_id
 * @property Carbon|null $voided_at
 * @property string|null $void_reason
 */
class Charge extends Model
{
    use BelongsToTenant;

    /** @var list<string> */
    protected $fillable = [
        'customer_id', 'pool_id', 'service_visit_id', 'work_order_id', 'kind', 'description', 'amount',
        'taxable', 'tax', 'occurred_on', 'invoice_id', 'created_by',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'tax' => 'decimal:2',
            'taxable' => 'boolean',
            'occurred_on' => 'date',
            'voided_at' => 'datetime',
        ];
    }

    /** Amount plus its tax. */
    public function total(): float
    {
        return round((float) $this->amount + (float) $this->tax, 2);
    }

    /**
     * Charges that count (not voided).
     *
     * @param  Builder<self>  $query
     */
    public function scopeActive(Builder $query): void
    {
        $query->whereNull('voided_at');
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

    /** @return BelongsTo<ServiceVisit, $this> */
    public function serviceVisit(): BelongsTo
    {
        return $this->belongsTo(ServiceVisit::class);
    }

    /** @return BelongsTo<WorkOrder, $this> */
    public function workOrder(): BelongsTo
    {
        return $this->belongsTo(WorkOrder::class);
    }

    /** @return BelongsTo<Invoice, $this> */
    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }
}
