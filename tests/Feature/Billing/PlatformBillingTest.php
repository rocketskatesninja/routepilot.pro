<?php

declare(strict_types=1);

use App\Models\AuditLog;
use App\Models\Tenant;
use App\Models\User;

test('the platform billing console summarizes MRR, pipeline and per-tenant rows', function () {
    // A paying tenant (active subscription) → counts toward MRR.
    $paying = Tenant::factory()->create(['name' => 'Acme Pools']);
    $paying->subscriptions()->create([
        'type' => 'default',
        'stripe_id' => 'sub_test_active',
        'stripe_status' => 'active',
        'stripe_price' => 'price_base',
        'quantity' => 1,
    ]);

    // A trialing tenant → trial pipeline, not MRR.
    $trialing = Tenant::factory()->create(['name' => 'Blue Wave']);
    $trialing->forceFill(['trial_ends_at' => now()->addDays(10)])->save();

    $super = User::factory()->superAdmin()->create();

    $base = (float) config('billing.base_price');

    $this->actingAs($super)->get('/platform/billing')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('admin/Billing')
            ->where('metrics.tenants', 2)
            ->where('metrics.active', 1)
            ->where('metrics.trialing', 1)
            ->where('metrics.mrr', round($base, 2))
            ->where('metrics.trial_pipeline', round($base, 2))
            ->has('tenants', 2));
});

test('the platform billing console is super-admin only', function () {
    $tenant = Tenant::factory()->create();

    $this->actingAs(User::factory()->for($tenant)->create())->get('/platform/billing')->assertForbidden();
    $this->actingAs(User::factory()->agent()->for($tenant)->create())->get('/platform/billing')->assertForbidden();
});

test('a super admin can comp a tenant, which unlocks it, and the change is audited', function () {
    $tenant = Tenant::factory()->create();
    $tenant->forceFill(['trial_ends_at' => now()->subDay()])->save();
    expect($tenant->billingLocked())->toBeTrue();

    $super = User::factory()->superAdmin()->create();

    $this->actingAs($super)
        ->patch("/platform/billing/tenants/{$tenant->id}", [
            'billing_free' => true,
            'billing_note' => 'Beta launch partner',
            'trial_ends_at' => null,
        ])
        ->assertRedirect();

    $tenant->refresh();
    expect($tenant->billing_free)->toBeTrue()
        ->and($tenant->billing_note)->toBe('Beta launch partner')
        ->and($tenant->billingLocked())->toBeFalse()
        ->and($tenant->billingState()['status'])->toBe('free');

    expect(AuditLog::where('action', 'tenant.billing_updated')
        ->where('model_id', $tenant->id)->exists())->toBeTrue();
});

test('a super admin can set and clear a trial date', function () {
    $tenant = Tenant::factory()->create();
    $super = User::factory()->superAdmin()->create();

    $this->actingAs($super)
        ->patch("/platform/billing/tenants/{$tenant->id}", ['billing_free' => false, 'trial_ends_at' => '2030-01-15'])
        ->assertRedirect();
    expect($tenant->refresh()->trial_ends_at?->toDateString())->toBe('2030-01-15');

    $this->actingAs($super)
        ->patch("/platform/billing/tenants/{$tenant->id}", ['billing_free' => false, 'trial_ends_at' => null])
        ->assertRedirect();
    expect($tenant->refresh()->trial_ends_at)->toBeNull();
});

test('a trial date beyond 2038 is rejected (TIMESTAMP range)', function () {
    $tenant = Tenant::factory()->create();
    $super = User::factory()->superAdmin()->create();

    $this->actingAs($super)
        ->patch("/platform/billing/tenants/{$tenant->id}", ['billing_free' => false, 'trial_ends_at' => '2099-12-31'])
        ->assertSessionHasErrors('trial_ends_at');
});

test('non super-admins cannot change a tenant billing status', function () {
    $tenant = Tenant::factory()->create();

    $this->actingAs(User::factory()->for($tenant)->create())
        ->patch("/platform/billing/tenants/{$tenant->id}", ['billing_free' => true])
        ->assertForbidden();

    expect($tenant->refresh()->billing_free)->toBeFalse();
});

test('the console returns the selected tenant detail for the side panel', function () {
    $tenant = Tenant::factory()->create(['name' => 'Sunshine Pools']);
    $tenant->forceFill(['billing_free' => true, 'billing_note' => 'Founder comp'])->save();
    $super = User::factory()->superAdmin()->create();

    $this->actingAs($super)->get("/platform/billing?selected={$tenant->id}")
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('selected.id', $tenant->id)
            ->where('selected.status', 'free')
            ->where('selected.free', true)
            ->where('selected.billing_note', 'Founder comp'));
});
