<?php

declare(strict_types=1);

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Provider-agnostic AI chat engine (Anthropic + OpenAI) with optional
 * tool use. Credential and model resolution is the caller's concern —
 * this service just performs the request and normalizes the response.
 *
 * Modernized from the legacy app: current model IDs (see config/ai.php),
 * optional adaptive thinking, `\Throwable` error handling, and tool-call
 * arguments parsed as JSON (never string-matched). Returns a text reply,
 * a normalized ['tool_call' => ['name', 'arguments']] array, or null when
 * no key is supplied.
 */
class ClaudeService
{
    /**
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

        try {
            return $provider === 'openai'
                ? $this->callOpenAI($apiKey, $model, $messages, $systemPrompt, $maxTokens, $tools)
                : $this->callAnthropic($apiKey, $model, $messages, $systemPrompt, $maxTokens, $tools, $adaptiveThinking);
        } catch (\Throwable $e) {
            Log::warning('AI API call failed', ['provider' => $provider, 'error' => $e->getMessage()]);

            return '⚠️ Connection error: could not reach the AI service. Please try again.';
        }
    }

    /**
     * Anthropic Messages API. Skips thinking blocks; tool_use wins over text.
     *
     * @param  list<array{role: string, content: string}>  $messages
     * @param  list<array{name: string, description: string, parameters: array<string, mixed>}>  $tools
     * @return string|array{tool_call: array{name: string, arguments: array<string, mixed>}}
     */
    protected function callAnthropic(string $key, string $model, array $messages, string $system, int $maxTokens, array $tools, bool $adaptiveThinking): string|array
    {
        $body = [
            'model' => $model,
            'max_tokens' => $maxTokens,
            'system' => $system,
            'messages' => $messages,
        ];
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
        }

        $response = Http::withHeaders([
            'x-api-key' => $key,
            'anthropic-version' => '2023-06-01',
            'content-type' => 'application/json',
        ])->timeout((int) config('ai.timeout', 30))
            ->post('https://api.anthropic.com/v1/messages', $body);

        if (! $response->successful()) {
            $error = $response->json('error.message') ?? 'Unknown error (status '.$response->status().')';
            Log::warning('Anthropic API error', ['error' => $error]);

            return '⚠️ AI Error: '.(is_string($error) ? $error : 'unexpected response');
        }

        $content = $response->json('content');
        if (! is_array($content)) {
            return 'No response from AI.';
        }

        foreach ($content as $block) {
            if (is_array($block) && ($block['type'] ?? '') === 'tool_use') {
                $input = $block['input'] ?? [];

                return ['tool_call' => [
                    'name' => (string) ($block['name'] ?? ''),
                    'arguments' => is_array($input) ? $input : [],
                ]];
            }
        }
        foreach ($content as $block) {
            if (is_array($block) && ($block['type'] ?? '') === 'text') {
                return (string) ($block['text'] ?? '');
            }
        }

        return 'No response from AI.';
    }

    /**
     * OpenAI Chat Completions API. Tool-call arguments arrive as a JSON
     * string and are decoded.
     *
     * @param  list<array{role: string, content: string}>  $messages
     * @param  list<array{name: string, description: string, parameters: array<string, mixed>}>  $tools
     * @return string|array{tool_call: array{name: string, arguments: array<string, mixed>}}
     */
    protected function callOpenAI(string $key, string $model, array $messages, string $system, int $maxTokens, array $tools): string|array
    {
        // OpenAI takes the system prompt as the first message.
        $openaiMessages = [];
        if ($system !== '') {
            $openaiMessages[] = ['role' => 'system', 'content' => $system];
        }
        foreach ($messages as $msg) {
            $openaiMessages[] = ['role' => $msg['role'], 'content' => $msg['content']];
        }

        $body = ['model' => $model, 'max_tokens' => $maxTokens, 'messages' => $openaiMessages];
        if ($tools !== []) {
            $body['tools'] = array_map(fn (array $t): array => [
                'type' => 'function',
                'function' => ['name' => $t['name'], 'description' => $t['description'], 'parameters' => $t['parameters']],
            ], $tools);
        }

        $response = Http::withHeaders([
            'Authorization' => 'Bearer '.$key,
            'Content-Type' => 'application/json',
        ])->timeout((int) config('ai.timeout', 30))
            ->post('https://api.openai.com/v1/chat/completions', $body);

        if (! $response->successful()) {
            $error = $response->json('error.message') ?? 'Unknown error (status '.$response->status().')';
            Log::warning('OpenAI API error', ['error' => $error]);

            return '⚠️ AI Error: '.(is_string($error) ? $error : 'unexpected response');
        }

        $message = $response->json('choices.0.message');
        if (! is_array($message)) {
            return 'No response from AI.';
        }

        $toolCalls = $message['tool_calls'] ?? null;
        if (is_array($toolCalls) && isset($toolCalls[0]['function']) && is_array($toolCalls[0]['function'])) {
            $fn = $toolCalls[0]['function'];
            $args = json_decode((string) ($fn['arguments'] ?? '{}'), true);

            return ['tool_call' => [
                'name' => (string) ($fn['name'] ?? ''),
                'arguments' => is_array($args) ? $args : [],
            ]];
        }

        return (string) ($message['content'] ?? 'No response from AI.');
    }
}
