<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\HasPersonName;
use Database\Factories\UserFactory;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Spatie\Permission\Traits\HasRoles;

/**
 * User — staff (super_admin / tenant_admin / agent) and customers.
 *
 * `role` is the SINGLE source of truth for coarse role; Spatie's HasRoles
 * is layered on for granular per-agent `manage_*` permissions only (never
 * as a second role system). NOTE: User does NOT use the global TenantScope —
 * route-model binding for User must be tenant-checked explicitly (see
 * ResolveTenant) to avoid cross-tenant access on non-scoped bindings.
 */
class User extends Authenticatable implements MustVerifyEmail
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, HasPersonName, HasRoles, Notifiable, SoftDeletes;

    /**
     * Privilege/identity fields (tenant_id, role, is_active, google_id,
     * email_verified_at) are intentionally NOT fillable — a careless
     * User::create($request->all()) must not let a tenant_admin escalate
     * to super_admin or jump tenants. Set them via forceFill() at
     * controlled call sites only.
     *
     * @var list<string>
     */
    protected $fillable = [
        'first_name', 'last_name', 'email', 'password', 'phone',
        'address_line1', 'address_line2', 'city', 'state', 'zip',
        'avatar_path', 'map_color', 'admin_notes', 'last_login_at',
        'theme', 'font_scale', 'dashboard_layout', 'sidebar_state',
    ];

    /** @var list<string> */
    protected $hidden = ['password', 'remember_token'];

    /**
     * `name` is a computed accessor (first + last); append it so the
     * Inertia-shared user carries a display name for the frontend.
     *
     * @var list<string>
     */
    protected $appends = ['name'];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'last_login_at' => 'datetime',
            'password' => 'hashed',
            'is_active' => 'boolean',
            'dashboard_layout' => 'array',
            'sidebar_state' => 'array',
        ];
    }

    // --- Accessors / Mutators ---

    public function getNameAttribute(): string
    {
        return trim($this->first_name.' '.$this->last_name);
    }

    public function getPublicNameAttribute(): string
    {
        $initial = $this->last_name ? strtoupper(substr($this->last_name, 0, 1)).'.' : '';

        return trim($this->first_name.' '.$initial);
    }

    /**
     * Normalize + validate map_color at the write boundary.
     *
     * map_color is rendered into inline CSS across the app (map markers,
     * route rows), so a dirty value is a style-injection surface. We store
     * a canonical "#rrggbb" or null (every read site has a fallback).
     */
    public function setMapColorAttribute(mixed $value): void
    {
        if (! is_string($value) || $value === '') {
            $this->attributes['map_color'] = null;

            return;
        }

        $hex = ltrim(strtolower(trim($value)), '#');
        if (preg_match('/^[0-9a-f]{3}$/', $hex)) {
            $hex = $hex[0].$hex[0].$hex[1].$hex[1].$hex[2].$hex[2];
        }
        $this->attributes['map_color'] = preg_match('/^[0-9a-f]{6}$/', $hex) ? '#'.$hex : null;
    }

    // --- Role checks (single source of truth) ---

    public function isSuperAdmin(): bool
    {
        return $this->role === 'super_admin';
    }

    public function isTenantAdmin(): bool
    {
        return $this->role === 'tenant_admin';
    }

    public function isAgent(): bool
    {
        return $this->role === 'agent';
    }

    public function isCustomer(): bool
    {
        return $this->role === 'customer';
    }

    // --- Relationships ---

    /** @return BelongsTo<Tenant, $this> */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    /** @return HasOne<Customer, $this> */
    public function customer(): HasOne
    {
        return $this->hasOne(Customer::class);
    }

    /** @return BelongsToMany<Company, $this> */
    public function companies(): BelongsToMany
    {
        return $this->belongsToMany(Company::class, 'agent_company')
            ->withPivot('is_primary')
            ->withTimestamps();
    }
}
