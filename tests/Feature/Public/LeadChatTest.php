<?php

declare(strict_types=1);

use App\Models\ChatSession;
use App\Models\Lead;
use App\Models\Tenant;
use App\Services\ClaudeService;

test('public chat returns a graceful message when AI is not configured', function () {
    $tenant = Tenant::factory()->create(['slug' => 'acme']);

    $this->postJson('/public/acme/chat', ['message' => 'Do you service salt pools?'])
        ->assertOk()
        ->assertJsonPath('unavailable', true)
        ->assertJsonStructure(['reply']);

    expect(ChatSession::query()->count())->toBe(0); // no session/usage burned when unavailable
});

test('public chat captures a lead when the model calls the capture_lead tool', function () {
    config([
        'ai.default_provider' => 'anthropic',
        'ai.platform_keys.anthropic' => 'sk-test-key',
        'ai.models.anthropic' => 'claude-test',
    ]);
    $tenant = Tenant::factory()->create(['slug' => 'acme']);

    // The AI decides to capture the visitor as a lead.
    $this->mock(ClaudeService::class)
        ->shouldReceive('chat')
        ->andReturn(['tool_call' => ['name' => 'capture_lead', 'arguments' => [
            'name' => 'Jane Visitor',
            'email' => 'jane@example.test',
            'summary' => 'Wants weekly service for a salt pool.',
        ]]]);

    $res = $this->postJson('/public/acme/chat', ['message' => 'I want weekly service, I am Jane, jane@example.test'])
        ->assertOk()
        ->assertJsonPath('unavailable', null);

    expect($res->json('reply'))->toContain('Jane')
        ->and($res->json('visitor_token'))->not->toBeEmpty();

    app()->instance('tenant_id', $tenant->id);
    $lead = Lead::query()->where('source', 'chatbot')->firstOrFail();
    expect($lead->name)->toBe('Jane Visitor')->and($lead->email)->toBe('jane@example.test');
});

test('public chat resumes the same session via the visitor token', function () {
    config(['ai.default_provider' => 'anthropic', 'ai.platform_keys.anthropic' => 'sk-test-key', 'ai.models.anthropic' => 'claude-test']);
    Tenant::factory()->create(['slug' => 'acme']);

    $this->mock(ClaudeService::class)->shouldReceive('chat')->andReturn('Happy to help!');

    $first = $this->postJson('/public/acme/chat', ['message' => 'hello'])->assertOk();
    $token = $first->json('visitor_token');

    $this->postJson('/public/acme/chat', ['message' => 'second', 'visitor_token' => $token])
        ->assertOk()
        ->assertJsonPath('visitor_token', $token);

    expect(ChatSession::query()->count())->toBe(1); // one session reused, not two
});

test('the chat assistant cannot save a lead for a visitor already waiting on three replies', function () {
    config([
        'ai.default_provider' => 'anthropic',
        'ai.platform_keys.anthropic' => 'sk-test-key',
        'ai.models.anthropic' => 'claude-test',
    ]);
    $tenant = Tenant::factory()->create(['slug' => 'acme']);
    app()->instance('tenant_id', $tenant->id);
    foreach (range(1, 3) as $i) {
        Lead::create(['name' => 'Jane Visitor', 'email' => 'jane@example.test', 'source' => 'contact']);
    }
    app()->forgetInstance('tenant_id');

    $this->mock(ClaudeService::class)
        ->shouldReceive('chat')
        ->andReturn(['tool_call' => ['name' => 'capture_lead', 'arguments' => [
            'name' => 'Jane Visitor',
            'email' => 'jane@example.test',
            'summary' => 'Still waiting to hear back.',
        ]]]);

    $res = $this->postJson('/public/acme/chat', ['message' => 'Have you got my messages? jane@example.test'])->assertOk();

    expect(Lead::query()->withoutGlobalScopes()->where('tenant_id', $tenant->id)->count())->toBe(3)
        ->and($res->json('reply'))->toContain('wait for our reply')->not->toContain('passed your details');
});

test('the chat assistant does not claim to pass on a visitor it has no name for', function () {
    config([
        'ai.default_provider' => 'anthropic',
        'ai.platform_keys.anthropic' => 'sk-test-key',
        'ai.models.anthropic' => 'claude-test',
    ]);
    $tenant = Tenant::factory()->create(['slug' => 'acme']);

    $this->mock(ClaudeService::class)
        ->shouldReceive('chat')
        ->andReturn(['tool_call' => ['name' => 'capture_lead', 'arguments' => ['name' => ' ', 'email' => 'jane@example.test']]]);

    $res = $this->postJson('/public/acme/chat', ['message' => 'Call me at jane@example.test'])->assertOk();

    expect(Lead::query()->withoutGlobalScopes()->where('tenant_id', $tenant->id)->count())->toBe(0)
        ->and($res->json('reply'))->toContain('your name')->not->toContain('passed your details');
});
