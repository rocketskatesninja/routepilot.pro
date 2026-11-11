<?php

declare(strict_types=1);

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Provider-agnostic AI chat engine (Anthropic + OpenAI) with tool use.
 * Credential and model resolution is the caller's concern.
 *
 * `turn()` is one model call in a conversation that can use look-ups: it
 * returns every tool call in the reply plus the reply in the provider's own
 * shape, so the caller can send it back with `toolResults()` and go round
 * again. Provider errors are logged and become a friendly line; their text is
 * never shown to anyone.
 */
class ClaudeService
{
    /**
     * One model call. `$stableSystem` is the same for every message from this
     * kind of account (rules, guide, expertise) and is cached on Anthropic;
     * `$liveSystem` is this person's own data, which changes.
     *
     * @param  list<array<string, mixed>>  $messages  {role, content} strings, plus earlier turns' `message` and `toolResults()`
     * @param  list<array{name: string, description: string, parameters: array<string, mixed>}>  $tools
     */
    public function turn(
        string $provider,
        string $apiKey,
        string $model,
        string $stableSystem,
        string $liveSystem,
        array $messages,
        array $tools = [],
        bool $allowTools = true,
        int $maxTokens = 4096,
        bool $adaptiveThinking = false,
    ): AiTurn {
        try {
            return $provider === 'openai'
                ? $this->callOpenAI($apiKey, $model, trim($stableSystem."\n\n".$liveSystem), $messages, $tools, $allowTools, $maxTokens)
                : $this->callAnthropic($apiKey, $model, $stableSystem, $liveSystem, $messages, $tools, $allowTools, $maxTokens, $adaptiveThinking);
        } catch (\Throwable $e) {
            Log::warning('AI API call failed', ['provider' => $provider, 'error' => $e->getMessage()]);

            return AiTurn::failure();
        }
    }

    /**
     * The results of a turn's look-ups, as the message(s) that follow it.
     *
     * @param  list<array{id: string, content: string}>  $results
     * @return list<array<string, mixed>>
     */
    public function toolResults(string $provider, array $results): array
    {
        if ($provider === 'openai') {
            return array_map(fn (array $r): array => ['role' => 'tool', 'tool_call_id' => $r['id'], 'content' => $r['content']], $results);
        }

        return [[
            'role' => 'user',
            'content' => array_map(fn (array $r): array => ['type' => 'tool_result', 'tool_use_id' => $r['id'], 'content' => $r['content']], $results),
        ]];
    }

    /**
     * A single call that takes the first tool call or the text (the website
     * chatbot's one-shot use). Null when no key is supplied.
     *
     * @param  list<array{role: string, content: string}>  $messages
     * @param  list<array{name: string, description: string, parameters: array<string, mixed>}>  $tools
     * @return string|array{tool_call: array{name: string, arguments: array<string, mixed>}}|null
     */
    public function chat(
        string $provider,
        string $apiKey,
        string $model,
        array $messages,
        string $systemPrompt = '',
        int $maxTokens = 4096,
        array $tools = [],
        bool $adaptiveThinking = false,
    ): string|array|null {
        if ($apiKey === '') {
            return null;
        }

        $turn = $this->turn($provider, $apiKey, $model, $systemPrompt, '', $messages, $tools, true, $maxTokens, $adaptiveThinking);
        $call = $turn->toolCalls[0] ?? null;

        return $call !== null && ! $turn->failed
            ? ['tool_call' => ['name' => $call['name'], 'arguments' => $call['arguments']]]
            : $turn->text;
    }

    /**
     * Anthropic Messages API: the stable system block carries the cache mark,
     * and the reply's own content blocks (thinking included) go back as-is.
     *
     * @param  list<array<string, mixed>>  $messages
     * @param  list<array{name: string, description: string, parameters: array<string, mixed>}>  $tools
     */
    protected function callAnthropic(string $key, string $model, string $stable, string $live, array $messages, array $tools, bool $allowTools, int $maxTokens, bool $adaptiveThinking): AiTurn
    {
        $system = array_values(array_filter([
            $stable !== '' ? ['type' => 'text', 'text' => $stable, 'cache_control' => ['type' => 'ephemeral']] : null,
            $live !== '' ? ['type' => 'text', 'text' => $live] : null,
        ]));

        $body = ['model' => $model, 'max_tokens' => $maxTokens, 'messages' => $messages];
        if ($system !== []) {
            $body['system'] = $system;
        }
        if ($adaptiveThinking) {
            $body['thinking'] = ['type' => 'adaptive'];
        }
        if ($tools !== []) {
            // Anthropic uses input_schema rather than parameters.
            $body['tools'] = array_map(fn (array $t): array => [
                'name' => $t['name'],
                'description' => $t['description'],
                'input_schema' => $t['parameters'],
            ], $tools);
            if (! $allowTools) {
                $body['tool_choice'] = ['type' => 'none'];
            }
        }

        $response = Http::withHeaders([
            'x-api-key' => $key,
            'anthropic-version' => '2023-06-01',
            'content-type' => 'application/json',
        ])->timeout((int) config('ai.timeout', 30))
            ->post('https://api.anthropic.com/v1/messages', $body);

        if (! $response->successful()) {
            Log::warning('Anthropic API error', ['status' => $response->status(), 'error' => $response->json('error.message')]);

            return AiTurn::failure();
        }

        $content = $response->json('content');
        if (! is_array($content)) {
            return AiTurn::failure();
        }

        $text = [];
        $calls = [];
        foreach ($content as $block) {
            if (! is_array($block)) {
                continue;
            }
            if (($block['type'] ?? '') === 'text') {
                $text[] = (string) ($block['text'] ?? '');
            } elseif (($block['type'] ?? '') === 'tool_use') {
                $input = $block['input'] ?? [];
                $calls[] = ['id' => (string) ($block['id'] ?? ''), 'name' => (string) ($block['name'] ?? ''), 'arguments' => is_array($input) ? $input : []];
            }
        }

        $usage = $response->json('usage');
        $usage = is_array($usage) ? $usage : [];
        $fresh = (int) ($usage['input_tokens'] ?? 0);
        $written = (int) ($usage['cache_creation_input_tokens'] ?? 0);
        $read = (int) ($usage['cache_read_input_tokens'] ?? 0);
        $out = (int) ($usage['output_tokens'] ?? 0);
        $price = $this->price($model);

        return new AiTurn(
            text: trim(implode("\n\n", array_filter($text, fn (string $t): bool => trim($t) !== ''))),
            toolCalls: $calls,
            message: ['role' => 'assistant', 'content' => $content],
            tokens: $fresh + $written + $read + $out,
            cost: ($fresh * $price['input'] + $written * $price['input'] * 1.25 + $read * $price['cached_input'] + $out * $price['output']) / 1_000_000,
        );
    }

    /**
     * OpenAI Chat Completions: the system prompt is the first message, tool-call
     * arguments arrive as JSON strings and are decoded.
     *
     * @param  list<array<string, mixed>>  $messages
     * @param  list<array{name: string, description: string, parameters: array<string, mixed>}>  $tools
     */
    protected function callOpenAI(string $key, string $model, string $system, array $messages, array $tools, bool $allowTools, int $maxTokens): AiTurn
    {
        $body = [
            'model' => $model,
            'max_tokens' => $maxTokens,
            'messages' => [...($system !== '' ? [['role' => 'system', 'content' => $system]] : []), ...$messages],
        ];
        if ($tools !== []) {
            $body['tools'] = array_map(fn (array $t): array => [
                'type' => 'function',
                'function' => ['name' => $t['name'], 'description' => $t['description'], 'parameters' => $t['parameters']],
            ], $tools);
            if (! $allowTools) {
                $body['tool_choice'] = 'none';
            }
        }

        $response = Http::withHeaders([
            'Authorization' => 'Bearer '.$key,
            'Content-Type' => 'application/json',
        ])->timeout((int) config('ai.timeout', 30))
            ->post('https://api.openai.com/v1/chat/completions', $body);

        if (! $response->successful()) {
            Log::warning('OpenAI API error', ['status' => $response->status(), 'error' => $response->json('error.message')]);

            return AiTurn::failure();
        }

        $message = $response->json('choices.0.message');
        if (! is_array($message)) {
            return AiTurn::failure();
        }

        $calls = [];
        $rawCalls = is_array($message['tool_calls'] ?? null) ? array_values($message['tool_calls']) : [];
        foreach ($rawCalls as $call) {
            $fn = is_array($call) && is_array($call['function'] ?? null) ? $call['function'] : null;
            if ($fn === null) {
                continue;
            }
            $args = json_decode((string) ($fn['arguments'] ?? '{}'), true);
            $calls[] = ['id' => (string) ($call['id'] ?? ''), 'name' => (string) ($fn['name'] ?? ''), 'arguments' => is_array($args) ? $args : []];
        }

        $text = is_string($message['content'] ?? null) ? $message['content'] : '';
        $echo = ['role' => 'assistant', 'content' => $text !== '' ? $text : null];
        if ($rawCalls !== []) {
            $echo['tool_calls'] = $rawCalls;
        }

        $usage = $response->json('usage');
        $usage = is_array($usage) ? $usage : [];
        $in = (int) ($usage['prompt_tokens'] ?? 0);
        $cached = min($in, (int) ($usage['prompt_tokens_details']['cached_tokens'] ?? 0));
        $out = (int) ($usage['completion_tokens'] ?? 0);
        $price = $this->price($model);

        return new AiTurn(
            text: trim($text),
            toolCalls: $calls,
            message: $echo,
            tokens: $in + $out,
            cost: (($in - $cached) * $price['input'] + $cached * $price['cached_input'] + $out * $price['output']) / 1_000_000,
        );
    }

    /**
     * USD per million tokens for a model (config/ai.php); an unlisted model
     * still records its tokens, at no cost.
     *
     * @return array{input: float, output: float, cached_input: float}
     */
    private function price(string $model): array
    {
        $p = config("ai.prices.{$model}");
        $p = is_array($p) ? $p : [];

        return [
            'input' => (float) ($p['input'] ?? 0),
            'output' => (float) ($p['output'] ?? 0),
            'cached_input' => (float) ($p['cached_input'] ?? $p['input'] ?? 0),
        ];
    }
}
