<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * InvoiceLineItem — one charge on an invoice (service / chemical / manual /
 * credit), optionally linked back to its source record. Scoped via invoice.
 *
 * @property string $type
 * @property string $description
 * @property string $amount
 */
class InvoiceLineItem extends Model
{
    /** @var list<string> */
    protected $fillable = [
        'type', 'description', 'quantity', 'unit_price', 'amount', 'taxable', 'source_type', 'source_id',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'quantity' => 'decimal:2',
            'unit_price' => 'decimal:2',
            'amount' => 'decimal:2',
            'taxable' => 'boolean',
        ];
    }

    /** @return BelongsTo<Invoice, $this> */
    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }
}
