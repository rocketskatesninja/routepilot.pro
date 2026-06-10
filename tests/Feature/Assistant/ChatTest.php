<?php

declare(strict_types=1);

use App\Models\ChatMessage;
use App\Models\Customer;
use App\Models\Pool;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Facades\Http;

/**
 * The assistant chat endpoint: text replies, the tool-call round-trip, and
 * the no-key path — all with the AI provider faked.
 */
beforeEach(function () {
    $this->tenant = Tenant::factory()->create();
    $this->admin = User::factory()->for($this->tenant)->create(); // tenant_admin
    config(['ai.platform_keys.anthropic' => 'sk-test', 'ai.models.anthropic' => 'claude-haiku-4-5']);
});

test('a text reply is returned and persisted', function () {
    Http::fake(['api.anthropic.com/*' => Http::response(['content' => [['type' => 'text', 'text' => 'Your routes look great.']]], 200)]);

    $this->actingAs($this->admin)
        ->postJson('/assistant/send', ['message' => 'How do my routes look?'])
        ->assertOk()
        ->assertJsonPath('reply', 'Your routes look great.');

    expect(ChatMessage::query()->where('role', 'user')->where('content', 'How do my routes look?')->exists())->toBeTrue();
    expect(ChatMessage::query()->where('role', 'assistant')->where('content', 'Your routes look great.')->exists())->toBeTrue();
});

test('a tool call executes then the model summarizes', function () {
    $customer = Customer::factory()->for($this->tenant)->create(['first_name' => 'Jane', 'last_name' => 'Doe']);
    Pool::factory()->for($this->tenant)->for($customer)->create(['name' => 'Main Pool']);

    Http::fake(['api.anthropic.com/*' => Http::sequence()
        ->push(['content' => [['type' => 'tool_use', 'name' => 'lookup_customer', 'input' => ['name' => 'Jane']]]], 200)
        ->push(['content' => [['type' => 'text', 'text' => 'I found Jane Doe with one pool.']]], 200)]);

    $this->actingAs($this->admin)
        ->postJson('/assistant/send', ['message' => 'Tell me about Jane'])
        ->assertOk()
        ->assertJsonPath('reply', 'I found Jane Doe with one pool.');

    // The tool execution was recorded as a visible assistant message.
    expect(ChatMessage::query()->where('content', 'like', '%lookup_customer%')->exists())->toBeTrue();
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
