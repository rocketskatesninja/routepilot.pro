<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A phone that shows its user's notifications (web push): where the push
 * service takes them, and the keys they're encrypted with. Belongs to a user,
 * not a tenant. `user_id` is set with forceFill — a device is only ever
 * claimed by whoever is signed in on it.
 */
class PushSubscription extends Model
{
    /** @var list<string> */
    protected $fillable = ['endpoint', 'public_key', 'auth_token'];

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
