<?php

declare(strict_types=1);

use App\Models\Customer;
use App\Models\Pool;
use App\Models\ServiceSubscription;
use App\Models\ServiceType;
use App\Models\Tenant;
use App\Models\User;

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
