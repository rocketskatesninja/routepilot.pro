<?php

declare(strict_types=1);

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
