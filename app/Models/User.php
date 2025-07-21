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
        'maps_provider',
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
}
