<?php

declare(strict_types=1);

use App\Models\Tenant;
use App\Models\User;

/** A comped tenant is never billing-locked, even with a lapsed trial. */
test('billing_free overrides a lapsed trial so the tenant is not locked', function () {
    $tenant = Tenant::factory()->create();
    $tenant->forceFill(['trial_ends_at' => now()->subDay()])->save();
    expect($tenant->billingLocked())->toBeTrue();

    $tenant->forceFill(['billing_free' => true])->save();
    expect($tenant->billingLocked())->toBeFalse();
});

/** The comp surfaces as a distinct 'free' status, taking precedence over trial/sub state. */
test('billingState reports free status when comped', function () {
    $tenant = Tenant::factory()->create();
    $tenant->forceFill(['trial_ends_at' => now()->subDay(), 'billing_free' => true])->save();

    $state = $tenant->billingState();
    expect($state['status'])->toBe('free')
        ->and($state['free'])->toBeTrue()
        ->and($state['locked'])->toBeFalse();
});

/** End-to-end: a comped admin who would otherwise be locked reaches the back office. */
test('a comped tenant admin is not redirected from the back office', function () {
    $tenant = Tenant::factory()->create();
    $tenant->forceFill(['trial_ends_at' => now()->subDay(), 'billing_free' => true])->save();
    $admin = User::factory()->for($tenant)->create();

    $this->actingAs($admin)->get('/dashboard')->assertOk();
});
