<?php

declare(strict_types=1);

use App\Models\PlatformSetting;
use App\Models\Tenant;
use App\Models\TenantSetting;
use App\Models\User;
use App\Services\AiQuota;
use App\Services\PlatformAiSettings;
use Illuminate\Support\Facades\Crypt;

beforeEach(function () {
    $this->super = User::factory()->superAdmin()->create();
    $this->tenant = Tenant::factory()->create();
    $this->admin = User::factory()->for($this->tenant)->create(); // tenant_admin
});

test('the AI console is super-admin only and platform-level', function () {
    $this->actingAs($this->admin)->get('/platform/ai')->assertForbidden();
    $this->actingAs($this->super)->get('/platform/ai')
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('admin/Ai')->has('defaults')->has('keys')->missing('tenants'));
});

test('the super People screen carries per-tenant AI in the tenant detail', function () {
    TenantSetting::setFor($this->tenant->id, 'ai_monthly_quota', '900');

    $this->actingAs($this->super)
        ->get('/people?selected='.$this->tenant->id.'&selected_type=tenant')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('people/Platform')
            ->has('aiDefaultQuota')
            ->where('selected.type', 'tenant')
            ->where('selected.ai.quota', 900)
            ->where('selected.ai.enabled', true));
});

test('saving platform defaults persists provider, model and default quota', function () {
    $this->actingAs($this->super)->patch('/platform/ai', [
        'provider' => 'anthropic',
        'model' => 'claude-haiku-4-5',
        'default_quota' => 750,
    ])->assertRedirect();

    expect(PlatformSetting::get('ai_provider'))->toBe('anthropic')
        ->and(PlatformSetting::get('ai_model'))->toBe('claude-haiku-4-5')
        ->and(PlatformSetting::get('ai_default_quota'))->toBe('750')
        ->and(app(PlatformAiSettings::class)->defaultQuota())->toBe(750);
});

test('an API key is stored encrypted and never returned in full', function () {
    $this->actingAs($this->super)->patch('/platform/ai', [
        'provider' => 'anthropic',
        'default_quota' => 500,
        'anthropic_key' => 'sk-ant-secret-test-key-1234',
    ])->assertRedirect();

    $stored = PlatformSetting::get('ai_key_anthropic');
    expect($stored)->not->toBeNull()
        ->and($stored)->not->toContain('sk-ant-secret') // encrypted at rest
        ->and(Crypt::decryptString($stored))->toBe('sk-ant-secret-test-key-1234');

    $platform = app(PlatformAiSettings::class);
    expect($platform->key('anthropic'))->toBe('sk-ant-secret-test-key-1234');

    // The render-safe status masks the key.
    $status = $platform->keyStatus()['anthropic'];
    expect($status['configured'])->toBeTrue()
        ->and($status['source'])->toBe('managed')
        ->and($status['hint'])->toBe('••••1234')
        ->and(json_encode($status))->not->toContain('secret-test-key');
});

test('blank key field leaves the existing key untouched', function () {
    app(PlatformAiSettings::class)->setKey('anthropic', 'original-key');

    $this->actingAs($this->super)->patch('/platform/ai', [
        'provider' => 'anthropic',
        'default_quota' => 500,
        'anthropic_key' => '',
    ])->assertRedirect();

    expect(app(PlatformAiSettings::class)->key('anthropic'))->toBe('original-key');
});

test('per-tenant update sets enabled, override and quota; blank quota clears it', function () {
    $this->actingAs($this->super)->patch("/platform/ai/tenants/{$this->tenant->id}", [
        'enabled' => false, 'allow_override' => true, 'quota' => 1200,
    ])->assertRedirect();

    expect(TenantSetting::getFor($this->tenant->id, 'ai_enabled'))->toBe('0')
        ->and(TenantSetting::getFor($this->tenant->id, 'ai_allow_override'))->toBe('1')
        ->and(TenantSetting::getFor($this->tenant->id, 'ai_monthly_quota'))->toBe('1200')
        ->and(app(AiQuota::class)->limit($this->tenant->id))->toBe(1200)
        ->and(app(AiQuota::class)->enabled($this->tenant->id))->toBeFalse();

    // Clearing the quota falls back to the platform default.
    PlatformSetting::set('ai_default_quota', '300');
    $this->actingAs($this->super)->patch("/platform/ai/tenants/{$this->tenant->id}", [
        'enabled' => true, 'allow_override' => false, 'quota' => null,
    ])->assertRedirect();

    expect(TenantSetting::getFor($this->tenant->id, 'ai_monthly_quota'))->toBeNull()
        ->and(app(AiQuota::class)->limit($this->tenant->id))->toBe(300);
});

test('the assistant is blocked for a tenant with AI disabled', function () {
    TenantSetting::setFor($this->tenant->id, 'ai_enabled', '0');

    $this->actingAs($this->admin)->postJson('/assistant/send', ['message' => 'hi'])
        ->assertStatus(403);
});

test('a tenant cannot reach the per-tenant AI endpoint', function () {
    $this->actingAs($this->admin)->patch("/platform/ai/tenants/{$this->tenant->id}", [
        'enabled' => true, 'allow_override' => false, 'quota' => null,
    ])->assertForbidden();
});

test('the platform model self-heals when it belongs to the other provider', function () {
    PlatformSetting::set('ai_provider', 'openai');
    PlatformSetting::set('ai_model', 'claude-haiku-4-5'); // stale Claude model left under OpenAI

    // The mismatched model is ignored — falls back to the OpenAI default.
    expect(app(PlatformAiSettings::class)->model())->toBe(config('ai.models.openai'));

    // A matching model is used as-is.
    PlatformSetting::set('ai_model', 'gpt-4o');
    expect(app(PlatformAiSettings::class)->model())->toBe('gpt-4o');

    // A custom / fine-tuned model name is trusted (not wrongly rejected).
    PlatformSetting::set('ai_model', 'ft:gpt-4o:acme');
    expect(app(PlatformAiSettings::class)->model())->toBe('ft:gpt-4o:acme');

    // And the guard works the other way too.
    PlatformSetting::set('ai_provider', 'anthropic');
    PlatformSetting::set('ai_model', 'gpt-4o-mini');
    expect(app(PlatformAiSettings::class)->model())->toBe(config('ai.models.anthropic'));
});
