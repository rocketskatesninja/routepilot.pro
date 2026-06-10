<?php

declare(strict_types=1);

use App\Models\Customer;
use App\Models\Pool;
use App\Models\RouteStop;
use App\Models\ServiceSubscription;
use App\Models\ServiceType;
use App\Models\Tenant;
use App\Models\User;
use App\Services\SubscriptionMaterializer;

beforeEach(function () {
    $this->tenant = Tenant::factory()->create();
    app()->instance('tenant_id', $this->tenant->id);
    $this->admin = User::factory()->for($this->tenant)->create();
    $this->agent = User::factory()->agent()->for($this->tenant)->create();

    $customer = Customer::factory()->for($this->tenant)->create();
    $pool = Pool::factory()->for($this->tenant)->for($customer)->create();
    $this->type = ServiceType::factory()->for($this->tenant)->create();
    $this->sub = ServiceSubscription::factory()->for($this->tenant)->for($pool)->for($this->type)
        ->create(['assigned_agent_id' => $this->agent->id, 'status' => 'active', 'frequency' => 'weekly']);
});

test('the materializer skips a subscription on vacation hold', function () {
    $this->sub->update(['hold_starts_at' => today(), 'hold_ends_at' => today()->addWeeks(4)]);

    app(SubscriptionMaterializer::class)->run($this->tenant->id, today()->addWeeks(2)->toDateString());

    expect(RouteStop::query()->where('service_subscription_id', $this->sub->id)->count())->toBe(0);
});

test('without a hold the materializer creates stops', function () {
    app(SubscriptionMaterializer::class)->run($this->tenant->id, today()->addWeeks(2)->toDateString());

    expect(RouteStop::query()->where('service_subscription_id', $this->sub->id)->count())->toBeGreaterThan(0);
});

test('an admin sets a dated hold via the subscription form', function () {
    $this->actingAs($this->admin)
        ->patch("/subscriptions/{$this->sub->id}", [
            'service_type_id' => $this->type->id,
            'assigned_agent_id' => $this->agent->id,
            'frequency' => 'weekly',
            'status' => 'active',
            'hold_starts_at' => today()->toDateString(),
            'hold_ends_at' => today()->addWeeks(2)->toDateString(),
        ])
        ->assertRedirect();

    expect($this->sub->fresh()?->hold_starts_at)->not->toBeNull();
});
