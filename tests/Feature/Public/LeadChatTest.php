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
