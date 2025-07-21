<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Client extends Model
{
    use HasFactory;

    protected $fillable = [
        'first_name',
        'last_name',
        'email',
        'phone',
        'street_address',
        'street_address_2',
        'city',
        'state',
        'zip_code',
        'notes_by_client',
        'notes_by_admin',
        'profile_photo',
        'role',
        'appointment_reminders',
        'mailing_list',
        'monthly_billing',
        'service_reports',
        'status',
        'is_active',
    ];

    protected $casts = [
        'appointment_reminders' => 'boolean',
        'mailing_list' => 'boolean',
        'monthly_billing' => 'boolean',
        'is_active' => 'boolean',
    ];

    /**
     * Get the client's full name.
     */
    public function getFullNameAttribute(): string
    {
        return "{$this->first_name} {$this->last_name}";
    }

    /**
     * Get the client's full address.
     */
    public function getFullAddressAttribute(): string
    {
        $address = $this->street_address;
        if ($this->street_address_2) {
            $address .= ', ' . $this->street_address_2;
        }
        $address .= ', ' . $this->city . ', ' . $this->state . ' ' . $this->zip_code;
        return $address;
    }

    /**
     * Get the locations for this client.
     */
    public function locations()
    {
        return $this->hasMany(Location::class);
    }

    /**
     * Get the invoices for this client.
     */
    public function invoices()
    {
        return $this->hasMany(Invoice::class);
    }

    /**
     * Get the reports for this client.
     */
    public function reports()
    {
        return $this->hasMany(Report::class);
    }

    /**
     * Get the total balance for this client.
     */
    public function getTotalBalanceAttribute(): float
    {
        // Only sum balances for invoices that are not paid and not draft
        return $this->invoices()->whereNotIn('status', ['paid', 'draft'])->sum('balance');
    }

    /**
     * Get the last service date for this client.
     */
    public function getLastServiceDateAttribute()
    {
        return $this->reports()->latest('service_date')->first()?->service_date;
    }

    /**
     * Get the next service date for this client.
     */
    public function getNextServiceDateAttribute()
    {
        // This would be calculated based on service frequency and last service
        // Implementation would depend on business logic
        return null;
    }

    /**
     * Get the last payment date for this client.
     */
    public function getLastPaymentDateAttribute()
    {
        return $this->invoices()->whereNotNull('paid_at')->latest('paid_at')->first()?->paid_at;
    }

    /**
     * Get the total of the last invoice for this client.
     */
    public function getLastInvoiceTotalAttribute()
    {
        return $this->invoices()->latest('service_date')->first()?->total_amount;
    }

    /**
     * Scope for active clients.
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope for clients by status.
     */
    public function scopeByStatus($query, $status)
    {
        return $query->where('status', $status);
    }
} 