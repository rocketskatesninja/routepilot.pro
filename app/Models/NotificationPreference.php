<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * NotificationPreference — whether a user gets one type of in-app
 * notification (`category` holds a NotificationTypes key). Customer emails are
 * chosen separately (EmailPreferences). Belongs to a user, not a tenant.
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
