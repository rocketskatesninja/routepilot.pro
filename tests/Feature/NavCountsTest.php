<?php

declare(strict_types=1);

use App\Models\Customer;
use App\Models\Lead;
use App\Models\Pool;
use App\Models\ServiceRequest;
use App\Models\ServiceVisit;
use App\Models\Tenant;
use App\Models\User;
use App\Models\WorkOrder;
use Inertia\Testing\AssertableInertia as Assert;

/*
 * The numbers on the sidebar's Requests, Work orders and Reports links: what's
 * waiting for each person, dropping as it gets done (Reports: when they look).
 */

beforeEach(function () {
    $this->tenant = Tenant::factory()->create();
    app()->instance('tenant_id', $this->tenant->id);
    $this->admin = User::factory()->for($this->tenant)->create();
    $this->tech = User::factory()->agent()->for($this->tenant)->create();
    $this->customer = Customer::factory()->for($this->tenant)->create();
    $this->pool = Pool::factory()->for($this->tenant)->for($this->customer)->create();
});

/** The sidebar counts a user's next page carries. */
function navCounts(User $user, string $path = '/dashboard'): array
{
    $counts = [];
    test()->actingAs($user)->get($path)->assertOk()
        ->assertInertia(function (Assert $page) use (&$counts) {
            $counts = $page->toArray()['props']['auth']['counts'];
        });

    return $counts;
}

function finishedVisit(string $when): ServiceVisit
{
    return ServiceVisit::factory()->for(test()->tenant)->for(test()->pool)->create([
        'agent_id' => test()->tech->id, 'status' => 'completed', 'visited_at' => $when, 'completed_at' => $when,
    ]);
}

test('Requests counts requests waiting for an answer plus new website leads', function () {
    ServiceRequest::create(['customer_id' => $this->customer->id, 'pool_id' => $this->pool->id, 'type' => 'service', 'message' => 'Green water']);
    ServiceRequest::create(['customer_id' => $this->customer->id, 'pool_id' => $this->pool->id, 'type' => 'hold']);
    ServiceRequest::create(['customer_id' => $this->customer->id, 'type' => 'cancel'])->forceFill(['status' => 'resolved'])->save();
    $archived = Customer::factory()->for($this->tenant)->create();
    ServiceRequest::create(['customer_id' => $archived->id, 'type' => 'service']);
    $archived->delete();
    Lead::create(['name' => 'New lead', 'email' => 'a@example.test']);
    Lead::create(['name' => 'Called back', 'email' => 'b@example.test'])->forceFill(['status' => 'contacted'])->save();

    expect(navCounts($this->admin)['requests'])->toBe(3);
});

test('Work orders counts your open ones, and for admins and Agent+ the unassigned too', function () {
    $agentPlus = User::factory()->agent()->for($this->tenant)->create();
    $agentPlus->forceFill(['agent_plus' => true])->save();
    WorkOrder::factory()->for($this->tenant)->create(['assigned_to' => $this->admin->id]);
    WorkOrder::factory()->for($this->tenant)->create(['assigned_to' => null]);
    WorkOrder::factory()->for($this->tenant)->count(2)->create(['assigned_to' => $this->tech->id]);
    WorkOrder::factory()->for($this->tenant)->create(['assigned_to' => $this->admin->id])->forceFill(['status' => 'done'])->save();
    WorkOrder::factory()->for(Tenant::factory())->create(['assigned_to' => null]); // another company's

    expect(navCounts($this->admin)['work_orders'])->toBe(2)
        ->and(navCounts($agentPlus)['work_orders'])->toBe(1)
        ->and(navCounts($this->tech))->toBe(['work_orders' => 2]); // techs: no Requests or Reports numbers
});

test('Reports counts reports filed since the admin last opened Reports, and opening it clears it', function () {
    $this->admin->forceFill(['reports_seen_at' => now()->subHours(2)])->save();
    finishedVisit(now()->subHours(3)->toDateTimeString());
    finishedVisit(now()->subHour()->toDateTimeString());
    finishedVisit(now()->subMinutes(5)->toDateTimeString());

    expect(navCounts($this->admin)['reports'])->toBe(2)
        ->and(navCounts($this->admin, '/reports')['reports'])->toBe(0) // the Reports page itself shows it cleared
        ->and(navCounts($this->admin)['reports'])->toBe(0);

    finishedVisit(now()->addMinute()->toDateTimeString());
    expect(navCounts($this->admin)['reports'])->toBe(1);
});

test('customers and platform admins get no sidebar numbers', function () {
    $login = User::factory()->customer()->for($this->tenant)->create();
    $this->customer->forceFill(['user_id' => $login->id])->save();

    expect(navCounts($login))->toBe([])
        ->and(navCounts(User::factory()->superAdmin()->create()))->toBe([]);
});
