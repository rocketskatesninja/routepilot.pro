<?php

declare(strict_types=1);

namespace App\Services\Chat;

use App\Models\ChatMessage;
use App\Models\ChatSession;
use App\Models\Customer;
use App\Models\TenantSetting;
use App\Models\User;
use App\Services\ClaudeService;

/**
 * Orchestrates an assistant turn: builds the role-specific context, resolves
 * the tenant's AI credentials, calls ClaudeService, runs any tool call, and
 * persists the exchange. Keeps ChatController thin.
 */
class AssistantService
{
    public function __construct(private ClaudeService $claude) {}

    /** Persist the user's message, get the assistant's reply, persist it. */
    public function reply(User $user, ChatSession $session, string $message): string
    {
        $role = $user->role;
        ChatMessage::create(['chat_session_id' => $session->id, 'role' => 'user', 'content' => $message]);

        $system = $this->systemPrompt($user, $role);
        $history = $this->history($session);
        [$provider, $key, $model] = $this->credentials((int) $user->tenant_id);
        $tools = $role === 'tenant_admin' ? ToolRegistry::schemas() : [];

        // Agents get adaptive thinking for deeper chemistry/equipment reasoning.
        $result = $this->claude->chat($provider, $key, $model, $history, $system, 4096, $tools, $role === 'agent');

        $reply = match (true) {
            $result === null => "⚠️ The AI assistant isn't configured yet. Add a platform or per-tenant API key.",
            is_array($result) => $this->runTool($result['tool_call'], $session, $history, $system, (int) $user->tenant_id, $provider, $key, $model),
            default => $result,
        };

        ChatMessage::create(['chat_session_id' => $session->id, 'role' => 'assistant', 'content' => $reply]);

        return $reply;
    }

    /**
     * Execute the tool, record it, and ask the model to summarize the result.
     *
     * @param  array{name: string, arguments: array<string, mixed>}  $toolCall
     * @param  list<array{role: string, content: string}>  $history
     */
    private function runTool(array $toolCall, ChatSession $session, array $history, string $system, int $tenantId, string $provider, string $key, string $model): string
    {
        $tool = ToolRegistry::find($toolCall['name']);
        if ($tool === null) {
            return "⚠️ Unknown tool: {$toolCall['name']}";
        }

        $toolResult = $tool->execute($toolCall['arguments'], $tenantId);
        ChatMessage::create(['chat_session_id' => $session->id, 'role' => 'assistant', 'content' => "🔧 *{$tool->name()}*: {$toolResult}"]);

        $history[] = ['role' => 'assistant', 'content' => "Tool result: {$toolResult}"];
        $history[] = ['role' => 'user', 'content' => 'Summarize what you just did in a friendly way.'];
        $followUp = $this->claude->chat($provider, $key, $model, $history, $system, 2048);

        return is_string($followUp) ? $followUp : $toolResult;
    }

    private function systemPrompt(User $user, string $role): string
    {
        return match ($role) {
            'tenant_admin' => app(TenantContext::class)->build((int) $user->tenant_id),
            'agent' => app(AgentContext::class)->build($user),
            'customer' => $this->customerPrompt($user),
            default => 'You are a helpful pool service assistant.',
        };
    }

    private function customerPrompt(User $user): string
    {
        $customer = Customer::query()->where('user_id', $user->id)->first();

        return $customer !== null
            ? app(CustomerContext::class)->build($customer)
            : 'You are a helpful pool service assistant.';
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

    /**
     * Resolve [provider, key, model]: per-tenant override, else platform config.
     *
     * @return array{string, string, string}
     */
    private function credentials(int $tenantId): array
    {
        $provider = TenantSetting::getFor($tenantId, 'ai_provider') ?? (string) config('ai.default_provider', 'anthropic');
        $key = TenantSetting::getFor($tenantId, 'ai_api_key') ?? (string) config("ai.platform_keys.{$provider}", '');
        $model = TenantSetting::getFor($tenantId, 'ai_model') ?? (string) config("ai.models.{$provider}", '');

        return [$provider, $key, $model];
    }
}
