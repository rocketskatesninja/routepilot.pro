<?php

declare(strict_types=1);

use App\Models\Tenant;
use App\Models\TenantSetting;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->tenant = Tenant::factory()->create(['name' => 'Sunshine Pools']);
    $this->admin = User::factory()->for($this->tenant)->create();
});

test('an admin can view company settings', function () {
    $this->actingAs($this->admin)
        ->get('/company')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('settings/Company')
            ->where('company.name', 'Sunshine Pools')
        );
});

test('an admin can update branding, tax, and AI config', function () {
    $this->actingAs($this->admin)
        ->patch('/company', [
            'name' => 'Sunshine Pools & Spa',
            'timezone' => 'America/Chicago',
            'brand_color' => '#ff8800',
            'tax_rate_percent' => 6.75,
            'ai_provider' => 'anthropic',
            'ai_model' => 'claude-haiku-4-5',
        ])
        ->assertRedirect();

    $this->tenant->refresh();
    expect($this->tenant->name)->toBe('Sunshine Pools & Spa');
    expect((float) $this->tenant->getAttribute('tax_rate'))->toBe(0.0675);
    expect(TenantSetting::getFor($this->tenant->id, 'ai_model'))->toBe('claude-haiku-4-5');
});

test('validation rejects a bad brand color', function () {
    $this->actingAs($this->admin)
        ->patch('/company', [
            'name' => 'X', 'timezone' => 'America/Chicago', 'brand_color' => 'not-a-color',
            'tax_rate_percent' => 5, 'ai_provider' => 'anthropic',
        ])
        ->assertSessionHasErrors('brand_color');
});

test('agents cannot access company settings', function () {
    $agent = User::factory()->agent()->for($this->tenant)->create();

    $this->actingAs($agent)->get('/company')->assertForbidden();
    $this->actingAs($agent)->patch('/company', [])->assertForbidden();
});
