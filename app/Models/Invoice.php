<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * Invoice — a customer's branded statement for a period. Tenant-scoped.
 *
 * @property string $number
 * @property string $status
 * @property string $total
 * @property string $amount_paid
 * @property Carbon|null $issued_at
 * @property Carbon|null $due_at
 * @property Carbon|null $period_start
 * @property Carbon|null $period_end
 */
class Invoice extends Model
{
    use BelongsToTenant;

    /** @var list<string> */
    protected $fillable = [
        'customer_id', 'number', 'status', 'period_start', 'period_end',
        'subtotal', 'tax', 'total', 'amount_paid', 'issued_at', 'due_at', 'pdf_path', 'notes',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'period_start' => 'date',
            'period_end' => 'date',
            'subtotal' => 'decimal:2',
            'tax' => 'decimal:2',
            'total' => 'decimal:2',
            'amount_paid' => 'decimal:2',
            'issued_at' => 'datetime',
            'due_at' => 'date',
        ];
    }

    /** @return BelongsTo<Customer, $this> */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    /**
     * The charges billed on this invoice (its lines).
     *
     * @return HasMany<Charge, $this>
     */
    public function charges(): HasMany
    {
        return $this->hasMany(Charge::class)->orderBy('occurred_on')->orderBy('id');
    }

    /** @return HasMany<Payment, $this> */
    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    /** Outstanding amount on this invoice. */
    public function balance(): float
    {
        return round((float) $this->total - (float) $this->amount_paid, 2);
    }
}
