<?php

declare(strict_types=1);

namespace App\Services\Chat;

use App\Models\ChatMessage;
use App\Models\ChatSession;
use App\Models\Tenant;
use App\Services\Chat\Tools\CaptureLeadTool;
use App\Services\ClaudeService;

/**
 * The public lead-capture chatbot turn: build the tenant's public context, call
 * the AI with the single capture_lead tool, persist the exchange. Anonymous —
 * no user, scoped to one tenant. Credentials/quota are the caller's concern.
 */
class PublicAssistant
{
    public function __construct(private ClaudeService $claude) {}

    public function reply(Tenant $tenant, ChatSession $session, string $message): string
    {
        ChatMessage::create(['chat_session_id' => $session->id, 'role' => 'user', 'content' => $message]);

        $system = app(PublicLeadContext::class)->build($tenant);
        $history = $this->history($session);
        [$provider, $key, $model] = app(AiCredentials::class)->for((int) $tenant->id);
        $tools = [(new CaptureLeadTool)->toSchema()];

        $result = $this->claude->chat($provider, $key, $model, $history, $system, 1024, $tools);

        $reply = match (true) {
            $result === null => "Sorry — chat isn't available right now. Please use the contact form and we'll get back to you.",
            is_array($result) => $this->capture($result['tool_call'], (int) $tenant->id),
            default => $result,
        };

        ChatMessage::create(['chat_session_id' => $session->id, 'role' => 'assistant', 'content' => $reply]);

        return $reply;
    }

    /**
     * Run capture_lead (the only public tool) and return a friendly confirmation
     * — no second AI round trip, to keep public chat cheap.
     *
     * @param  array{name: string, arguments: array<string, mixed>}  $toolCall
     */
    private function capture(array $toolCall, int $tenantId): string
    {
        if ($toolCall['name'] !== 'capture_lead') {
            return "Let me get someone from the team to help with that — what's the best email or phone to reach you?";
        }

        (new CaptureLeadTool)->execute($toolCall['arguments'], $tenantId);
        $name = trim((string) ($toolCall['arguments']['name'] ?? ''));

        return ($name !== '' ? "Thanks, {$name}! " : 'Thanks! ')
            ."I've passed your details to the team — they'll reach out soon. Anything else I can help with?";
    }

    /**
     * The last 20 turns as provider message shapes.
     *
     * @return list<array{role: string, content: string}>
     */
    private function history(ChatSession $session): array
    {
        $rows = $session->messages()
            ->orderByDesc('id')
            ->limit(20)
            ->get()
            ->map(fn (ChatMessage $m): array => ['role' => $m->role, 'content' => $m->content])
            ->all();

        return array_reverse($rows);
    }
}
