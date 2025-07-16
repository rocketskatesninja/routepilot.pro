<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Location extends Model
{
    use HasFactory;

    protected $fillable = [
        'client_id',
        'nickname',
        'street_address',
        'street_address_2',
        'city',
        'state',
        'zip_code',
        'photos',
        'access',
        'pool_type',
        'water_type',
        'filter_type',
        'setting',
        'installation',
        'gallons',
        'service_frequency',
        'service_day_1',
        'service_day_2',
        'rate_per_visit',
        'chemicals_included',
        'assigned_technician_id',
        'is_favorite',
        'status',
        'notes',
        // Cleaning tasks
        'vacuumed',
        'brushed',
        'skimmed',
        'cleaned_skimmer_basket',
        'cleaned_pump_basket',
        'cleaned_pool_deck',
        // Maintenance tasks
        'cleaned_filter_cartridge',
        'backwashed_sand_filter',
        'adjusted_water_level',
        'adjusted_auto_fill',
        'adjusted_pump_timer',
        'adjusted_heater',
        'checked_cover',
        'checked_lights',
        'checked_fountain',
        'checked_heater',
        // Other services
        'other_services',
        'other_services_cost',
    ];

    protected $casts = [
        'photos' => 'array',
        'chemicals_included' => 'boolean',
        'is_favorite' => 'boolean',
    ];

    /**
     * Get the location's full address.
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
     * Get the client for this location.
     */
    public function client()
    {
        return $this->belongsTo(Client::class);
    }

    /**
     * Get the assigned technician for this location.
     */
    public function assignedTechnician()
    {
        return $this->belongsTo(User::class, 'assigned_technician_id');
    }

    /**
     * Get the invoices for this location.
     */
    public function invoices()
    {
        return $this->hasMany(Invoice::class);
    }

    /**
     * Get the reports for this location.
     */
    public function reports()
    {
        return $this->hasMany(Report::class);
    }

    /**
     * Get the last service date for this location.
     */
    public function getLastServiceDateAttribute()
    {
        return $this->reports()->latest('service_date')->first()?->service_date;
    }

    /**
     * Get the next service date for this location.
     */
    public function getNextServiceDateAttribute()
    {
        // This would be calculated based on service frequency and last service
        // Implementation would depend on business logic
        return null;
    }

    /**
     * Scope for active locations.
     */
    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    /**
     * Scope for favorite locations.
     */
    public function scopeFavorite($query)
    {
        return $query->where('is_favorite', true);
    }

    /**
     * Scope for locations by access type.
     */
    public function scopeByAccess($query, $access)
    {
        return $query->where('access', $access);
    }

    /**
     * Scope for locations by pool type.
     */
    public function scopeByPoolType($query, $poolType)
    {
        return $query->where('pool_type', $poolType);
    }

    /**
     * Scope for locations by water type.
     */
    public function scopeByWaterType($query, $waterType)
    {
        return $query->where('water_type', $waterType);
    }
} 