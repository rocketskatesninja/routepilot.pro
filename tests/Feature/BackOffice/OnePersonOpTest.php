<?php

declare(strict_types=1);

use App\Actions\UpdateSubscription;
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
    $this->admin = User::factory()->for($this->tenant)->create(); // tenant_admin by default
    $this->customer = Customer::factory()->for($this->tenant)->create();
    $this->pool = Pool::factory()->for($this->tenant)->for($this->customer)->create();
    $this->serviceType = ServiceType::factory()->for($this->tenant)->create();
});

test('a one-person operation can assign a service plan to the tenant_admin themselves', function () {
    $this->actingAs($this->admin)->post('/subscriptions', [
        'pool_id' => $this->pool->id,
        'service_type_id' => $this->serviceType->id,
        'assigned_agent_id' => $this->admin->id, // the admin IS the tech
        'frequency' => 'weekly',
    ])->assertRedirect()->assertSessionHasNoErrors();

    expect(ServiceSubscription::query()->where('pool_id', $this->pool->id)->first()?->assigned_agent_id)
        ->toBe($this->admin->id);
});

test('reassigning a plan moves its upcoming pending stops to the new tech', function () {
    $agent = User::factory()->agent()->for($this->tenant)->create();
    $sub = $this->pool->subscriptions()->create([
        'service_type_id' => $this->serviceType->id,
        'assigned_agent_id' => $agent->id,
        'frequency' => 'weekly',
        'preferred_day' => 'monday',
        'status' => 'active',
    ]);

    app(SubscriptionMaterializer::class)->run($this->tenant->id);

    $stopsOn = fn (int $id): int => RouteStop::query()
        ->where('service_subscription_id', $sub->id)
        ->where('status', 'pending')
        ->whereHas('route', fn ($q) => $q->where('agent_id', $id))
        ->count();

    expect($stopsOn($agent->id))->toBeGreaterThan(0)->and($stopsOn($this->admin->id))->toBe(0);

    app(UpdateSubscription::class)->handle($sub, [
        'service_type_id' => $this->serviceType->id,
        'assigned_agent_id' => $this->admin->id,
        'frequency' => 'weekly',
        'preferred_day' => 'monday',
        'status' => 'active',
    ]);

    expect($stopsOn($this->admin->id))->toBeGreaterThan(0)->and($stopsOn($agent->id))->toBe(0);
});
