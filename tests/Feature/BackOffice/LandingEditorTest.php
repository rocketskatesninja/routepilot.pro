<?php

declare(strict_types=1);

use App\Models\Tenant;
use App\Models\TenantSetting;
use App\Models\User;
use App\Support\LandingConfig;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->tenant = Tenant::factory()->create();
    $this->admin = User::factory()->for($this->tenant)->create();
});

test('a tenant_admin sees the landing editor with the full section set', function () {
    $this->actingAs($this->admin)
        ->get('/company/landing')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('settings/Landing')
            ->has('config.sections', count(LandingConfig::SECTION_KEYS))
            ->has('live')
            ->has('agents')
        );
});

test('an agent cannot access the landing editor', function () {
    $agent = User::factory()->agent()->for($this->tenant)->create();

    $this->actingAs($agent)->get('/company/landing')->assertForbidden();
    $this->actingAs($agent)->post('/company/landing', ['sections' => [['key' => 'hero', 'enabled' => true]]])->assertForbidden();
});

test('saving persists a sanitized config and keeps the full section set', function () {
    $this->actingAs($this->admin)->post('/company/landing', [
        'sections' => [
            ['key' => 'hero', 'enabled' => true, 'headline' => 'Welcome!', 'evil' => '<script>'],
            ['key' => 'faq', 'enabled' => false],
        ],
        'seo' => ['title' => 'My Pools', 'description' => 'The best pools in town'],
    ])->assertRedirect();

    $stored = json_decode((string) TenantSetting::getFor($this->tenant->id, 'landing'), true);
    $hero = collect($stored['sections'])->firstWhere('key', 'hero');

    expect($hero['headline'])->toBe('Welcome!');
    expect($hero)->not->toHaveKey('evil');                 // sanitized away
    expect($stored['seo']['title'])->toBe('My Pools');
    expect(collect($stored['sections'])->pluck('key')->sort()->values()->all())
        ->toEqual(collect(LandingConfig::SECTION_KEYS)->sort()->values()->all());
});
