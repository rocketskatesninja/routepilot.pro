<?php

declare(strict_types=1);

namespace App\Services\Chat;

use App\Models\ChatMessage;
use App\Models\ChatSession;
use App\Models\Tenant;
use App\Services\AiQuota;
use App\Services\Chat\Tools\CaptureLeadTool;
use App\Services\ClaudeService;
use App\Support\SensitiveText;

/**
 * One message to a company website's chatbot: an anonymous visitor, one
 * company. Card numbers and passwords are blanked before anything is saved or
 * sent; the model sees only the company's public information (cached) and the
 * conversation; its one tool, capture_lead, passes a visitor to the office.
 * A message counts against the company's allowance only when the AI answered,
 * with its tokens and cost.
 */
class PublicAssistant
{
    public const UNAVAILABLE = 'Thanks for reaching out! Our chat assistant isn’t available right now — please use the contact form and we’ll get back to you shortly.';

    public function __construct(private ClaudeService $claude) {}

    public function reply(Tenant $tenant, ChatSession $session, string $message): string
    {
        ChatMessage::create(['chat_session_id' => $session->id, 'role' => 'user', 'content' => SensitiveText::redact($message)]);

        [$provider, $key, $model] = app(AiCredentials::class)->for((int) $tenant->id);
        $turn = $this->claude->turn(
            $provider, $key, $model,
            app(PublicLeadContext::class)->build($tenant), '',
            $session->history(),
            [(new CaptureLeadTool)->toSchema()],
            maxTokens: 1024,
        );

        if (! $turn->failed) {
            app(AiQuota::class)->charge((int) $tenant->id, $turn->tokens, $turn->cost);
        }

        $call = $turn->toolCalls[0] ?? null;
        $reply = match (true) {
            $turn->failed => self::UNAVAILABLE,
            $call !== null => $this->capture($call, (int) $tenant->id),
            $turn->text !== '' => $turn->text,
            default => 'Sorry, I didn’t catch that. Could you say it another way?',
        };

        ChatMessage::create(['chat_session_id' => $session->id, 'role' => 'assistant', 'content' => $reply]);

        return $reply;
    }

    /**
     * Run capture_lead (the only public tool) and reply with what it says: a
     * thank-you, or why it didn't pass anything on (no name yet, or they're
     * waiting on replies). No second AI round trip, to keep public chat cheap.
     *
     * @param  array{id: string, name: string, arguments: array<string, mixed>}  $call
     */
    private function capture(array $call, int $tenantId): string
    {
        if ($call['name'] !== 'capture_lead') {
            return 'Let me get someone from the team to help with that — what’s the best email or phone to reach you?';
        }

        return (new CaptureLeadTool)->execute($call['arguments'], $tenantId);
    }
}
