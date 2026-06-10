<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * NotificationPreference — a user's per-category channel choices (email /
 * in-app), including the marketing category the mailing composer honours.
 * Belongs to a user, not a tenant.
 */
class NotificationPreference extends Model
{
    /** @var list<string> */
    protected $fillable = ['user_id', 'category', 'email', 'in_app'];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['email' => 'boolean', 'in_app' => 'boolean'];
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
