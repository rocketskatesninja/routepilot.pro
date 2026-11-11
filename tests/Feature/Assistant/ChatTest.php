<?php

declare(strict_types=1);

use App\Models\AiUsage;
use App\Models\ChatMessage;
use App\Models\ChatSession;
use App\Models\Customer;
use App\Models\Pool;
use App\Models\Tenant;
use App\Models\TenantSetting;
use App\Models\User;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;

/**
 * The assistant chat endpoint, with the AI provider faked: replies, look-ups
 * that go round as proper tool results (Anthropic and OpenAI), the round limit,
 * what's saved, card numbers, limits, and keeping chats apart.
 */
beforeEach(function () {
    $this->tenant = Tenant::factory()->create();
    $this->admin = User::factory()->for($this->tenant)->create(); // tenant_admin
    config(['ai.platform_keys.anthropic' => 'sk-test', 'ai.models.anthropic' => 'claude-haiku-4-5']);
});

/** @param  list<array<string, mixed>>  $content */
function anthropic(array $content, array $usage = []): array
{
    return ['content' => $content, 'usage' => $usage];
}

/** @return list<Request> */
function aiRequests(): array
{
    return Http::recorded()->map(fn (array $pair) => $pair[0])->values()->all();
}

test('a text reply is returned and persisted', function () {
    Http::fake(['api.anthropic.com/*' => Http::response(anthropic([['type' => 'text', 'text' => 'Your routes look great.']]))]);

    $this->actingAs($this->admin)
        ->postJson('/assistant/send', ['message' => 'How do my routes look?'])
        ->assertOk()
        ->assertJsonPath('reply', 'Your routes look great.');

    expect(ChatMessage::query()->where('role', 'user')->where('content', 'How do my routes look?')->exists())->toBeTrue()
        ->and(ChatMessage::query()->where('role', 'assistant')->where('content', 'Your routes look great.')->exists())->toBeTrue();
});

test('look-ups go back to the model as tool results, and only the question and answer are saved', function () {
    $customer = Customer::factory()->for($this->tenant)->create(['first_name' => 'Jane', 'last_name' => 'Doe']);
    Pool::factory()->for($this->tenant)->for($customer)->create(['name' => 'Main Pool']);

    Http::fake(['api.anthropic.com/*' => Http::sequence()
        ->push(anthropic([
            ['type' => 'text', 'text' => 'Let me look.'],
            ['type' => 'tool_use', 'id' => 'tu_1', 'name' => 'lookup_customer', 'input' => ['name' => 'Jane']],
            ['type' => 'tool_use', 'id' => 'tu_2', 'name' => 'lookup_pool', 'input' => ['customer_name' => 'Jane']],
        ]))
        ->push(anthropic([['type' => 'text', 'text' => 'Jane Doe has one pool, Main Pool.']]))]);

    $this->actingAs($this->admin)
        ->postJson('/assistant/send', ['message' => 'Tell me about Jane'])
        ->assertOk()
        ->assertJsonPath('reply', 'Jane Doe has one pool, Main Pool.');

    $second = aiRequests()[1]->data()['messages'];
    $results = end($second)['content'];
    expect(prev($second)['role'])->toBe('assistant')
        ->and(collect($results)->pluck('tool_use_id')->all())->toBe(['tu_1', 'tu_2'])
        ->and($results[0]['type'])->toBe('tool_result')
        ->and($results[0]['content'])->toContain('Jane Doe')
        ->and($results[1]['content'])->toContain('Main Pool')
        ->and(ChatMessage::query()->count())->toBe(2);
});

test('OpenAI look-ups go back as tool messages', function () {
    config(['ai.default_provider' => 'openai', 'ai.platform_keys.openai' => 'sk-o', 'ai.models.openai' => 'gpt-4o-mini']);
    Customer::factory()->for($this->tenant)->create(['first_name' => 'Jane', 'last_name' => 'Doe']);

    Http::fake(['api.openai.com/*' => Http::sequence()
        ->push(['choices' => [['message' => ['content' => null, 'tool_calls' => [
            ['id' => 'call_1', 'type' => 'function', 'function' => ['name' => 'lookup_customer', 'arguments' => '{"name":"Jane"}']],
        ]]]]])
        ->push(['choices' => [['message' => ['content' => 'Found Jane Doe.']]]])]);

    $this->actingAs($this->admin)->postJson('/assistant/send', ['message' => 'Jane?'])->assertJsonPath('reply', 'Found Jane Doe.');

    $messages = aiRequests()[1]->data()['messages'];
    expect($messages[0]['role'])->toBe('system')
        ->and($messages[count($messages) - 2]['tool_calls'][0]['id'])->toBe('call_1')
        ->and(end($messages))->toMatchArray(['role' => 'tool', 'tool_call_id' => 'call_1'])
        ->and(end($messages)['content'])->toContain('Jane Doe');
});

test('after a few rounds of look-ups the model has to answer', function () {
    Http::fake(['api.anthropic.com/*' => Http::response(anthropic([['type' => 'tool_use', 'id' => 'tu', 'name' => 'lookup_customer', 'input' => ['name' => 'all']]]))]);

    $reply = $this->actingAs($this->admin)->postJson('/assistant/send', ['message' => 'Loop forever'])->json('reply');

    $requests = aiRequests();
    expect($requests)->toHaveCount(4)
        ->and($requests[0]->data())->not->toHaveKey('tool_choice')
        ->and($requests[3]->data()['tool_choice'])->toBe(['type' => 'none'])
        ->and($reply)->toContain('couldn’t put an answer together');
});

test('a card number is blanked out before it is saved or sent', function () {
    Http::fake(['api.anthropic.com/*' => Http::response(anthropic([['type' => 'text', 'text' => 'Never type card details here.']]))]);

    $this->actingAs($this->admin)->postJson('/assistant/send', ['message' => 'Charge my card 4242 4242 4242 4242 please'])
        ->assertOk()->assertJsonPath('message', 'Charge my card [card number removed] please');

    expect(ChatMessage::query()->where('role', 'user')->value('content'))->toBe('Charge my card [card number removed] please')
        ->and(aiRequests()[0]->body())->not->toContain('4242');
});

test('the conversation sent to the model always starts on a question', function () {
    $session = ChatSession::create(['user_id' => $this->admin->id, 'context' => 'tenant_admin']);
    foreach (range(1, 21) as $i) {
        ChatMessage::create(['chat_session_id' => $session->id, 'role' => $i % 2 ? 'user' : 'assistant', 'content' => "message {$i}"]);
    }
    Http::fake(['api.anthropic.com/*' => Http::response(anthropic([['type' => 'text', 'text' => 'ok']]))]);

    $this->actingAs($this->admin)->postJson('/assistant/send', ['message' => 'next', 'session_id' => $session->id])->assertOk();

    $messages = aiRequests()[0]->data()['messages'];
    expect($messages[0]['role'])->toBe('user')
        ->and(end($messages)['content'])->toBe('next');
});

test('tokens and cost are recorded for the company', function () {
    Http::fake(['api.anthropic.com/*' => Http::response(anthropic([['type' => 'text', 'text' => 'ok']], ['input_tokens' => 1000, 'output_tokens' => 200]))]);

    $this->actingAs($this->admin)->postJson('/assistant/send', ['message' => 'hi'])->assertOk();

    $usage = AiUsage::query()->where('tenant_id', $this->tenant->id)->first();
    expect((int) $usage->messages)->toBe(1)
        ->and((int) $usage->getAttribute('tokens'))->toBe(1200)
        ->and((float) $usage->getAttribute('cost'))->toBe(0.002);
});

test('the office prompt has the guide and pool know-how but no customer list', function () {
    Customer::factory()->for($this->tenant)->create(['first_name' => 'Secret', 'email' => 'secret@example.com']);
    Http::fake(['api.anthropic.com/*' => Http::response(anthropic([['type' => 'text', 'text' => 'ok']]))]);

    $this->actingAs($this->admin)->postJson('/assistant/send', ['message' => 'hi'])->assertOk();

    $system = collect(aiRequests()[0]->data()['system'])->pluck('text')->join("\n");
    expect($system)->toContain('RoutePilot for the office')
        ->toContain('POOL AND SPA EXPERTISE')
        ->toContain('cannot change anything')
        ->not->toContain('secret@example.com')
        ->and(aiRequests()[0]->data()['system'][0]['cache_control'])->toBe(['type' => 'ephemeral']);
});

test('a customer is only offered their own look-ups, and office ones don\'t run for them', function () {
    $user = User::factory()->customer()->for($this->tenant)->create();
    Customer::factory()->for($this->tenant)->create(['user_id' => $user->id]);
    Customer::factory()->for($this->tenant)->create(['first_name' => 'Neighbour']);
    Http::fake(['api.anthropic.com/*' => Http::sequence()
        ->push(anthropic([['type' => 'tool_use', 'id' => 'tu', 'name' => 'lookup_customer', 'input' => ['name' => 'Neighbour']]]))
        ->push(anthropic([['type' => 'text', 'text' => 'I can only help with your own pool.']]))]);

    $this->actingAs($user)->postJson('/assistant/send', ['message' => 'Who lives next door?'])->assertOk();

    $first = aiRequests()[0]->data();
    $result = end(aiRequests()[1]->data()['messages'])['content'][0]['content'];
    expect(collect($first['tools'])->pluck('name')->all())->toBe(['my_service', 'my_visits', 'my_requests', 'my_balance', 'analyze_water'])
        ->and(collect($first['system'])->pluck('text')->join("\n"))->not->toContain('Neighbour')
        ->and($result)->toContain('no look-up called')
        ->and($result)->not->toContain('Neighbour');
});

test('customers have a daily limit', function () {
    config(['ai.daily_limits.customer' => 2]);
    $user = User::factory()->customer()->for($this->tenant)->create();
    Http::fake(['api.anthropic.com/*' => Http::response(anthropic([['type' => 'text', 'text' => 'ok']]))]);

    $this->actingAs($user)->postJson('/assistant/send', ['message' => 'one'])->assertOk();
    $this->actingAs($user)->postJson('/assistant/send', ['message' => 'two'])->assertOk();
    $this->actingAs($user)->postJson('/assistant/send', ['message' => 'three'])->assertStatus(429)->assertJsonPath('error', fn (string $e) => str_contains($e, 'tomorrow'));
});

test('customers can\'t use the last of the month\'s allowance, staff can', function () {
    TenantSetting::setFor($this->tenant->id, 'ai_monthly_quota', '10');
    (new AiUsage)->forceFill(['tenant_id' => $this->tenant->id, 'period' => now()->format('Y-m'), 'messages' => 9])->save();
    $user = User::factory()->customer()->for($this->tenant)->create();
    Http::fake(['api.anthropic.com/*' => Http::response(anthropic([['type' => 'text', 'text' => 'ok']]))]);

    $this->actingAs($user)->postJson('/assistant/send', ['message' => 'hi'])->assertStatus(429);
    $this->actingAs($this->admin)->postJson('/assistant/send', ['message' => 'hi'])->assertOk();
});

test('chats while a super admin is signed in as someone are kept apart from theirs', function () {
    Http::fake(['api.anthropic.com/*' => Http::response(anthropic([['type' => 'text', 'text' => 'ok']]))]);
    $this->actingAs($this->admin)->postJson('/assistant/send', ['message' => 'my own question'])->assertOk();

    $this->actingAs($this->admin)->withSession(['impersonator_id' => 999])
        ->postJson('/assistant/send', ['message' => 'support checking'])->assertOk();
    $this->actingAs($this->admin)->withSession(['impersonator_id' => 999])->get('/assistant')
        ->assertInertia(fn ($page) => $page->where('messages.0.content', 'support checking'));

    expect(ChatSession::query()->where('context', 'support')->count())->toBe(1);
    $this->flushSession();
    $this->actingAs($this->admin)->get('/assistant')
        ->assertInertia(fn ($page) => $page->where('messages', fn ($m) => collect($m)->pluck('content')->doesntContain('support checking')));
});

test('New conversation starts an empty chat, and the last one can be continued', function () {
    $session = ChatSession::create(['user_id' => $this->admin->id, 'context' => 'tenant_admin']);
    ChatMessage::create(['chat_session_id' => $session->id, 'role' => 'user', 'content' => 'old question']);

    $this->actingAs($this->admin)->post('/assistant/new')->assertRedirect('/assistant');
    $this->actingAs($this->admin)->post('/assistant/new'); // an empty chat is reused, not stacked

    expect(ChatSession::query()->where('user_id', $this->admin->id)->count())->toBe(2);
    $this->actingAs($this->admin)->get('/assistant')->assertInertia(fn ($page) => $page->where('messages', [])->where('hasPrevious', true));
    $this->actingAs($this->admin)->get('/assistant?continue=1')->assertInertia(fn ($page) => $page->where('messages.0.content', 'old question'));
});

test('a chat quiet for 12 hours starts fresh when the page opens', function () {
    $session = ChatSession::create(['user_id' => $this->admin->id, 'context' => 'tenant_admin']);
    $old = ChatMessage::create(['chat_session_id' => $session->id, 'role' => 'user', 'content' => 'yesterday']);
    $old->forceFill(['created_at' => now()->subHours(13)])->save();

    $this->actingAs($this->admin)->get('/assistant')
        ->assertInertia(fn ($page) => $page->where('messages', [])->where('sessionId', null)->where('hasPrevious', true));
    $this->actingAs($this->admin)->get('/assistant?continue=1')
        ->assertInertia(fn ($page) => $page->where('messages.0.content', 'yesterday')->where('sessionId', $session->id));
});

test('the page loads the last 50 messages, and all of them on request', function () {
    $session = ChatSession::create(['user_id' => $this->admin->id, 'context' => 'tenant_admin']);
    foreach (range(1, 60) as $i) {
        ChatMessage::create(['chat_session_id' => $session->id, 'role' => $i % 2 ? 'user' : 'assistant', 'content' => "m{$i}"]);
    }

    $this->actingAs($this->admin)->get('/assistant')->assertInertia(fn ($page) => $page
        ->has('messages', 50)->where('messages.0.content', 'm11')->where('messages.49.content', 'm60')->where('hasEarlier', true));
    $this->actingAs($this->admin)->get('/assistant?continue=1&all=1')->assertInertia(fn ($page) => $page->has('messages', 60)->where('hasEarlier', false));
});

test('chats nobody has written in for 45 days are deleted, newer ones kept', function () {
    $old = ChatSession::create(['user_id' => $this->admin->id, 'context' => 'tenant_admin']);
    $old->forceFill(['created_at' => now()->subDays(50)])->save();
    ChatMessage::create(['chat_session_id' => $old->id, 'role' => 'user', 'content' => 'old'])->forceFill(['created_at' => now()->subDays(46)])->save();
    $kept = ChatSession::create(['user_id' => $this->admin->id, 'context' => 'tenant_admin']);
    $kept->forceFill(['created_at' => now()->subDays(50)])->save();
    ChatMessage::create(['chat_session_id' => $kept->id, 'role' => 'user', 'content' => 'recent']);

    $this->artisan('app:purge-retention')->assertSuccessful();

    expect(ChatSession::query()->find($old->id))->toBeNull()
        ->and(ChatMessage::query()->where('content', 'old')->exists())->toBeFalse()
        ->and(ChatSession::query()->find($kept->id))->not->toBeNull();
});

test('with no API key the assistant reports it is not configured', function () {
    config(['ai.platform_keys.anthropic' => '']);
    Http::fake();

    $this->actingAs($this->admin)
        ->postJson('/assistant/send', ['message' => 'hi'])
        ->assertOk()
        ->assertJsonPath('reply', fn (string $reply): bool => str_contains($reply, 'configured'));

    Http::assertNothingSent();
});

test('the send endpoint requires authentication', function () {
    $this->postJson('/assistant/send', ['message' => 'hi'])->assertUnauthorized();
});

test('each kind of account gets its own example questions, none of which changes anything', function () {
    $roles = [
        'tenant_admin' => $this->admin,
        'agent' => User::factory()->agent()->for($this->tenant)->create(),
        'customer' => User::factory()->customer()->for($this->tenant)->create(),
    ];
    foreach ($roles as $user) {
        $this->actingAs($user)->get('/assistant')->assertInertia(fn ($page) => $page
            ->where('suggestions', fn ($s) => count($s) >= 4 && count($s) <= 6
                && collect($s)->every(fn (string $q): bool => ! preg_match('/^(skip|reassign|move|cancel|delete|charge|remove)\b/i', $q)))
            ->where('intro', fn ($intro) => is_string($intro) && $intro !== ''));
    }
});
