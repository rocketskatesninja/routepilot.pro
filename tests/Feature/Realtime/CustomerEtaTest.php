<?php

declare(strict_types=1);

use App\Events\VisitEtaUpdated;
use App\Models\Customer;
use App\Models\Pool;
use App\Models\Route;
use App\Models\RouteStop;
use App\Models\ServiceLocation;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Broadcasting\Broadcasters\Broadcaster;
use Illuminate\Contracts\Broadcasting\Factory as BroadcastFactory;
use Illuminate\Support\Facades\Event;

beforeEach(function () {
    $this->tenant = Tenant::factory()->create();
    app()->instance('tenant_id', $this->tenant->id);
    $this->agent = User::factory()->agent()->for($this->tenant)->create();
});

/** A pending stop on the agent's active route today, owned by $customer. */
function pendingStopFor(Tenant $tenant, User $agent, Customer $customer): void
{
    $pool = Pool::factory()->for($tenant)->for($customer)->create();
    ServiceLocation::factory()->for($pool)->create(['lat' => 31.2, 'lng' => -81.5]);
    $route = Route::factory()->for($tenant)->create(['agent_id' => $agent->id, 'scheduled_date' => today(), 'status' => 'in_progress']);
    RouteStop::factory()->for($route)->for($pool)->create(['stop_order' => 1, 'status' => 'pending']);
}

test('the customer eta channel authorizes only that customer\'s portal user', function () {
    $broadcaster = app(BroadcastFactory::class)->connection();
    $channels = (new ReflectionProperty(Broadcaster::class, 'channels'))->getValue($broadcaster);
    $authorize = $channels['customer.{customerId}'];

    $portalUser = User::factory()->customer()->for($this->tenant)->create();
    $customer = Customer::factory()->for($this->tenant)->create(['user_id' => $portalUser->id]);
    $otherUser = User::factory()->customer()->for($this->tenant)->create();
    Customer::factory()->for($this->tenant)->create(['user_id' => $otherUser->id]);

    $id = (string) $customer->id;
    expect($authorize($portalUser, $id))->toBeTrue()
        ->and($authorize($otherUser, $id))->toBeFalse()
        ->and($authorize($this->agent, $id))->toBeFalse();
});

test('a ping broadcasts the next customer their live on-my-way window', function () {
    Event::fake([VisitEtaUpdated::class]);
    $portalUser = User::factory()->customer()->for($this->tenant)->create();
    $customer = Customer::factory()->for($this->tenant)->create(['user_id' => $portalUser->id]);
    pendingStopFor($this->tenant, $this->agent, $customer);

    $this->actingAs($this->agent)
        ->postJson('/api/field/ping', ['lat' => 31.21, 'lng' => -81.49])
        ->assertOk()->assertJsonPath('tracking', true);

    Event::assertDispatched(VisitEtaUpdated::class, fn (VisitEtaUpdated $e): bool => $e->customerId === $customer->id
        && $e->window !== '');
});

test('a ping sends no window when the next customer has no portal login', function () {
    Event::fake([VisitEtaUpdated::class]);
    $customer = Customer::factory()->for($this->tenant)->create(['user_id' => null]);
    pendingStopFor($this->tenant, $this->agent, $customer);

    $this->actingAs($this->agent)->postJson('/api/field/ping', ['lat' => 31.21, 'lng' => -81.49])->assertOk();

    Event::assertNotDispatched(VisitEtaUpdated::class);
});
