<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * ChatSession — one assistant conversation: a signed-in person's (`context` is
 * their role, or "support" for one a super admin had while signed in as them)
 * or an anonymous website visitor's (`visitor_token`, context "public_lead").
 */
class ChatSession extends Model
{
    /** @var list<string> */
    protected $fillable = ['user_id', 'tenant_id', 'visitor_token', 'context'];

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

    /**
     * What the AI is sent: the last 20 messages, oldest first, starting on the
     * person's own (the newest one included).
     *
     * @return list<array{role: string, content: string}>
     */
    public function history(): array
    {
        $rows = $this->messages()
            ->whereIn('role', ['user', 'assistant'])
            ->orderByDesc('id')
            ->limit(20)
            ->get()
            ->reverse()
            ->map(fn (ChatMessage $m): array => ['role' => $m->role, 'content' => $m->content])
            ->values()
            ->all();

        while ($rows !== [] && $rows[0]['role'] !== 'user') {
            array_shift($rows);
        }

        return $rows;
    }
}
