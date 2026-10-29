<?php

declare(strict_types=1);

use App\Models\Charge;
use App\Models\ChemicalInventory;
use App\Models\Customer;
use App\Models\Pool;
use App\Models\Route;
use App\Models\RouteStop;
use App\Models\ServiceLocation;
use App\Models\ServiceSubscription;
use App\Models\ServiceType;
use App\Models\ServiceVisit;
use App\Models\Tenant;
use App\Models\User;

/*
 * Extra visits: a tech adds a pool that isn't on their route from the field app.
 * It joins the end of today's route as a stop with no plan, so finishing it files
 * a report like any visit but makes no charge.
 */

beforeEach(function () {
    $this->tenant = Tenant::factory()->create();
    app()->instance('tenant_id', $this->tenant->id);
    $this->agent = User::factory()->agent()->for($this->tenant)->create();
    $customer = Customer::factory()->for($this->tenant)->create(['first_name' => 'Jan', 'last_name' => 'Harris']);
    $this->pool = Pool::factory()->for($this->tenant)->for($customer)->create(['name' => 'Backyard Pool']);
    ServiceLocation::factory()->for($this->pool)->create(['address_line1' => '12 Ocean Dr', 'city' => 'Tybee']);
    // The pool has a regular plan, so a scheduled visit here would be billed.
    $this->plan = ServiceSubscription::factory()->for($this->tenant)->for($this->pool)
        ->for(ServiceType::factory()->for($this->tenant)->create(['name' => 'Weekly Clean', 'price' => 50]))
        ->create(['assigned_agent_id' => $this->agent->id, 'status' => 'active']);
});

/** The tech adds a pool to today and gets back the stop to open. */
function addExtra(User $tech, Pool $pool): RouteStop
{
    $id = test()->actingAs($tech)->postJson("/api/field/pools/{$pool->id}/visit")->assertOk()->json('stop_id');

    return RouteStop::query()->findOrFail($id);
}

test('adding a pool puts an extra visit at the end of today\'s route, making the route if there isn\'t one', function () {
    $other = Pool::factory()->for($this->tenant)->for(Customer::factory()->for($this->tenant))->create();

    $first = addExtra($this->agent, $this->pool);
    $second = addExtra($this->agent, $other);

    expect($first->route?->agent_id)->toBe($this->agent->id)
        ->and($first->route?->scheduled_date->toDateString())->toBe($this->tenant->today()->toDateString())
        ->and($first->service_subscription_id)->toBeNull()
        ->and($first->status)->toBe('pending')
        ->and([$first->stop_order, $second->stop_order])->toBe([1, 2])
        ->and($second->route_id)->toBe($first->route_id);

    $this->actingAs($this->agent)->getJson('/api/field/today')->assertOk()
        ->assertJsonPath('stops.0.id', $first->id)
        ->assertJsonPath('stops.0.extra', true)
        ->assertJsonPath('stops.0.service.name', 'Weekly Clean'); // the pool's own tasks and sections
});

test('pucks are counted out of stock like any chemical', function () {
    $pucks = ChemicalInventory::factory()->for($this->tenant)->create(['chemical_name' => 'Chlorine Pucks', 'unit' => 'puck', 'current_stock' => 50]);
    $stop = addExtra($this->agent, $this->pool);

    $this->actingAs($this->agent)->post("/api/field/visits/{$stop->id}/complete", [
        'treatments' => [['chemical_id' => $pucks->id, 'amount' => 3]],
    ])->assertOk();

    expect((float) $pucks->fresh()?->current_stock)->toBe(47.0);
});

test('finishing an extra visit files a report and takes chemicals from stock, but charges nothing', function () {
    $chlorine = ChemicalInventory::factory()->for($this->tenant)->create(['chemical_name' => 'Liquid Chlorine', 'unit' => 'gal', 'current_stock' => 10]);
    $stop = addExtra($this->agent, $this->pool);

    $this->actingAs($this->agent)->post("/api/field/visits/{$stop->id}/complete", [
        'ph' => 7.4,
        'treatments' => [['chemical_id' => $chlorine->id, 'amount' => 2]],
        'notes' => 'Customer called about cloudy water.',
    ])->assertOk();

    $visit = ServiceVisit::query()->sole();
    expect($visit->route_stop_id)->toBe($stop->id)
        ->and($visit->getAttribute('service_subscription_id'))->toBeNull()
        ->and($stop->fresh()?->status)->toBe('completed')
        ->and(Charge::query()->exists())->toBeFalse()
        ->and((float) $chlorine->fresh()?->current_stock)->toBe(8.0);
});

test('adding a pool that is already waiting on today\'s route opens that stop instead', function () {
    $route = Route::forDay($this->tenant->id, $this->agent->id, $this->tenant->today()->toDateString());
    $planned = RouteStop::factory()->for($route)->for($this->pool)->create(['service_subscription_id' => $this->plan->id, 'stop_order' => 1, 'status' => 'pending']);

    expect(addExtra($this->agent, $this->pool)->id)->toBe($planned->id)
        ->and(RouteStop::query()->count())->toBe(1);
});

test('only this company\'s pools that are still in service can be added, and only by staff', function () {
    $foreign = Pool::factory()->create();
    $archived = Pool::factory()->for($this->tenant)->for(Customer::factory()->for($this->tenant))->create();
    $archived->delete();
    $portalUser = User::factory()->customer()->for($this->tenant)->create();
    app()->forgetInstance('tenant_id'); // the request resolves the tenant from the signed-in user

    $this->actingAs($this->agent)->postJson("/api/field/pools/{$foreign->id}/visit")->assertNotFound();
    $this->actingAs($this->agent)->postJson("/api/field/pools/{$archived->id}/visit")->assertNotFound();
    $this->actingAs($portalUser)->postJson("/api/field/pools/{$this->pool->id}/visit")->assertForbidden();
    expect(RouteStop::query()->count())->toBe(0);
});

test('the pool search finds a pool by its name, customer or street, within the company', function () {
    Pool::factory()->for($this->tenant)->for(Customer::factory()->for($this->tenant))->create(['name' => 'Harris Spa'])->delete(); // archived
    Pool::factory()->create(['name' => 'Harris Lagoon']); // another company's
    app()->forgetInstance('tenant_id');

    foreach (['Backyard', 'harris', 'Ocean'] as $search) {
        $this->actingAs($this->agent)->getJson('/api/field/pools?search='.$search)->assertOk()
            ->assertJsonCount(1, 'pools')
            ->assertJsonPath('pools.0', ['id' => $this->pool->id, 'name' => 'Backyard Pool', 'customer' => 'Jan Harris', 'address' => '12 Ocean Dr, Tybee']);
    }
});

test('an extra visit that hasn\'t been done can be taken back off, and an emptied route goes with it', function () {
    $stop = addExtra($this->agent, $this->pool);

    $this->actingAs($this->agent)->deleteJson("/api/field/stops/{$stop->id}")->assertOk();

    expect(RouteStop::query()->exists())->toBeFalse()
        ->and(Route::query()->exists())->toBeFalse();
});

test('only an extra visit that is still waiting can be removed, and only from your own route', function () {
    $route = Route::forDay($this->tenant->id, $this->agent->id, $this->tenant->today()->toDateString());
    $planned = RouteStop::factory()->for($route)->for($this->pool)->create(['service_subscription_id' => $this->plan->id, 'stop_order' => 1, 'status' => 'pending']);
    $other = Pool::factory()->for($this->tenant)->for(Customer::factory()->for($this->tenant))->create();
    $done = addExtra($this->agent, $other);
    $this->actingAs($this->agent)->post("/api/field/visits/{$done->id}/complete", ['ph' => 7.4])->assertOk();
    $colleague = User::factory()->agent()->for($this->tenant)->create();
    $theirs = addExtra($colleague, $other);

    $this->actingAs($this->agent)->deleteJson("/api/field/stops/{$planned->id}")->assertStatus(422);
    $this->actingAs($this->agent)->deleteJson("/api/field/stops/{$done->id}")->assertStatus(422);
    $this->actingAs($this->agent)->deleteJson("/api/field/stops/{$theirs->id}")->assertForbidden();
    expect(RouteStop::query()->count())->toBe(3);
});
