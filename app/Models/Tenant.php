<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\TenantFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Tenant — a company/account using the platform and the single billable
 * owner of its subscription (Cashier's Billable trait is added in Phase 6).
 * Resolved by session (staff) or custom domain / subdomain (public) — see
 * ResolveTenant. All tenant-owned data is scoped via the BelongsToTenant
 * trait on child models.
 */
class Tenant extends Model
{
    /** @use HasFactory<TenantFactory> */
    use HasFactory, SoftDeletes;

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
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'settings' => 'array',
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
}
