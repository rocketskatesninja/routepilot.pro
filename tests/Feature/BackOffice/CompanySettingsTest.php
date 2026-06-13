<?php

declare(strict_types=1);

use App\Models\AuditLog;
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

test('an admin can save the business address (geocoding fails soft without a key)', function () {
    config()->set('services.google.server_maps_key', ''); // force the no-key fail-soft path

    $this->actingAs($this->admin)
        ->patch('/company', [
            'name' => 'Sunshine Pools', 'timezone' => 'America/Chicago', 'brand_color' => '#0ea5e9',
            'tax_rate_percent' => 0, 'ai_provider' => 'anthropic',
            'address_line1' => '123 Pool Lane', 'address_line2' => 'Suite 100',
            'city' => 'Austin', 'state' => 'tx', 'postal_code' => '78701',
        ])
        ->assertRedirect()
        ->assertSessionHasNoErrors();

    $this->tenant->refresh();
    expect($this->tenant->getAttribute('address_line1'))->toBe('123 Pool Lane');
    expect($this->tenant->getAttribute('city'))->toBe('Austin');
    expect($this->tenant->getAttribute('state'))->toBe('TX'); // normalized to uppercase
    expect($this->tenant->getAttribute('lat'))->toBeNull(); // geocode no-op without a key
    expect($this->tenant->formattedAddress())->toBe('123 Pool Lane, Austin, TX 78701');
});

test('a partial business address is rejected', function () {
    $this->actingAs($this->admin)
        ->patch('/company', [
            'name' => 'Sunshine Pools', 'timezone' => 'America/Chicago', 'brand_color' => '#0ea5e9',
            'tax_rate_percent' => 0, 'ai_provider' => 'anthropic',
            'address_line1' => '123 Pool Lane', // city/state/zip omitted
        ])
        ->assertSessionHasErrors(['city', 'state', 'postal_code']);
});

test('agents cannot access company settings', function () {
    $agent = User::factory()->agent()->for($this->tenant)->create();

    $this->actingAs($agent)->get('/company')->assertForbidden();
    $this->actingAs($agent)->patch('/company', [])->assertForbidden();
});

test('changing the tax rate is audited (and an unchanged rate is not)', function () {
    $this->tenant->forceFill(['tax_rate' => 0.05])->save();

    // A real change records an audit entry with the from/to.
    $this->actingAs($this->admin)->patch('/company', [
        'name' => 'Sunshine Pools', 'timezone' => 'America/Chicago', 'brand_color' => '#0ea5e9',
        'tax_rate_percent' => 8.25, 'ai_provider' => 'anthropic',
    ])->assertRedirect();

    $entry = AuditLog::query()->where('action', 'company.tax_rate.updated')->latest('id')->first();
    expect($entry)->not->toBeNull()
        ->and($entry->model_id)->toBe($this->tenant->id)
        ->and($entry->changes)->toMatchArray(['from' => 0.05, 'to' => 0.0825]);

    // Re-saving the same rate does not add another entry.
    $this->actingAs($this->admin)->patch('/company', [
        'name' => 'Sunshine Pools', 'timezone' => 'America/Chicago', 'brand_color' => '#0ea5e9',
        'tax_rate_percent' => 8.25, 'ai_provider' => 'anthropic',
    ])->assertRedirect();

    expect(AuditLog::query()->where('action', 'company.tax_rate.updated')->count())->toBe(1);
});
