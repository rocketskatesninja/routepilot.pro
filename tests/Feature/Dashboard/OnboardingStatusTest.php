<?php

declare(strict_types=1);

use App\Models\Customer;
use App\Models\Pool;
use App\Models\ServiceType;
use App\Models\Tenant;
use App\Models\TenantSetting;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

/**
 * The first-run "Getting started" checklist: progress is derived from the data
 * itself (each step ticks once its records exist), only the tenant admin sees it,
 * and "Dismiss for now" hides it via a per-tenant flag.
 */
beforeEach(function () {
    $this->tenant = Tenant::factory()->create();
    $this->admin = User::factory()->for($this->tenant)->create(); // default role = tenant_admin
});

test('a fresh tenant admin sees the checklist with four required steps', function () {
    $this->actingAs($this->admin)
        ->get('/dashboard')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->has('onboarding.steps', 5) // five rows shown; only four count toward completion
            ->where('onboarding.done', 0)
            ->where('onboarding.total', 4)
            ->where('onboarding.complete', false)
            ->where('onboarding.dismissed', false)
            ->where('onboarding.steps.2.key', 'agents')
            ->where('onboarding.steps.2.optional', true)
        );
});

test('each created required record ticks its step off', function () {
    ServiceType::factory()->for($this->tenant)->create();

    $this->actingAs($this->admin)
        ->get('/dashboard')
        ->assertInertia(fn (Assert $page) => $page
            ->where('onboarding.done', 1)
            ->where('onboarding.steps.0.key', 'services')
            ->where('onboarding.steps.0.done', true)
            ->where('onboarding.complete', false)
        );
});

test('completing the four required steps marks the checklist complete without an agent', function () {
    ServiceType::factory()->for($this->tenant)->create();
    TenantSetting::setFor($this->tenant->id, 'landing', json_encode(['sections' => []]));
    $customer = Customer::factory()->for($this->tenant)->create();
    Pool::factory()->for($this->tenant)->for($customer)->create();
    // deliberately no agent — a solo operator runs the route themselves

    $this->actingAs($this->admin)
        ->get('/dashboard')
        ->assertInertia(fn (Assert $page) => $page
            ->where('onboarding.done', 4)
            ->where('onboarding.total', 4)
            ->where('onboarding.complete', true)
            ->where('onboarding.steps.2.done', false) // agents still unticked
        );
});

test('adding an agent ticks the optional step but never inflates required progress', function () {
    User::factory()->agent()->for($this->tenant)->create();

    $this->actingAs($this->admin)
        ->get('/dashboard')
        ->assertInertia(fn (Assert $page) => $page
            ->where('onboarding.steps.2.key', 'agents')
            ->where('onboarding.steps.2.done', true)
            ->where('onboarding.done', 0) // required progress unchanged
            ->where('onboarding.total', 4)
            ->where('onboarding.complete', false)
        );
});

test('dismiss hides the checklist for the tenant', function () {
    $this->actingAs($this->admin)
        ->from('/dashboard')
        ->post('/dashboard/onboarding/dismiss')
        ->assertRedirect('/dashboard');

    $this->actingAs($this->admin)
        ->get('/dashboard')
        ->assertInertia(fn (Assert $page) => $page->where('onboarding.dismissed', true));
});

test('non-admin roles never receive the checklist', function () {
    $agent = User::factory()->agent()->for($this->tenant)->create();

    $this->actingAs($agent)
        ->get('/dashboard')
        ->assertInertia(fn (Assert $page) => $page->where('onboarding', null));
});

test('a non-admin cannot dismiss the checklist', function () {
    $agent = User::factory()->agent()->for($this->tenant)->create();

    $this->actingAs($agent)
        ->post('/dashboard/onboarding/dismiss')
        ->assertForbidden();
});
