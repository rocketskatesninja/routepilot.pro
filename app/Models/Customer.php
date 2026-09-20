<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use App\Models\Concerns\HasPersonName;
use Database\Factories\CustomerFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Customer — a homeowner/account the tenant services. Tenant-scoped.
 *
 * @property string $first_name
 * @property string|null $last_name
 * @property string|null $email
 * @property string|null $phone
 * @property string|null $address_line1
 * @property string|null $address_line2
 * @property string|null $city
 * @property string|null $state
 * @property string|null $zip
 * @property string|null $notes
 * @property int|null $user_id
 * @property bool $email_opt_out
 */
class Customer extends Model
{
    /** @use HasFactory<CustomerFactory> */
    use BelongsToTenant, HasFactory, HasPersonName, SoftDeletes;

    /** @var list<string> */
    protected $fillable = [
        'user_id', 'company_id', 'first_name', 'last_name', 'email', 'phone',
        'address_line1', 'address_line2', 'city', 'state', 'zip', 'lat', 'lng',
        'notes', 'admin_notes', 'security_code', 'bill_chemicals', 'onboarded_at', 'email_opt_out',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'lat' => 'float',
            'lng' => 'float',
            'bill_chemicals' => 'boolean',
            'onboarded_at' => 'datetime',
            'email_opt_out' => 'boolean',
        ];
    }

    public function getNameAttribute(): string
    {
        return trim($this->first_name.' '.$this->last_name);
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Portal-login state: 'none' (no login), 'active' (a usable login), or
     * 'revoked' (a login exists but was soft-deleted — e.g. the customer
     * deleted their own account). Only 'revoked' is restorable; a dangling
     * reference to a hard-deleted user reads as 'none' so a fresh grant works.
     */
    public function portalStatus(): string
    {
        if ($this->user_id === null) {
            return 'none';
        }

        $user = User::withTrashed()->select(['id', 'deleted_at'])->find($this->user_id);

        return match (true) {
            $user === null => 'none',
            $user->trashed() => 'revoked',
            default => 'active',
        };
    }

    /** @return HasMany<Pool, $this> */
    public function pools(): HasMany
    {
        return $this->hasMany(Pool::class);
    }
}
