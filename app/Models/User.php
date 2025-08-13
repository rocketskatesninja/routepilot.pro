<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    use HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
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
        'password',
        'appointment_reminders',
        'mailing_list',
        'monthly_billing',
        'service_reports',
        'is_active',

        'current_latitude',
        'current_longitude',
        'location_updated_at',
        'location_sharing_enabled',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<int, string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'email_verified_at' => 'datetime',
        'password' => 'hashed',
        'appointment_reminders' => 'boolean',
        'mailing_list' => 'boolean',
        'monthly_billing' => 'boolean',
        'is_active' => 'boolean',
        'location_updated_at' => 'datetime',
        'location_sharing_enabled' => 'boolean',
    ];

    /**
     * Get the user's full name.
     */
    public function getFullNameAttribute(): string
    {
        return "{$this->first_name} {$this->last_name}";
    }

    /**
     * Check if user has a specific role.
     */
    public function hasRole(string $role): bool
    {
        return $this->role === $role;
    }

    /**
     * Check if user is an admin.
     */
    public function isAdmin(): bool
    {
        return $this->role === 'admin';
    }

    /**
     * Check if user is a technician.
     */
    public function isTechnician(): bool
    {
        return $this->role === 'technician';
    }

    /**
     * Check if user is a customer.
     */
    public function isCustomer(): bool
    {
        return $this->role === 'customer';
    }

    /**
     * Check if user is a client.
     */
    public function isClient(): bool
    {
        return $this->role === 'customer';
    }

    /**
     * Get the locations assigned to this technician.
     */
    public function assignedLocations()
    {
        return $this->hasMany(Location::class, 'assigned_technician_id');
    }

    /**
     * Get the reports created by this user.
     */
    public function reports()
    {
        return $this->hasMany(Report::class, 'technician_id');
    }

    /**
     * Get the invoices created by this user.
     */
    public function invoices()
    {
        return $this->hasMany(Invoice::class, 'technician_id');
    }

    /**
     * Get the activities for this user.
     */
    public function activities()
    {
        return $this->hasMany(Activity::class);
    }

    /**
     * Check if user has current GPS location.
     */
    public function hasCurrentLocation(): bool
    {
        return !is_null($this->current_latitude) && !is_null($this->current_longitude);
    }

    /**
     * Get current GPS coordinates as array.
     */
    public function getCurrentCoordinates(): ?array
    {
        if ($this->hasCurrentLocation()) {
            return [
                'lat' => $this->current_latitude,
                'lng' => $this->current_longitude
            ];
        }
        return null;
    }

    /**
     * Update current GPS location.
     */
    public function updateCurrentLocation(float $latitude, float $longitude): bool
    {
        $this->current_latitude = $latitude;
        $this->current_longitude = $longitude;
        $this->location_updated_at = now();
        return $this->save();
    }

    /**
     * Get location age in minutes.
     */
    public function getLocationAge(): ?int
    {
        if ($this->location_updated_at) {
            return now()->diffInMinutes($this->location_updated_at);
        }
        return null;
    }

    /**
     * Check if location is recent (less than 30 minutes old).
     */
    public function hasRecentLocation(): bool
    {
        $age = $this->getLocationAge();
        return $age !== null && $age < 30;
    }
}
