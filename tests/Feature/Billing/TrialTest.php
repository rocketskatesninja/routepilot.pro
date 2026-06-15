<?php

declare(strict_types=1);

use App\Actions\RegisterTenant;
use App\Models\Tenant;
use App\Models\User;

test('registering a tenant starts a 14-day free trial', function () {
    $admin = app(RegisterTenant::class)([
        'company' => 'Bubbles Pools', 'first_name' => 'Pat', 'email' => 'pat@bubbles.test', 'password' => 'password123',
    ]);

    $tenant = Tenant::query()->findOrFail($admin->tenant_id);

    expect($tenant->onTrial())->toBeTrue()
        ->and($tenant->trial_ends_at?->isFuture())->toBeTrue()
        ->and((int) ceil((float) now()->diffInDays($tenant->trial_ends_at, false)))->toBeGreaterThanOrEqual(13);

    $state = $tenant->billingState();
    expect($state['status'])->toBe('trialing')
        ->and($state['on_trial'])->toBeTrue()
        ->and($state['subscribed'])->toBeFalse()
        ->and($state['trial_days_left'])->toBeGreaterThanOrEqual(13);
});

test('an expired trial with no subscription reads as expired', function () {
    $tenant = Tenant::factory()->create();
    $tenant->forceFill(['trial_ends_at' => now()->subDay()])->save();

    expect($tenant->onTrial())->toBeFalse()
        ->and($tenant->billingState()['status'])->toBe('expired')
        ->and($tenant->billingState()['trial_days_left'])->toBe(0);
});

test('a tenant with no trial set reads as none', function () {
    $tenant = Tenant::factory()->create(); // trial_ends_at null

    expect($tenant->billingState()['status'])->toBe('none')
        ->and($tenant->billingState()['on_trial'])->toBeFalse();
});

test('the billing state is shared to a signed-in tenant admin', function () {
    $tenant = Tenant::factory()->create();
    $tenant->forceFill(['trial_ends_at' => now()->addDays(10)])->save();
    $admin = User::factory()->for($tenant)->create();

    $this->actingAs($admin)->get('/dashboard')
        ->assertInertia(fn ($page) => $page
            ->where('billing.status', 'trialing')
            ->where('billing.on_trial', true)
            ->where('billing.subscribed', false));
});
