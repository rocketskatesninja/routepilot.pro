<?php

declare(strict_types=1);

use App\Models\Customer;
use App\Models\Pool;
use App\Models\RouteStop;
use App\Models\ServiceSubscription;
use App\Models\ServiceType;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Carbon;

/*
 * Adding a pool can add its first service plan in the same step: checked like any
 * plan when a service is picked, ignored when not; a bad plan adds neither.
 */

beforeEach(function () {
    Carbon::setTestNow('2026-10-29 15:00:00'); // a Thursday
    $this->tenant = Tenant::factory()->create(['timezone' => 'America/New_York']);
    app()->instance('tenant_id', $this->tenant->id);
    $this->admin = User::factory()->for($this->tenant)->create();
    $this->tech = User::factory()->agent()->for($this->tenant)->create();
    $this->customer = Customer::factory()->for($this->tenant)->create();
    $this->service = ServiceType::factory()->for($this->tenant)->create(['name' => 'Weekly pool service']);
});

$pool = fn (array $extra = []) => ['customer_id' => test()->customer->id, 'name' => 'Cruz Pool', 'type' => 'inground', 'sanitizer_type' => 'chlorine', ...$extra];

test('a pool added with a service gets its plan, scheduled', function () use ($pool) {
    $this->actingAs($this->admin)->post('/pools', $pool([
        'service_type_id' => $this->service->id, 'assigned_agent_id' => $this->tech->id,
        'frequency' => 'weekly', 'preferred_day' => 'friday', 'starts_on' => '2026-10-29',
    ]))->assertSessionHas('success', 'Pool added, with its service plan on the schedule.');

    $plan = ServiceSubscription::query()->sole();
    expect($plan->pool_id)->toBe(Pool::query()->sole()->id)
        ->and($plan->only(['service_type_id', 'assigned_agent_id', 'frequency', 'preferred_day', 'status']))
        ->toBe(['service_type_id' => $this->service->id, 'assigned_agent_id' => $this->tech->id, 'frequency' => 'weekly', 'preferred_day' => 'friday', 'status' => 'active'])
        ->and(RouteStop::query()->where('service_subscription_id', $plan->id)->exists())->toBeTrue();
});

test('without a service it is just the pool, whatever the schedule fields hold', function () use ($pool) {
    $this->actingAs($this->admin)->post('/pools', $pool(['service_type_id' => '', 'frequency' => 'weekly', 'preferred_day' => '']))
        ->assertSessionHasNoErrors()->assertSessionHas('success', 'Pool added.');

    expect(Pool::query()->count())->toBe(1)->and(ServiceSubscription::query()->exists())->toBeFalse();
});

test('a bad plan adds neither the plan nor the pool', function () use ($pool) {
    $foreign = ServiceType::factory()->for(Tenant::factory()->create())->create();

    $this->actingAs($this->admin)->post('/pools', $pool(['service_type_id' => $this->service->id, 'frequency' => 'weekly', 'preferred_day' => '']))
        ->assertSessionHasErrors('preferred_day');
    $this->actingAs($this->admin)->post('/pools', $pool(['service_type_id' => $foreign->id, 'frequency' => 'monthly']))
        ->assertSessionHasErrors('service_type_id');

    expect(Pool::query()->exists())->toBeFalse()->and(ServiceSubscription::query()->exists())->toBeFalse();
});
