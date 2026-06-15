<?php

declare(strict_types=1);

use App\Models\Tenant;
use App\Models\User;

test('the billing screen renders for a tenant admin with plan + usage', function () {
    $tenant = Tenant::factory()->create();
    $tenant->forceFill(['trial_ends_at' => now()->addDays(10)])->save();
    $admin = User::factory()->for($tenant)->create();

    $this->actingAs($admin)->get('/billing')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('settings/Billing')
            ->where('configured', false) // no Stripe price IDs in the test env
            ->has('plan.base_price')
            ->where('billing.status', 'trialing')
            ->has('billing.usage.estimated_total'));
});

test('agents and customers cannot reach billing', function () {
    $tenant = Tenant::factory()->create();

    $this->actingAs(User::factory()->agent()->for($tenant)->create())->get('/billing')->assertForbidden();
    $this->actingAs(User::factory()->customer()->for($tenant)->create())->get('/billing')->assertForbidden();
});

test('checkout fails gracefully when billing is not configured', function () {
    $admin = User::factory()->for(Tenant::factory())->create();

    $this->actingAs($admin)->post('/billing/checkout')
        ->assertRedirect()
        ->assertSessionHas('error');
});

test('the billing portal fails gracefully without a billing account', function () {
    $admin = User::factory()->for(Tenant::factory())->create();

    $this->actingAs($admin)->get('/billing/portal')
        ->assertRedirect()
        ->assertSessionHas('error');
});
