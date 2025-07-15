<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Report extends Model
{
    use HasFactory;

    protected $fillable = [
        'client_id',
        'location_id',
        'technician_id',
        'service_date',
        'service_time',
        'pool_gallons',
        'fac',
        'cc',
        'ph',
        'alkalinity',
        'calcium',
        'salt',
        'cya',
        'tds',
        'vacuumed',
        'brushed',
        'skimmed',
        'cleaned_skimmer_basket',
        'cleaned_pump_basket',
        'cleaned_pool_deck',
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
        'chemicals_used',
        'chemicals_cost',
        'other_services',
        'other_services_cost',
        'total_cost',
        'notes_to_client',
        'notes_to_admin',
        'photos',
    ];

    protected $casts = [
        'service_date' => 'date',
        'service_time' => 'datetime',
        'vacuumed' => 'boolean',
        'brushed' => 'boolean',
        'skimmed' => 'boolean',
        'cleaned_skimmer_basket' => 'boolean',
        'cleaned_pump_basket' => 'boolean',
        'cleaned_pool_deck' => 'boolean',
        'cleaned_filter_cartridge' => 'boolean',
        'backwashed_sand_filter' => 'boolean',
        'adjusted_water_level' => 'boolean',
        'adjusted_auto_fill' => 'boolean',
        'adjusted_pump_timer' => 'boolean',
        'adjusted_heater' => 'boolean',
        'checked_cover' => 'boolean',
        'checked_lights' => 'boolean',
        'checked_fountain' => 'boolean',
        'checked_heater' => 'boolean',
        'chemicals_used' => 'array',
        'other_services' => 'array',
        'photos' => 'array',
    ];

    /**
     * Get the client for this report.
     */
    public function client()
    {
        return $this->belongsTo(Client::class);
    }

    /**
     * Get the location for this report.
     */
    public function location()
    {
        return $this->belongsTo(Location::class);
    }

    /**
     * Get the technician for this report.
     */
    public function technician()
    {
        return $this->belongsTo(User::class, 'technician_id');
    }

    /**
     * Get all cleaning tasks as an array.
     */
    public function getCleaningTasksAttribute(): array
    {
        return [
            'vacuumed' => $this->vacuumed,
            'brushed' => $this->brushed,
            'skimmed' => $this->skimmed,
            'cleaned_skimmer_basket' => $this->cleaned_skimmer_basket,
            'cleaned_pump_basket' => $this->cleaned_pump_basket,
            'cleaned_pool_deck' => $this->cleaned_pool_deck,
        ];
    }

    /**
     * Get all maintenance tasks as an array.
     */
    public function getMaintenanceTasksAttribute(): array
    {
        return [
            'cleaned_filter_cartridge' => $this->cleaned_filter_cartridge,
            'backwashed_sand_filter' => $this->backwashed_sand_filter,
            'adjusted_water_level' => $this->adjusted_water_level,
            'adjusted_auto_fill' => $this->adjusted_auto_fill,
            'adjusted_pump_timer' => $this->adjusted_pump_timer,
            'adjusted_heater' => $this->adjusted_heater,
            'checked_cover' => $this->checked_cover,
            'checked_lights' => $this->checked_lights,
            'checked_fountain' => $this->checked_fountain,
            'checked_heater' => $this->checked_heater,
        ];
    }

    /**
     * Get chemistry readings as an array.
     */
    public function getChemistryReadingsAttribute(): array
    {
        return [
            'fac' => $this->fac,
            'cc' => $this->cc,
            'ph' => $this->ph,
            'alkalinity' => $this->alkalinity,
            'calcium' => $this->calcium,
            'salt' => $this->salt,
            'cya' => $this->cya,
            'tds' => $this->tds,
        ];
    }

    /**
     * Scope for reports by date range.
     */
    public function scopeByDateRange($query, $startDate, $endDate)
    {
        return $query->whereBetween('service_date', [$startDate, $endDate]);
    }

    /**
     * Scope for reports by technician.
     */
    public function scopeByTechnician($query, $technicianId)
    {
        return $query->where('technician_id', $technicianId);
    }

    /**
     * Scope for reports by client.
     */
    public function scopeByClient($query, $clientId)
    {
        return $query->where('client_id', $clientId);
    }

    /**
     * Scope for reports by location.
     */
    public function scopeByLocation($query, $locationId)
    {
        return $query->where('location_id', $locationId);
    }
} 