<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * ChatSession — a user's assistant conversation. The `context` records the
 * surface/role (e.g. tenant_admin, agent, customer) that shaped its prompt.
 */
class ChatSession extends Model
{
    /** @var list<string> */
    protected $fillable = ['user_id', 'context'];

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return HasMany<ChatMessage, $this> */
    public function messages(): HasMany
    {
        return $this->hasMany(ChatMessage::class);
    }
}
