<?php

declare(strict_types=1);

use App\Models\Customer;
use App\Models\Pool;
use App\Models\Route;
use App\Models\RouteStop;
use App\Models\Tenant;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

/*
 * Opening a stop in the field app marks it "in progress" on the office's schedule.
 * A tech is at one pool at a time, and an in-progress stop is still a visit to do.
 */

beforeEach(function () {
    $this->tenant = Tenant::factory()->create();
    app()->instance('tenant_id', $this->tenant->id);
    $this->admin = User::factory()->for($this->tenant)->create();
    $this->agent = User::factory()->agent()->for($this->tenant)->create();
    $this->customer = Customer::factory()->for($this->tenant)->create();
    $this->route = Route::factory()->for($this->tenant)->create(['agent_id' => $this->agent->id, 'scheduled_date' => $this->tenant->today()]);
    $this->first = progressStop($this->route, 1);
    $this->second = progressStop($this->route, 2);
});

function progressStop(Route $route, int $order, string $status = 'pending'): RouteStop
{
    $pool = Pool::factory()->for(test()->tenant)->for(test()->customer)->create();

    return RouteStop::factory()->for($route)->for($pool)->create(['stop_order' => $order, 'status' => $status]);
}

test('opening a stop marks it in progress, and the one opened before goes back to pending', function () {
    $this->actingAs($this->agent)->postJson("/api/field/stops/{$this->first->id}/start")->assertOk()->assertJson(['started' => true]);
    expect($this->first->fresh()->status)->toBe('in_progress');

    $this->postJson("/api/field/stops/{$this->second->id}/start")->assertOk();
    expect($this->first->fresh()->status)->toBe('pending')
        ->and($this->second->fresh()->status)->toBe('in_progress');
});

test('a finished stop, or one on another day, is left as it is', function () {
    $done = progressStop($this->route, 3, 'completed');
    $tomorrow = Route::factory()->for($this->tenant)->create(['agent_id' => $this->agent->id, 'scheduled_date' => $this->tenant->today()->addDay()]);
    $later = progressStop($tomorrow, 1);

    $this->actingAs($this->agent)->postJson("/api/field/stops/{$done->id}/start")->assertOk()->assertJson(['started' => false]);
    $this->postJson("/api/field/stops/{$later->id}/start")->assertOk()->assertJson(['started' => false]);

    expect($done->fresh()->status)->toBe('completed')->and($later->fresh()->status)->toBe('pending');
});

test('only the tech on the route starts it: another tech is refused, an admin changes nothing', function () {
    $other = User::factory()->agent()->for($this->tenant)->create();
    $this->actingAs($other)->postJson("/api/field/stops/{$this->first->id}/start")->assertForbidden();

    $this->actingAs($this->admin)->postJson("/api/field/stops/{$this->first->id}/start")->assertOk()->assertJson(['started' => false]);
    expect($this->first->fresh()->status)->toBe('pending');
});

test('support signed in as the tech changes nothing', function () {
    $this->actingAs($this->agent)->withSession(['impersonator_id' => $this->admin->id])
        ->postJson("/api/field/stops/{$this->first->id}/start")->assertOk()->assertJson(['started' => false]);

    expect($this->first->fresh()->status)->toBe('pending');
});

test('an in-progress stop can still be skipped, and an in-progress extra visit removed', function () {
    $this->first->update(['status' => 'in_progress']);
    $this->actingAs($this->admin)->post("/stops/{$this->first->id}/skip");
    expect($this->first->fresh()->status)->toBe('skipped');

    $extra = progressStop($this->route, 3, 'in_progress'); // no plan: an extra visit
    $this->actingAs($this->agent)->deleteJson("/api/field/stops/{$extra->id}")->assertOk();
    expect(RouteStop::query()->find($extra->id))->toBeNull();
});

test('the customer still sees an in-progress visit as their upcoming one', function () {
    $user = User::factory()->customer()->for($this->tenant)->create(['dashboard_style' => 'simple']);
    $this->customer->forceFill(['user_id' => $user->id])->save();
    RouteStop::query()->whereKey($this->second->id)->delete();
    $this->first->update(['status' => 'in_progress']);

    $this->actingAs($user)->get('/dashboard')->assertInertia(fn (Assert $page) => $page
        ->has('upcoming', 1)
        ->where('upcoming.0.date', $this->tenant->today()->toDateString()));
});
