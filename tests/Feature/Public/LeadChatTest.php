<?php

declare(strict_types=1);

use App\Models\AiUsage;
use App\Models\ChatMessage;
use App\Models\ChatSession;
use App\Models\Lead;
use App\Models\Tenant;
use App\Models\TenantSetting;
use Illuminate\Support\Facades\Http;

/**
 * A company website's chatbot, with the AI provider faked: replies, capturing
 * a lead, resuming a conversation, card numbers, what's charged, and the share
 * of the allowance kept for staff.
 */
beforeEach(function () {
    config(['ai.default_provider' => 'anthropic', 'ai.platform_keys.anthropic' => 'sk-test-key', 'ai.models.anthropic' => 'claude-haiku-4-5']);
    $this->tenant = Tenant::factory()->create(['slug' => 'acme']);
});

/** A faked Anthropic reply: text, or a capture_lead call. */
function siteReply(?string $text = null, ?array $lead = null, array $usage = ['input_tokens' => 800, 'output_tokens' => 100]): array
{
    return ['content' => [$lead !== null
        ? ['type' => 'tool_use', 'id' => 'tu_lead', 'name' => 'capture_lead', 'input' => $lead]
        : ['type' => 'text', 'text' => $text]], 'usage' => $usage];
}

function leadsFor(Tenant $tenant): int
{
    return Lead::query()->withoutGlobalScopes()->where('tenant_id', $tenant->id)->count();
}

test('public chat returns a graceful message when AI is not configured', function () {
    config(['ai.platform_keys.anthropic' => '']);
    Http::fake();

    $this->postJson('/public/acme/chat', ['message' => 'Do you service salt pools?'])
        ->assertOk()
        ->assertJsonPath('unavailable', true)
        ->assertJsonStructure(['reply']);

    expect(ChatSession::query()->count())->toBe(0); // no session/usage burned when unavailable
    Http::assertNothingSent();
});

test('public chat captures a lead when the model calls the capture_lead tool', function () {
    Http::fake(['api.anthropic.com/*' => Http::response(siteReply(lead: ['name' => 'Jane Visitor', 'email' => 'jane@example.test', 'summary' => 'Wants weekly service for a salt pool.']))]);

    $res = $this->postJson('/public/acme/chat', ['message' => 'I want weekly service, I am Jane, jane@example.test'])
        ->assertOk()
        ->assertJsonPath('unavailable', null);

    expect($res->json('reply'))->toContain('Jane')
        ->and($res->json('visitor_token'))->not->toBeEmpty();

    $lead = Lead::query()->withoutGlobalScopes()->where('tenant_id', $this->tenant->id)->where('source', 'chatbot')->firstOrFail();
    expect($lead->name)->toBe('Jane Visitor')->and($lead->email)->toBe('jane@example.test');
});

test('public chat resumes the same session via the visitor token, starting the history on a question', function () {
    Http::fake(['api.anthropic.com/*' => Http::response(siteReply('Happy to help!'))]);

    $token = $this->postJson('/public/acme/chat', ['message' => 'hello'])->assertOk()->json('visitor_token');
    $this->postJson('/public/acme/chat', ['message' => 'second', 'visitor_token' => $token])
        ->assertOk()
        ->assertJsonPath('visitor_token', $token);

    $second = Http::recorded()->map(fn (array $pair) => $pair[0]->data())->values()[1];
    expect(ChatSession::query()->count())->toBe(1) // one session reused, not two
        ->and(collect($second['messages'])->pluck('role')->all())->toBe(['user', 'assistant', 'user'])
        ->and($second['system'][0]['cache_control'])->toBe(['type' => 'ephemeral']);
});

test('a card number a visitor types is blanked before it is saved, sent or shown', function () {
    Http::fake(['api.anthropic.com/*' => Http::response(siteReply('Please never share card details in a chat.'))]);

    $this->postJson('/public/acme/chat', ['message' => 'Bill my card 4242 4242 4242 4242'])
        ->assertOk()
        ->assertJsonPath('message', 'Bill my card [card number removed]');

    expect(ChatMessage::query()->where('role', 'user')->value('content'))->toBe('Bill my card [card number removed]')
        ->and(Http::recorded()->first()[0]->body())->not->toContain('4242');
});

test('an answered message is charged with its cost; a failed one is not', function () {
    Http::fake(['api.anthropic.com/*' => Http::sequence()
        ->push(siteReply('Hi there!'))
        ->push(['error' => ['message' => 'overloaded']], 529)]);

    $this->postJson('/public/acme/chat', ['message' => 'hi'])->assertOk();
    $this->postJson('/public/acme/chat', ['message' => 'hi again'])->assertOk()->assertJsonPath('reply', fn (string $r): bool => str_contains($r, 'isn’t available'));

    $usage = AiUsage::query()->withoutGlobalScopes()->where('tenant_id', $this->tenant->id)->sole();
    expect((int) $usage->messages)->toBe(1)
        ->and((int) $usage->getAttribute('tokens'))->toBe(900)
        ->and((float) $usage->getAttribute('cost'))->toBeGreaterThan(0.0);
});

test('website visitors can\'t use the share of the allowance kept for staff', function () {
    TenantSetting::setFor($this->tenant->id, 'ai_monthly_quota', '10');
    (new AiUsage)->forceFill(['tenant_id' => $this->tenant->id, 'period' => now()->format('Y-m'), 'messages' => 8])->save();
    Http::fake();

    $this->postJson('/public/acme/chat', ['message' => 'hello'])->assertOk()->assertJsonPath('unavailable', true);

    Http::assertNothingSent();
});

test('the chat assistant cannot save a lead for a visitor already waiting on three replies', function () {
    app()->instance('tenant_id', $this->tenant->id);
    foreach (range(1, 3) as $i) {
        Lead::create(['name' => 'Jane Visitor', 'email' => 'jane@example.test', 'source' => 'contact']);
    }
    app()->forgetInstance('tenant_id');
    Http::fake(['api.anthropic.com/*' => Http::response(siteReply(lead: ['name' => 'Jane Visitor', 'email' => 'jane@example.test', 'summary' => 'Still waiting to hear back.']))]);

    $res = $this->postJson('/public/acme/chat', ['message' => 'Have you got my messages? jane@example.test'])->assertOk();

    expect(leadsFor($this->tenant))->toBe(3)
        ->and($res->json('reply'))->toContain('wait for our reply')->not->toContain('passed your details');
});

test('the chat assistant does not claim to pass on a visitor it has no name for', function () {
    Http::fake(['api.anthropic.com/*' => Http::response(siteReply(lead: ['name' => ' ', 'email' => 'jane@example.test']))]);

    $res = $this->postJson('/public/acme/chat', ['message' => 'Call me at jane@example.test'])->assertOk();

    expect(leadsFor($this->tenant))->toBe(0)
        ->and($res->json('reply'))->toContain('your name')->not->toContain('passed your details');
});
