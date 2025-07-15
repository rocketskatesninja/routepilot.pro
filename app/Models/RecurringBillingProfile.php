<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Carbon\Carbon;

class RecurringBillingProfile extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'description',
        'client_id',
        'location_id',
        'technician_id',
        'rate_per_visit',
        'chemicals_cost',
        'chemicals_included',
        'extras_cost',
        'frequency',
        'frequency_value',
        'start_date',
        'end_date',
        'day_of_week',
        'day_of_month',
        'status',
        'auto_generate_invoices',
        'advance_notice_days',
        'next_billing_date',
        'invoices_generated',
        'total_amount_generated',
    ];

    protected $casts = [
        'start_date' => 'date',
        'end_date' => 'date',
        'next_billing_date' => 'date',
        'chemicals_included' => 'boolean',
        'auto_generate_invoices' => 'boolean',
        'rate_per_visit' => 'decimal:2',
        'chemicals_cost' => 'decimal:2',
        'extras_cost' => 'decimal:2',
        'total_amount_generated' => 'decimal:2',
    ];

    /**
     * Get the client for this profile.
     */
    public function client()
    {
        return $this->belongsTo(Client::class);
    }

    /**
     * Get the location for this profile.
     */
    public function location()
    {
        return $this->belongsTo(Location::class);
    }

    /**
     * Get the technician for this profile.
     */
    public function technician()
    {
        return $this->belongsTo(User::class, 'technician_id');
    }

    /**
     * Get the invoices generated from this profile.
     */
    public function invoices()
    {
        return $this->hasMany(Invoice::class, 'recurring_profile_id');
    }

    /**
     * Check if profile is active.
     */
    public function isActive(): bool
    {
        return $this->status === 'active';
    }

    /**
     * Check if profile is paused.
     */
    public function isPaused(): bool
    {
        return $this->status === 'paused';
    }

    /**
     * Check if profile is cancelled.
     */
    public function isCancelled(): bool
    {
        return $this->status === 'cancelled';
    }

    /**
     * Check if profile has ended.
     */
    public function hasEnded(): bool
    {
        return $this->end_date && $this->end_date->isPast();
    }

    /**
     * Calculate the total amount for this profile.
     */
    public function getTotalAmountAttribute(): float
    {
        $total = $this->rate_per_visit;
        if ($this->chemicals_included) {
            $total += $this->chemicals_cost;
        }
        $total += $this->extras_cost;
        return $total;
    }

    /**
     * Calculate next billing date based on frequency.
     */
    public function calculateNextBillingDate(): Carbon
    {
        $currentDate = $this->next_billing_date ?? $this->start_date;
        
        switch ($this->frequency) {
            case 'weekly':
                return $currentDate->copy()->addWeek();
            case 'biweekly':
                return $currentDate->copy()->addWeeks(2);
            case 'monthly':
                return $currentDate->copy()->addMonth();
            case 'quarterly':
                return $currentDate->copy()->addMonths(3);
            case 'custom':
                return $currentDate->copy()->addDays($this->frequency_value);
            default:
                return $currentDate->copy()->addWeek();
        }
    }

    /**
     * Generate an invoice from this profile.
     */
    public function generateInvoice(): Invoice
    {
        $serviceDate = $this->next_billing_date;
        $dueDate = $serviceDate->copy()->addDays(30); // 30 days from service date

        // Generate invoice number
        $lastInvoice = Invoice::orderBy('id', 'desc')->first();
        $invoiceNumber = $lastInvoice ? 'INV-' . str_pad($lastInvoice->id + 1, 6, '0', STR_PAD_LEFT) : 'INV-000001';

        $invoice = Invoice::create([
            'invoice_number' => $invoiceNumber,
            'client_id' => $this->client_id,
            'location_id' => $this->location_id,
            'technician_id' => $this->technician_id,
            'service_date' => $serviceDate,
            'due_date' => $dueDate,
            'rate_per_visit' => $this->rate_per_visit,
            'chemicals_cost' => $this->chemicals_cost,
            'chemicals_included' => $this->chemicals_included,
            'extras_cost' => $this->extras_cost,
            'total_amount' => $this->total_amount,
            'balance' => $this->total_amount,
            'status' => 'sent',
            'recurring_profile_id' => $this->id,
        ]);

        // Update profile statistics
        $this->increment('invoices_generated');
        $this->increment('total_amount_generated', $this->total_amount);
        
        // Calculate next billing date
        $this->next_billing_date = $this->calculateNextBillingDate();
        $this->save();

        return $invoice;
    }

    /**
     * Check if it's time to generate the next invoice.
     */
    public function shouldGenerateInvoice(): bool
    {
        if (!$this->isActive() || $this->hasEnded()) {
            return false;
        }

        if (!$this->auto_generate_invoices) {
            return false;
        }

        $nextBillingDate = $this->next_billing_date ?? $this->start_date;
        $advanceDate = now()->addDays($this->advance_notice_days);

        return $nextBillingDate->lte($advanceDate);
    }

    /**
     * Scope for active profiles.
     */
    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    /**
     * Scope for profiles that need invoice generation.
     */
    public function scopeNeedsInvoiceGeneration($query)
    {
        return $query->where('status', 'active')
                    ->where('auto_generate_invoices', true)
                    ->where(function ($q) {
                        $q->whereNull('next_billing_date')
                          ->orWhere('next_billing_date', '<=', now()->addDays(7));
                    });
    }

    /**
     * Scope for profiles by client.
     */
    public function scopeByClient($query, $clientId)
    {
        return $query->where('client_id', $clientId);
    }

    /**
     * Scope for profiles by status.
     */
    public function scopeByStatus($query, $status)
    {
        return $query->where('status', $status);
    }
}
