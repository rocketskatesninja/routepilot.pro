<?php

declare(strict_types=1);

use App\Services\AiTurn;
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
function anthropicReply(array $content, array $usage = []): PromiseInterface
{
    return Http::response(['content' => $content, 'usage' => $usage], 200);
}

test('parses an Anthropic text reply', function () {
    Http::fake(['api.anthropic.com/*' => anthropicReply([
        ['type' => 'text', 'text' => 'Your pH looks high.'],
    ])]);

    $turn = $this->ai->turn('anthropic', 'sk-key', 'claude-haiku-4-5', '', '', [['role' => 'user', 'content' => 'how is my pool?']]);

    expect($turn->text)->toBe('Your pH looks high.')->and($turn->toolCalls)->toBe([])->and($turn->failed)->toBeFalse();

    Http::assertSent(function ($request) {
        return $request->url() === 'https://api.anthropic.com/v1/messages'
            && $request['model'] === 'claude-haiku-4-5'
            && $request->hasHeader('x-api-key', 'sk-key')
            && ! isset($request['thinking']) // off by default
            && ! isset($request['system']); // nothing sent when there's no prompt
    });
});

test('a tool_use block becomes a tool call (Anthropic), with tools in its input_schema shape', function () {
    Http::fake(['api.anthropic.com/*' => anthropicReply([
        ['type' => 'tool_use', 'id' => 'tu_1', 'name' => 'lookup_chemistry', 'input' => ['pool' => 'Smith', 'days' => 30]],
    ])]);

    $turn = $this->ai->turn('anthropic', 'sk-key', 'claude-haiku-4-5', '', '',
        [['role' => 'user', 'content' => 'last readings for Smith?']],
        [['name' => 'lookup_chemistry', 'description' => 'Look up readings', 'parameters' => ['type' => 'object']]],
    );

    expect($turn->toolCalls)->toBe([['id' => 'tu_1', 'name' => 'lookup_chemistry', 'arguments' => ['pool' => 'Smith', 'days' => 30]]]);
    Http::assertSent(fn ($request) => $request['tools'][0]['input_schema'] === ['type' => 'object']
        && ! isset($request['tools'][0]['parameters']));
});

test('thinking blocks aren\'t text; the text and the tool call both come through', function () {
    Http::fake(['api.anthropic.com/*' => anthropicReply([
        ['type' => 'thinking', 'thinking' => ''],
        ['type' => 'text', 'text' => 'some preamble'],
        ['type' => 'tool_use', 'id' => 'tu_2', 'name' => 'lookup_pool', 'input' => ['pool_id' => 5]],
    ])]);

    $turn = $this->ai->turn('anthropic', 'sk-key', 'claude-haiku-4-5', '', '', [['role' => 'user', 'content' => 'find it']]);

    expect($turn->text)->toBe('some preamble')
        ->and($turn->toolCalls)->toBe([['id' => 'tu_2', 'name' => 'lookup_pool', 'arguments' => ['pool_id' => 5]]]);
});

test('adaptive thinking is opt-in and adds the request param', function () {
    Http::fake(['api.anthropic.com/*' => anthropicReply([['type' => 'text', 'text' => 'ok']])]);

    $this->ai->turn('anthropic', 'sk-key', 'claude-sonnet-4-6', '', '', [['role' => 'user', 'content' => 'think hard']], adaptiveThinking: true);

    Http::assertSent(fn ($request) => $request['thinking'] === ['type' => 'adaptive']);
});

test('an API error returns a friendly message, never the provider\'s own text', function () {
    Http::fake(['api.anthropic.com/*' => Http::response(['error' => ['message' => 'Your credit balance is too low']], 400)]);

    $turn = $this->ai->turn('anthropic', 'sk-key', 'claude-haiku-4-5', '', '', [['role' => 'user', 'content' => 'hi']]);

    expect($turn->failed)->toBeTrue()->and($turn->text)->toBe(AiTurn::UNAVAILABLE)->and($turn->tokens)->toBe(0);
});

test('a turn returns every tool call, the reply to send back, and what it cost (Anthropic)', function () {
    config(['ai.prices.claude-haiku-4-5' => ['input' => 1.0, 'output' => 5.0, 'cached_input' => 0.1]]);
    Http::fake(['api.anthropic.com/*' => anthropicReply([
        ['type' => 'thinking', 'thinking' => 'hmm', 'signature' => 'sig'],
        ['type' => 'tool_use', 'id' => 'a', 'name' => 'lookup_pool', 'input' => ['pool_id' => 1]],
        ['type' => 'tool_use', 'id' => 'b', 'name' => 'lookup_customer', 'input' => ['name' => 'Jo']],
    ], ['input_tokens' => 100, 'cache_creation_input_tokens' => 1000, 'cache_read_input_tokens' => 2000, 'output_tokens' => 50])]);

    $turn = $this->ai->turn('anthropic', 'sk', 'claude-haiku-4-5', 'stable rules', 'live data', [['role' => 'user', 'content' => 'hi']],
        [['name' => 'lookup_pool', 'description' => 'd', 'parameters' => ['type' => 'object']]], allowTools: false);

    expect(collect($turn->toolCalls)->pluck('id')->all())->toBe(['a', 'b'])
        ->and($turn->message['content'][0]['type'])->toBe('thinking') // sent back unmodified
        ->and($turn->tokens)->toBe(3150)
        ->and(round($turn->cost, 6))->toBe(round((100 * 1 + 1000 * 1.25 + 2000 * 0.1 + 50 * 5) / 1_000_000, 6))
        ->and($this->ai->toolResults('anthropic', [['id' => 'a', 'content' => 'x']]))->toBe([['role' => 'user', 'content' => [['type' => 'tool_result', 'tool_use_id' => 'a', 'content' => 'x']]]]);

    Http::assertSent(fn ($request) => $request['system'][0] === ['type' => 'text', 'text' => 'stable rules', 'cache_control' => ['type' => 'ephemeral']]
        && $request['system'][1] === ['type' => 'text', 'text' => 'live data']
        && $request['tool_choice'] === ['type' => 'none']);
});

test('a turn returns every tool call and its echo (OpenAI)', function () {
    $calls = [
        ['id' => 'c1', 'type' => 'function', 'function' => ['name' => 'lookup_pool', 'arguments' => '{"pool_id":1}']],
        ['id' => 'c2', 'type' => 'function', 'function' => ['name' => 'lookup_customer', 'arguments' => '{"name":"Jo"}']],
    ];
    Http::fake(['api.openai.com/*' => Http::response(['choices' => [['message' => ['content' => null, 'tool_calls' => $calls]]], 'usage' => ['prompt_tokens' => 10, 'completion_tokens' => 5]])]);

    $turn = $this->ai->turn('openai', 'sk', 'gpt-4o-mini', 'rules', 'data', [['role' => 'user', 'content' => 'hi']]);

    expect(collect($turn->toolCalls)->pluck('arguments')->all())->toBe([['pool_id' => 1], ['name' => 'Jo']])
        ->and($turn->message)->toBe(['role' => 'assistant', 'content' => null, 'tool_calls' => $calls])
        ->and($turn->tokens)->toBe(15)
        ->and($this->ai->toolResults('openai', [['id' => 'c1', 'content' => 'x'], ['id' => 'c2', 'content' => 'y']]))
        ->toBe([['role' => 'tool', 'tool_call_id' => 'c1', 'content' => 'x'], ['role' => 'tool', 'tool_call_id' => 'c2', 'content' => 'y']]);

    Http::assertSent(fn ($request) => $request['messages'][0] === ['role' => 'system', 'content' => "rules\n\ndata"]);
});

test('parses an OpenAI text reply and puts the system prompt first', function () {
    Http::fake(['api.openai.com/*' => Http::response([
        'choices' => [['message' => ['content' => 'Hello from GPT.']]],
    ], 200)]);

    $turn = $this->ai->turn('openai', 'sk-o', 'gpt-4o-mini', 'You are a pool assistant.', '', [['role' => 'user', 'content' => 'hi']]);

    expect($turn->text)->toBe('Hello from GPT.');
    Http::assertSent(fn ($request) => $request->url() === 'https://api.openai.com/v1/chat/completions'
        && $request['messages'][0] === ['role' => 'system', 'content' => 'You are a pool assistant.']
        && $request['messages'][1]['content'] === 'hi');
});

test('an OpenAI tool call has its JSON-string arguments decoded', function () {
    Http::fake(['api.openai.com/*' => Http::response([
        'choices' => [['message' => ['tool_calls' => [
            ['id' => 'c9', 'type' => 'function', 'function' => ['name' => 'lookup_pool', 'arguments' => '{"name":"Smith Pool"}']],
        ]]]],
    ], 200)]);

    $turn = $this->ai->turn('openai', 'sk-o', 'gpt-4o-mini', '', '', [['role' => 'user', 'content' => 'find it']]);

    expect($turn->toolCalls)->toBe([['id' => 'c9', 'name' => 'lookup_pool', 'arguments' => ['name' => 'Smith Pool']]]);
});

test('OpenAI models that need it get reasoning switched off for tools, and dotted names find their price', function () {
    config(['ai.openai_reasoning_effort' => ['gpt-6' => 'none'], 'ai.prices' => ['gpt-5.4-mini' => ['input' => 1.0, 'output' => 2.0]]]);
    Http::fake(['api.openai.com/*' => Http::response(['choices' => [['message' => ['content' => 'ok']]], 'usage' => ['prompt_tokens' => 1000, 'completion_tokens' => 500]])]);

    $this->ai->turn('openai', 'sk', 'gpt-6-sol', '', '', [['role' => 'user', 'content' => 'hi']]);
    $turn = $this->ai->turn('openai', 'sk', 'gpt-5.4-mini', '', '', [['role' => 'user', 'content' => 'hi']]);

    $sent = Http::recorded()->map(fn (array $pair) => $pair[0]->data())->values();
    expect($sent[0]['reasoning_effort'])->toBe('none')
        ->and($sent[1])->not->toHaveKey('reasoning_effort')
        ->and($sent[1])->toHaveKey('max_completion_tokens')->not->toHaveKey('max_tokens')
        ->and($turn->cost)->toBe((1000 * 1.0 + 500 * 2.0) / 1_000_000);
});
