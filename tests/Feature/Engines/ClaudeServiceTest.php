<?php

declare(strict_types=1);

use App\Services\ClaudeService;
use GuzzleHttp\Promise\PromiseInterface;
use Illuminate\Support\Facades\Http;

/**
 * AI engine: provider-agnostic request shaping and response normalization,
 * with the network faked. No DB — credentials/model are passed in.
 */
beforeEach(function () {
    $this->ai = new ClaudeService;
});

/** @param  list<array<string, mixed>>  $content */
function anthropicReply(array $content): PromiseInterface
{
    return Http::response(['content' => $content], 200);
}

test('returns null when no API key is supplied (no request made)', function () {
    Http::fake();

    $result = $this->ai->chat('anthropic', '', 'claude-haiku-4-5', [
        ['role' => 'user', 'content' => 'hi'],
    ]);

    expect($result)->toBeNull();
    Http::assertNothingSent();
});

test('parses an Anthropic text reply', function () {
    Http::fake(['api.anthropic.com/*' => anthropicReply([
        ['type' => 'text', 'text' => 'Your pH looks high.'],
    ])]);

    $result = $this->ai->chat('anthropic', 'sk-key', 'claude-haiku-4-5', [
        ['role' => 'user', 'content' => 'how is my pool?'],
    ]);

    expect($result)->toBe('Your pH looks high.');

    Http::assertSent(function ($request) {
        return $request->url() === 'https://api.anthropic.com/v1/messages'
            && $request['model'] === 'claude-haiku-4-5'
            && $request->hasHeader('x-api-key', 'sk-key')
            && ! isset($request['thinking']); // off by default
    });
});

test('a tool_use block normalizes to a tool_call (Anthropic)', function () {
    Http::fake(['api.anthropic.com/*' => anthropicReply([
        ['type' => 'tool_use', 'name' => 'lookup_chemistry', 'input' => ['pool' => 'Smith', 'days' => 30]],
    ])]);

    $result = $this->ai->chat('anthropic', 'sk-key', 'claude-haiku-4-5',
        [['role' => 'user', 'content' => 'last readings for Smith?']],
        tools: [['name' => 'lookup_chemistry', 'description' => 'Look up readings', 'parameters' => ['type' => 'object']]],
    );

    expect($result)->toBe([
        'tool_call' => ['name' => 'lookup_chemistry', 'arguments' => ['pool' => 'Smith', 'days' => 30]],
    ]);

    // Tools are mapped to Anthropic's input_schema shape.
    Http::assertSent(fn ($request) => $request['tools'][0]['input_schema'] === ['type' => 'object']
        && ! isset($request['tools'][0]['parameters']));
});

test('thinking blocks are skipped; tool_use wins over text', function () {
    Http::fake(['api.anthropic.com/*' => anthropicReply([
        ['type' => 'thinking', 'thinking' => ''],
        ['type' => 'text', 'text' => 'some preamble'],
        ['type' => 'tool_use', 'name' => 'skip_stop', 'input' => ['id' => 5]],
    ])]);

    $result = $this->ai->chat('anthropic', 'sk-key', 'claude-haiku-4-5', [['role' => 'user', 'content' => 'skip it']]);

    expect($result)->toBe(['tool_call' => ['name' => 'skip_stop', 'arguments' => ['id' => 5]]]);
});

test('adaptive thinking is opt-in and adds the request param', function () {
    Http::fake(['api.anthropic.com/*' => anthropicReply([['type' => 'text', 'text' => 'ok']])]);

    $this->ai->chat('anthropic', 'sk-key', 'claude-sonnet-4-6',
        [['role' => 'user', 'content' => 'think hard']],
        adaptiveThinking: true,
    );

    Http::assertSent(fn ($request) => $request['thinking'] === ['type' => 'adaptive']);
});

test('an Anthropic API error returns a friendly message', function () {
    Http::fake(['api.anthropic.com/*' => Http::response(['error' => ['message' => 'overloaded']], 529)]);

    $result = $this->ai->chat('anthropic', 'sk-key', 'claude-haiku-4-5', [['role' => 'user', 'content' => 'hi']]);

    expect($result)->toBe('⚠️ AI Error: overloaded');
});

test('parses an OpenAI text reply and prepends the system prompt', function () {
    Http::fake(['api.openai.com/*' => Http::response([
        'choices' => [['message' => ['content' => 'Hello from GPT.']]],
    ], 200)]);

    $result = $this->ai->chat('openai', 'sk-o', 'gpt-4o-mini',
        [['role' => 'user', 'content' => 'hi']],
        systemPrompt: 'You are a pool assistant.',
    );

    expect($result)->toBe('Hello from GPT.');
    Http::assertSent(fn ($request) => $request->url() === 'https://api.openai.com/v1/chat/completions'
        && $request['messages'][0] === ['role' => 'system', 'content' => 'You are a pool assistant.']
        && $request['messages'][1]['content'] === 'hi');
});

test('an OpenAI tool call has its JSON-string arguments decoded', function () {
    Http::fake(['api.openai.com/*' => Http::response([
        'choices' => [['message' => ['tool_calls' => [
            ['function' => ['name' => 'lookup_pool', 'arguments' => '{"name":"Smith Pool"}']],
        ]]]],
    ], 200)]);

    $result = $this->ai->chat('openai', 'sk-o', 'gpt-4o-mini', [['role' => 'user', 'content' => 'find it']]);

    expect($result)->toBe([
        'tool_call' => ['name' => 'lookup_pool', 'arguments' => ['name' => 'Smith Pool']],
    ]);
});
