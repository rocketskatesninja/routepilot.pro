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
        ->and($turn->messages[0]['content'][0]['type'])->toBe('thinking') // sent back unmodified
        ->and($turn->tokens)->toBe(3150)
        ->and(round($turn->cost, 6))->toBe(round((100 * 1 + 1000 * 1.25 + 2000 * 0.1 + 50 * 5) / 1_000_000, 6))
        ->and($this->ai->toolResults('anthropic', [['id' => 'a', 'content' => 'x']]))->toBe([['role' => 'user', 'content' => [['type' => 'tool_result', 'tool_use_id' => 'a', 'content' => 'x']]]]);

    Http::assertSent(fn ($request) => $request['system'][0] === ['type' => 'text', 'text' => 'stable rules', 'cache_control' => ['type' => 'ephemeral']]
        && $request['system'][1] === ['type' => 'text', 'text' => 'live data']
        && $request['tool_choice'] === ['type' => 'none']);
});

/** A Responses API reply: its output items and usage. */
function openaiReply(array $output, array $usage = []): PromiseInterface
{
    return Http::response(['output' => $output, 'usage' => $usage], 200);
}

test('a turn returns every tool call and its items to send back (OpenAI)', function () {
    $output = [
        ['type' => 'reasoning', 'id' => 'rs_1', 'encrypted_content' => 'opaque', 'summary' => []],
        ['type' => 'function_call', 'id' => 'fc_1', 'call_id' => 'c1', 'name' => 'lookup_pool', 'arguments' => '{"pool_id":1}'],
        ['type' => 'function_call', 'id' => 'fc_2', 'call_id' => 'c2', 'name' => 'lookup_customer', 'arguments' => '{"name":"Jo"}'],
    ];
    Http::fake(['api.openai.com/*' => openaiReply($output, ['input_tokens' => 10, 'output_tokens' => 5])]);

    $turn = $this->ai->turn('openai', 'sk', 'gpt-6-luna', 'rules', 'data', [['role' => 'user', 'content' => 'hi']],
        [['name' => 'lookup_pool', 'description' => 'd', 'parameters' => ['type' => 'object']]], allowTools: false);

    expect($turn->toolCalls)->toBe([
        ['id' => 'c1', 'name' => 'lookup_pool', 'arguments' => ['pool_id' => 1]],
        ['id' => 'c2', 'name' => 'lookup_customer', 'arguments' => ['name' => 'Jo']],
    ])
        ->and($turn->messages)->toBe($output) // reasoning included, sent back unmodified
        ->and($turn->tokens)->toBe(15)
        ->and($this->ai->toolResults('openai', [['id' => 'c1', 'content' => 'x'], ['id' => 'c2', 'content' => 'y']]))
        ->toBe([['type' => 'function_call_output', 'call_id' => 'c1', 'output' => 'x'], ['type' => 'function_call_output', 'call_id' => 'c2', 'output' => 'y']]);

    Http::assertSent(fn ($request) => $request->url() === 'https://api.openai.com/v1/responses'
        && $request['store'] === false
        && $request['instructions'] === "rules\n\ndata"
        && $request['input'] === [['role' => 'user', 'content' => 'hi']]
        && $request['tools'] === [['type' => 'function', 'name' => 'lookup_pool', 'description' => 'd', 'parameters' => ['type' => 'object'], 'strict' => false]]
        && $request['tool_choice'] === 'none');
});

test('parses an OpenAI text reply', function () {
    Http::fake(['api.openai.com/*' => openaiReply([
        ['type' => 'message', 'role' => 'assistant', 'content' => [['type' => 'output_text', 'text' => 'Hello from GPT.', 'annotations' => []]]],
    ])]);

    $turn = $this->ai->turn('openai', 'sk-o', 'gpt-4o-mini', '', '', [['role' => 'user', 'content' => 'hi']]);

    expect($turn->text)->toBe('Hello from GPT.')->and($turn->toolCalls)->toBe([]);
    Http::assertSent(fn ($request) => $request->hasHeader('Authorization', 'Bearer sk-o')
        && ! isset($request['instructions']));
});

test('reasoning models get the reasoning setting and their reasoning back; older models get neither', function () {
    config(['ai.openai_reasoning_effort' => 'low', 'ai.prices' => ['gpt-5.4-mini' => ['input' => 1.0, 'output' => 2.0, 'cached_input' => 0.5]]]);
    Http::fake(['api.openai.com/*' => openaiReply(
        [['type' => 'message', 'content' => [['type' => 'output_text', 'text' => 'ok']]]],
        ['input_tokens' => 1000, 'input_tokens_details' => ['cached_tokens' => 400], 'output_tokens' => 500],
    )]);

    $turn = $this->ai->turn('openai', 'sk', 'gpt-5.4-mini', '', '', [['role' => 'user', 'content' => 'hi']]);
    $this->ai->turn('openai', 'sk', 'o4-mini', '', '', [['role' => 'user', 'content' => 'hi']]);
    $this->ai->turn('openai', 'sk', 'gpt-4o-mini', '', '', [['role' => 'user', 'content' => 'hi']]);

    $sent = Http::recorded()->map(fn (array $pair) => $pair[0]->data())->values();
    expect($sent[0]['reasoning'])->toBe(['effort' => 'low'])
        ->and($sent[0]['include'])->toBe(['reasoning.encrypted_content'])
        ->and($sent[0])->toHaveKey('max_output_tokens')
        ->and($sent[1]['reasoning'])->toBe(['effort' => 'low'])
        ->and($sent[2])->not->toHaveKey('reasoning')->not->toHaveKey('include')
        ->and($turn->cost)->toBe((600 * 1.0 + 400 * 0.5 + 500 * 2.0) / 1_000_000); // dotted names find their price
});

test('a model that refuses the reasoning setting is asked again at its own default', function () {
    config(['ai.openai_reasoning_effort' => 'none']);
    Http::fake(['api.openai.com/*' => Http::sequence()
        ->push(['error' => ['message' => "Unsupported value: 'reasoning.effort' does not support 'none' with this model."]], 400)
        ->push(['output' => [['type' => 'message', 'content' => [['type' => 'output_text', 'text' => 'ok']]]]])]);

    $turn = $this->ai->turn('openai', 'sk', 'gpt-6.1-sol', '', '', [['role' => 'user', 'content' => 'hi']]);

    $sent = Http::recorded()->map(fn (array $pair) => $pair[0]->data())->values();
    expect($turn->text)->toBe('ok')
        ->and($sent)->toHaveCount(2)
        ->and($sent[1])->not->toHaveKey('reasoning')
        ->and($sent[1]['include'])->toBe(['reasoning.encrypted_content']);
});

test('any other OpenAI error fails once, without a retry or its text', function () {
    Http::fake(['api.openai.com/*' => Http::response(['error' => ['message' => 'Incorrect API key provided']], 401)]);

    $turn = $this->ai->turn('openai', 'sk', 'gpt-6-luna', '', '', [['role' => 'user', 'content' => 'hi']]);

    expect($turn->failed)->toBeTrue()->and($turn->text)->toBe(AiTurn::UNAVAILABLE);
    Http::assertSentCount(1);
});
