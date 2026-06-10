<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * ChatMessage — one turn in a ChatSession (user / assistant / system).
 *
 * @property string $role
 * @property string $content
 */
class ChatMessage extends Model
{
    /** @var list<string> */
    protected $fillable = ['chat_session_id', 'role', 'content'];

    /** @return BelongsTo<ChatSession, $this> */
    public function session(): BelongsTo
    {
        return $this->belongsTo(ChatSession::class);
    }
}
