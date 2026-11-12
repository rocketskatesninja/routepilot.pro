<?php

declare(strict_types=1);

namespace App\Services\Chat;

use App\Models\ChatMessage;
use App\Models\ChatSession;
use App\Models\User;
use App\Services\AiQuota;
use App\Services\ClaudeService;
use App\Support\SensitiveText;
use Illuminate\Support\Str;

/**
 * One assistant message: blank out card numbers and passwords, build this
 * person's prompt, then let the model run look-ups (several per reply, a few
 * rounds) until it answers in words. Only the question and the answer are
 * saved; the look-up chatter stays out of the conversation.
 *
 * The assistant only reads. It can't change anything, so a wrong guess or an
 * instruction planted in someone's note can at worst mislead an answer.
 */
class AssistantService
{
    public const NOT_CONFIGURED = 'The AI assistant isn’t configured yet. Ask the platform admin to add an API key.';

    /** The most a single look-up result can say; past that it's cut short. */
    private const RESULT_LIMIT = 12000;

    public function __construct(private ClaudeService $claude) {}

    public function reply(User $user, ChatSession $session, string $message): string
    {
        ChatMessage::create(['chat_session_id' => $session->id, 'role' => 'user', 'content' => SensitiveText::redact($message)]);

        [$provider, $key, $model] = app(AiCredentials::class)->for((int) $user->tenant_id);
        if ($key === '') {
            return $this->save($session, self::NOT_CONFIGURED);
        }

        [$stable, $live] = app(AssistantPrompt::class)->for($user);
        $tools = ToolRegistry::for($user);
        $schemas = array_map(fn (AssistantTool $t): array => $t->toSchema(), $tools);
        $messages = $session->history();
        $rounds = max(1, (int) config('ai.max_rounds', 4));
        $tokens = 0;
        $cost = 0.0;
        $answered = false;
        $reply = '';

        for ($round = 1; $round <= $rounds; $round++) {
            $turn = $this->claude->turn(
                $provider, $key, $model, $stable, $live, $messages, $schemas,
                allowTools: $round < $rounds,
                maxTokens: (int) config('ai.max_tokens', 4096),
            );
            $tokens += $turn->tokens;
            $cost += $turn->cost;
            $answered = $answered || ! $turn->failed;

            if ($turn->failed || $turn->toolCalls === []) {
                $reply = $turn->text;
                break;
            }

            $messages[] = $turn->message;
            $results = array_map(fn (array $call): array => ['id' => $call['id'], 'content' => $this->run($call, $tools, $user)], $turn->toolCalls);
            array_push($messages, ...$this->claude->toolResults($provider, $results));
        }

        // A message counts against the allowance only when the AI actually answered
        // (not when it was down or not set up); its tokens and cost go with it.
        if ($user->tenant_id !== null && $answered) {
            app(AiQuota::class)->charge((int) $user->tenant_id, $tokens, $cost);
        }

        return $this->save($session, $reply !== '' ? $reply : 'Sorry, I couldn’t put an answer together. Please try asking another way.');
    }

    /**
     * Run one look-up the model asked for, if this person is offered it.
     *
     * @param  array{id: string, name: string, arguments: array<string, mixed>}  $call
     * @param  list<AssistantTool>  $tools
     */
    private function run(array $call, array $tools, User $user): string
    {
        $tool = collect($tools)->first(fn (AssistantTool $t): bool => $t->name() === $call['name']);
        if ($tool === null) {
            return "There is no look-up called \"{$call['name']}\".";
        }

        try {
            return Str::limit($tool->execute($call['arguments'], $user), self::RESULT_LIMIT, "\n(Cut short: ask a narrower question.)");
        } catch (\Throwable $e) {
            report($e);

            return 'That look-up failed. Say you couldn’t look it up right now.';
        }
    }

    private function save(ChatSession $session, string $reply): string
    {
        ChatMessage::create(['chat_session_id' => $session->id, 'role' => 'assistant', 'content' => $reply]);

        return $reply;
    }
}
