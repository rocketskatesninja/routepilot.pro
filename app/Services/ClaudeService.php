<?php

declare(strict_types=1);

namespace App\Services;

use Illuminate\Http\Client\Response;
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
    ): AiTurn {
        try {
            return $provider === 'openai'
                ? $this->callOpenAI($apiKey, $model, trim($stableSystem."\n\n".$liveSystem), $messages, $tools, $allowTools, $maxTokens)
                : $this->callAnthropic($apiKey, $model, $stableSystem, $liveSystem, $messages, $tools, $allowTools, $maxTokens);
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
            return array_map(fn (array $r): array => ['type' => 'function_call_output', 'call_id' => $r['id'], 'output' => $r['content']], $results);
        }

        return [[
            'role' => 'user',
            'content' => array_map(fn (array $r): array => ['type' => 'tool_result', 'tool_use_id' => $r['id'], 'content' => $r['content']], $results),
        ]];
    }

    /**
     * Anthropic Messages API: the stable system block carries the cache mark,
     * and the reply's own content blocks (thinking included) go back as-is.
     *
     * @param  list<array<string, mixed>>  $messages
     * @param  list<array{name: string, description: string, parameters: array<string, mixed>}>  $tools
     */
    protected function callAnthropic(string $key, string $model, string $stable, string $live, array $messages, array $tools, bool $allowTools, int $maxTokens): AiTurn
    {
        $system = array_values(array_filter([
            $stable !== '' ? ['type' => 'text', 'text' => $stable, 'cache_control' => ['type' => 'ephemeral']] : null,
            $live !== '' ? ['type' => 'text', 'text' => $live] : null,
        ]));

        $body = ['model' => $model, 'max_tokens' => $maxTokens, 'messages' => $messages];
        if ($system !== []) {
            $body['system'] = $system;
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
            messages: [['role' => 'assistant', 'content' => $content]],
            tokens: $fresh + $written + $read + $out,
            cost: ($fresh * $price['input'] + $written * $price['input'] * 1.25 + $read * $price['cached_input'] + $out * $price['output']) / 1_000_000,
        );
    }

    /**
     * OpenAI Responses API, which (unlike Chat Completions) lets reasoning
     * models use tools at any reasoning setting, so every current model works.
     * store:false keeps chats off OpenAI's servers; a reasoning model then hands
     * its reasoning back encrypted, and the turn's output items go back as-is
     * ahead of the look-up results. Tool arguments arrive as JSON strings.
     *
     * @param  list<array<string, mixed>>  $messages
     * @param  list<array{name: string, description: string, parameters: array<string, mixed>}>  $tools
     */
    protected function callOpenAI(string $key, string $model, string $system, array $messages, array $tools, bool $allowTools, int $maxTokens): AiTurn
    {
        $body = ['model' => $model, 'store' => false, 'max_output_tokens' => $maxTokens, 'input' => $messages];
        if ($system !== '') {
            $body['instructions'] = $system;
        }
        // Reasoning models (GPT-5 on, o-series); older ones reject both settings.
        $effort = config('ai.openai_reasoning_effort');
        if (preg_match('/^(gpt-([5-9]|\d\d)|o\d)/', $model) === 1) {
            $body['include'] = ['reasoning.encrypted_content'];
            if (is_string($effort) && $effort !== '') {
                $body['reasoning'] = ['effort' => $effort];
            }
        }
        if ($tools !== []) {
            // strict is on by default here, and would make every parameter required.
            $body['tools'] = array_map(fn (array $t): array => ['type' => 'function', ...$t, 'strict' => false], $tools);
            if (! $allowTools) {
                $body['tool_choice'] = 'none';
            }
        }

        $response = $this->postOpenAI($key, $body);
        // A model that won't take the configured setting thinks at its own default.
        if ($response->status() === 400 && isset($body['reasoning']) && str_contains($response->body(), 'reasoning')) {
            unset($body['reasoning']);
            $response = $this->postOpenAI($key, $body);
        }

        if (! $response->successful()) {
            Log::warning('OpenAI API error', ['status' => $response->status(), 'error' => $response->json('error.message')]);

            return AiTurn::failure();
        }

        $output = $response->json('output');
        if (! is_array($output)) {
            return AiTurn::failure();
        }
        $items = array_values(array_filter($output, 'is_array'));

        $text = [];
        $calls = [];
        foreach ($items as $item) {
            if (($item['type'] ?? '') === 'message') {
                foreach (is_array($item['content'] ?? null) ? $item['content'] : [] as $part) {
                    $text[] = is_array($part) ? (string) ($part['text'] ?? $part['refusal'] ?? '') : '';
                }
            } elseif (($item['type'] ?? '') === 'function_call') {
                $args = json_decode((string) ($item['arguments'] ?? '{}'), true);
                $calls[] = ['id' => (string) ($item['call_id'] ?? ''), 'name' => (string) ($item['name'] ?? ''), 'arguments' => is_array($args) ? $args : []];
            }
        }

        $usage = $response->json('usage');
        $usage = is_array($usage) ? $usage : [];
        $in = (int) ($usage['input_tokens'] ?? 0);
        $cached = min($in, (int) ($usage['input_tokens_details']['cached_tokens'] ?? 0));
        $out = (int) ($usage['output_tokens'] ?? 0); // reasoning included
        $price = $this->price($model);

        return new AiTurn(
            text: trim(implode("\n\n", array_filter($text, fn (string $t): bool => trim($t) !== ''))),
            toolCalls: $calls,
            messages: $items,
            tokens: $in + $out,
            cost: (($in - $cached) * $price['input'] + $cached * $price['cached_input'] + $out * $price['output']) / 1_000_000,
        );
    }

    /** @param  array<string, mixed>  $body */
    private function postOpenAI(string $key, array $body): Response
    {
        return Http::withToken($key)->timeout((int) config('ai.timeout', 30))
            ->post('https://api.openai.com/v1/responses', $body);
    }

    /**
     * USD per million tokens for a model (config/ai.php); an unlisted model
     * still records its tokens, at no cost.
     *
     * @return array{input: float, output: float, cached_input: float}
     */
    private function price(string $model): array
    {
        // Looked up by key, not config dot-notation: model names have dots in them (gpt-5.4-mini).
        $prices = config('ai.prices');
        $p = is_array($prices) && is_array($prices[$model] ?? null) ? $prices[$model] : [];

        return [
            'input' => (float) ($p['input'] ?? 0),
            'output' => (float) ($p['output'] ?? 0),
            'cached_input' => (float) ($p['cached_input'] ?? $p['input'] ?? 0),
        ];
    }
}
