<?php

declare(strict_types=1);

use App\Models\Tenant;
use App\Models\User;

/** A tenant whose trial lapsed with no subscription is billing-locked. */
test('billingLocked is true only after the trial expires without a subscription', function () {
    $expired = Tenant::factory()->create();
    $expired->forceFill(['trial_ends_at' => now()->subDay()])->save();
    expect($expired->billingLocked())->toBeTrue();

    $onTrial = Tenant::factory()->create();
    $onTrial->forceFill(['trial_ends_at' => now()->addDays(5)])->save();
    expect($onTrial->billingLocked())->toBeFalse();

    // No trial ever set (seeded/legacy) — deliberately not locked.
    $noTrial = Tenant::factory()->create();
    expect($noTrial->billingLocked())->toBeFalse();
});

test('a locked tenant admin is redirected from the back office to billing', function () {
    $tenant = Tenant::factory()->create();
    $tenant->forceFill(['trial_ends_at' => now()->subDay()])->save();
    $admin = User::factory()->for($tenant)->create();

    $this->actingAs($admin)->get('/dashboard')->assertRedirect(route('billing.show'));
    // Billing + paused stay reachable so the lock is escapable.
    $this->actingAs($admin)->get('/billing')->assertOk();
});

test('a locked agent or customer is redirected to the paused page', function () {
    $tenant = Tenant::factory()->create();
    $tenant->forceFill(['trial_ends_at' => now()->subDay()])->save();

    $agent = User::factory()->agent()->for($tenant)->create();
    $this->actingAs($agent)->get('/dashboard')->assertRedirect(route('account.paused'));
    $this->actingAs($agent)->get('/paused')->assertOk();

    $customer = User::factory()->customer()->for($tenant)->create();
    $this->actingAs($customer)->get('/dashboard')->assertRedirect(route('account.paused'));
});

test('a tenant on an active trial is not locked out of the back office', function () {
    $tenant = Tenant::factory()->create();
    $tenant->forceFill(['trial_ends_at' => now()->addDays(5)])->save();
    $admin = User::factory()->for($tenant)->create();

    $this->actingAs($admin)->get('/dashboard')->assertOk();
});

test('a super admin is never billing-locked', function () {
    // Super-admins have no tenant; even so the middleware must never redirect them.
    $admin = User::factory()->superAdmin()->create();

    $this->actingAs($admin)->get('/dashboard')->assertOk();
});
