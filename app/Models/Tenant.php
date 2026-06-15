<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\TenantFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;
use Laravel\Cashier\Billable;

/**
 * Tenant — a company/account using the platform and the single billable
 * owner of its subscription (Cashier's Billable trait is added in Phase 6).
 * Resolved by session (staff) or custom domain / subdomain (public) — see
 * ResolveTenant. All tenant-owned data is scoped via the BelongsToTenant
 * trait on child models.
 *
 * @property array<string, mixed>|null $settings
 * @property string|null $address_line1
 * @property string|null $address_line2
 * @property string|null $city
 * @property string|null $state
 * @property string|null $postal_code
 * @property float|null $lat
 * @property float|null $lng
 * @property Carbon|null $trial_ends_at
 * @property string|null $pm_type
 * @property string|null $pm_last_four
 */
class Tenant extends Model
{
    /** @use HasFactory<TenantFactory> */
    use Billable, HasFactory, SoftDeletes;

    /**
     * Subdomains that collide with platform hostnames or hint at admin
     * surfaces — rejected on signup AND on rename (source both sites here).
     *
     * @var list<string>
     */
    public const RESERVED_SLUGS = ['www', 'api', 'mail', 'admin', 'app', 'blog', 'dev', 'staging', 'test'];

    /** @var list<string> */
    protected $fillable = [
        'name', 'slug', 'primary_domain', 'timezone',
        'logo_path', 'brand_color', 'status', 'settings',
        'address_line1', 'address_line2', 'city', 'state', 'postal_code',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'settings' => 'array',
            'lat' => 'float',
            'lng' => 'float',
            'trial_ends_at' => 'datetime',
        ];
    }

    /** @return HasMany<User, $this> */
    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    /** @return HasMany<Customer, $this> */
    public function customers(): HasMany
    {
        return $this->hasMany(Customer::class);
    }

    /** @return HasMany<Pool, $this> */
    public function pools(): HasMany
    {
        return $this->hasMany(Pool::class);
    }

    /**
     * Platform-billing state for the UI: trial countdown + subscription status.
     * Cashier is the source of truth (generic trial via trial_ends_at until they
     * subscribe).
     *
     * @return array{status: string, on_trial: bool, subscribed: bool, trial_ends_at: string|null, trial_days_left: int}
     */
    public function billingState(): array
    {
        $subscription = $this->subscription('default');
        $trialEnds = $this->trial_ends_at;

        $status = match (true) {
            $subscription !== null && $subscription->pastDue() => 'past_due',
            $subscription !== null && $subscription->active() => 'active',
            $subscription !== null && $subscription->canceled() => 'canceled',
            $this->onGenericTrial() => 'trialing',
            $this->hasExpiredGenericTrial() => 'expired',
            default => 'none',
        };

        return [
            'status' => $status,
            'on_trial' => $this->onTrial(),
            'subscribed' => $this->subscribed(),
            'trial_ends_at' => $trialEnds?->toDateString(),
            'trial_days_left' => $trialEnds !== null && $trialEnds->isFuture()
                ? (int) ceil((float) now()->diffInDays($trialEnds, false))
                : 0,
        ];
    }

    /**
     * Single-line business address for geocoding / display, or null when no
     * street address is set. Excludes line 2 (suite) — it confuses geocoders.
     */
    public function formattedAddress(): ?string
    {
        $parts = array_filter([
            (string) ($this->getAttribute('address_line1') ?? ''),
            (string) ($this->getAttribute('city') ?? ''),
            trim((string) ($this->getAttribute('state') ?? '').' '.(string) ($this->getAttribute('postal_code') ?? '')),
        ], static fn (string $part): bool => trim($part) !== '');

        return $parts === [] ? null : implode(', ', $parts);
    }
}
