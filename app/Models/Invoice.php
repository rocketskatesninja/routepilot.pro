<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Invoice extends Model
{
    use HasFactory;

    protected $fillable = [
        'invoice_number',
        'client_id',
        'location_id',
        'technician_id',
        'service_date',
        'due_date',
        'rate_per_visit',
        'chemicals_cost',
        'chemicals_included',
        'extras_cost',
        'total_amount',
        'balance',
        'status',
        'notes',
        'notification_sent',
        'paid_at',
        'recurring_profile_id',
    ];

    protected $casts = [
        'service_date' => 'date',
        'due_date' => 'date',
        'chemicals_included' => 'boolean',
        'notification_sent' => 'boolean',
        'paid_at' => 'datetime',
    ];

    /**
     * Get the client for this invoice.
     */
    public function client()
    {
        return $this->belongsTo(Client::class);
    }

    /**
     * Get the location for this invoice.
     */
    public function location()
    {
        return $this->belongsTo(Location::class);
    }

    /**
     * Get the technician for this invoice.
     */
    public function technician()
    {
        return $this->belongsTo(User::class, 'technician_id');
    }

    /**
     * Get the recurring billing profile for this invoice.
     */
    public function recurringProfile()
    {
        return $this->belongsTo(RecurringBillingProfile::class, 'recurring_profile_id');
    }

    /**
     * Check if invoice is paid.
     */
    public function isPaid(): bool
    {
        return $this->status === 'paid';
    }

    /**
     * Check if invoice is overdue.
     */
    public function isOverdue(): bool
    {
        return $this->due_date->isPast() && !$this->isPaid();
    }

    /**
     * Get the total amount including balance.
     */
    public function getTotalWithBalanceAttribute(): float
    {
        return $this->total_amount + $this->balance;
    }

    /**
     * Scope for paid invoices.
     */
    public function scopePaid($query)
    {
        return $query->where('status', 'paid');
    }

    /**
     * Scope for unpaid invoices.
     */
    public function scopeUnpaid($query)
    {
        return $query->where('status', '!=', 'paid');
    }

    /**
     * Scope for overdue invoices.
     */
    public function scopeOverdue($query)
    {
        return $query->where('due_date', '<', now())->where('status', '!=', 'paid');
    }

    /**
     * Scope for invoices by status.
     */
    public function scopeByStatus($query, $status)
    {
        return $query->where('status', $status);
    }

    /**
     * Scope for invoices by date range.
     */
    public function scopeByDateRange($query, $startDate, $endDate)
    {
        return $query->whereBetween('service_date', [$startDate, $endDate]);
    }
} 