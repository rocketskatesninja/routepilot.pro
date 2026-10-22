<?php

declare(strict_types=1);

use App\Dashboard\DashboardWidgets;
use App\Models\Customer;
use App\Models\Pool;
use App\Models\Route;
use App\Models\RouteStop;
use App\Models\Tenant;
use App\Models\User;
use App\Models\WorkOrder;
use Inertia\Testing\AssertableInertia as Assert;

/*
 * Work orders where people already look: the dashboard widget (the office and
 * Agent+ see the whole list, a tech their own) and the field visit screen (the
 * pool's open ones, read-only, in the day bundle so they show offline).
 */

beforeEach(function () {
    $this->tenant = Tenant::factory()->create();
    app()->instance('tenant_id', $this->tenant->id);
    $this->admin = User::factory()->for($this->tenant)->create();
    $this->agentPlus = User::factory()->agent()->for($this->tenant)->create();
    $this->agentPlus->forceFill(['agent_plus' => true])->save();
    $this->tech = User::factory()->agent()->for($this->tenant)->create(['first_name' => 'Marcus', 'last_name' => 'Bennett']);
    $this->pool = Pool::factory()->for($this->tenant)->for(Customer::factory()->for($this->tenant))->create(['name' => 'Anderson Pool']);
});

/** The work orders widget's data as a user's dashboard sends it. */
function workOrdersWidget(User $user): array
{
    $data = [];
    test()->actingAs($user)->get('/dashboard')->assertOk()
        ->assertInertia(function (Assert $page) use (&$data) {
            $data = $page->toArray()['props']['widgets']['work_orders'];
        });

    return $data;
}

test('the dashboard widget lists open work orders, most pressing first, five at most', function () {
    $tenantToday = $this->tenant->today();
    WorkOrder::factory()->for($this->tenant)->create(['title' => 'Someday']);
    WorkOrder::factory()->for($this->tenant)->create(['title' => 'Due next week', 'due_on' => $tenantToday->copy()->addWeek()]);
    WorkOrder::factory()->for($this->tenant)->create(['title' => 'Overdue', 'due_on' => $tenantToday->copy()->subDay()]);
    WorkOrder::factory()->for($this->tenant)->create(['title' => 'Urgent', 'urgent' => true, 'assigned_to' => $this->tech->id]);
    WorkOrder::factory()->for($this->tenant)->create(['title' => 'Also someday']);
    WorkOrder::factory()->for($this->tenant)->create(['title' => 'Sixth']);
    WorkOrder::factory()->for($this->tenant)->create(['title' => 'Finished'])->forceFill(['status' => 'done', 'completed_at' => now()])->save();

    $widget = workOrdersWidget($this->admin);

    expect(array_column($widget['orders'], 'title'))->toBe(['Urgent', 'Overdue', 'Due next week', 'Sixth', 'Also someday'])
        ->and($widget['open'])->toBe(6)
        ->and($widget['mine'])->toBeFalse()
        ->and($widget['orders'][0]['assignee'])->toBe('Marcus Bennett')
        ->and($widget['orders'][1]['overdue'])->toBeTrue();
});

test('Agent+ sees the whole list on the dashboard; a tech sees only theirs', function () {
    WorkOrder::factory()->for($this->tenant)->create(['title' => 'Theirs', 'assigned_to' => $this->tech->id]);
    WorkOrder::factory()->for($this->tenant)->create(['title' => 'Nobody yet']);
    WorkOrder::factory()->for(Tenant::factory())->create(['title' => 'Another company']);

    $tech = workOrdersWidget($this->tech);

    expect(array_column(workOrdersWidget($this->agentPlus)['orders'], 'title'))->toEqualCanonicalizing(['Theirs', 'Nobody yet'])
        ->and(array_column($tech['orders'], 'title'))->toBe(['Theirs'])
        ->and($tech['open'])->toBe(1)
        ->and($tech['mine'])->toBeTrue();
});

test('the widget is on the office and agent grids by default', function () {
    expect(DashboardWidgets::layoutsFor($this->admin)['desktop'])->toContain(['i' => 'work_orders', 'x' => 6, 'y' => 9, 'w' => 6, 'h' => 5])
        ->and(collect(DashboardWidgets::layoutsFor($this->tech)['mobile'])->pluck('i'))->toContain('work_orders');
});

test('the field visit shows the pool\'s open work orders, read-only, in the day bundle', function () {
    $route = Route::factory()->for($this->tenant)->create(['agent_id' => $this->tech->id, 'scheduled_date' => $this->tenant->today()]);
    RouteStop::factory()->for($route)->for($this->pool)->create(['stop_order' => 1, 'status' => 'pending']);
    $elsewhere = Pool::factory()->for($this->tenant)->for(Customer::factory()->for($this->tenant))->create();

    WorkOrder::factory()->for($this->tenant)->create(['pool_id' => $this->pool->id, 'title' => 'Order a pump seal', 'details' => 'Two of them', 'assigned_to' => $this->agentPlus->id]);
    WorkOrder::factory()->for($this->tenant)->create(['pool_id' => $this->pool->id, 'title' => 'Fix the light', 'urgent' => true, 'assigned_to' => $this->tech->id]);
    WorkOrder::factory()->for($this->tenant)->create(['pool_id' => $this->pool->id, 'title' => 'Done already'])->forceFill(['status' => 'done', 'completed_at' => now()])->save();
    WorkOrder::factory()->for($this->tenant)->create(['pool_id' => $elsewhere->id, 'title' => 'Another pool']);

    $this->actingAs($this->tech)->getJson('/api/field/today')->assertOk()
        ->assertJsonCount(2, 'stops.0.work_orders')
        ->assertJsonPath('stops.0.work_orders.0.title', 'Fix the light') // urgent first
        ->assertJsonPath('stops.0.work_orders.0.assignee', 'you')
        ->assertJsonPath('stops.0.work_orders.1.title', 'Order a pump seal')
        ->assertJsonPath('stops.0.work_orders.1.details', 'Two of them')
        ->assertJsonPath('stops.0.work_orders.1.assignee', $this->agentPlus->displayName());
});
