<?php

declare(strict_types=1);

use App\Actions\CreateWorkOrder;
use App\Models\Customer;
use App\Models\Pool;
use App\Models\Route;
use App\Models\RouteStop;
use App\Models\ServiceVisit;
use App\Models\Tenant;
use App\Models\User;
use App\Models\WorkOrder;
use App\Notifications\WorkOrderRaised;
use Illuminate\Support\Facades\Notification;
use Inertia\Testing\AssertableInertia as Assert;

/*
 * A tech can raise a work order from a visit: something that needs doing later.
 * It lands on the company's list once per visit, and the office hears about it.
 */

beforeEach(function () {
    $this->tenant = Tenant::factory()->create();
    app()->instance('tenant_id', $this->tenant->id);
    $this->admin = User::factory()->for($this->tenant)->create();
    $this->agent = User::factory()->agent()->for($this->tenant)->create(['first_name' => 'Marcus']);
    $this->pool = Pool::factory()->for($this->tenant)->for(Customer::factory()->for($this->tenant))->create(['name' => 'Anderson Pool']);
    $route = Route::factory()->for($this->tenant)->create(['agent_id' => $this->agent->id, 'scheduled_date' => $this->tenant->today()]);
    $this->stop = RouteStop::factory()->for($route)->for($this->pool)->create(['stop_order' => 1, 'status' => 'pending']);
});

/** Finish the test stop from the field app as the tech. */
function finishWith(array $data): void
{
    test()->actingAs(test()->agent)->postJson('/api/field/visits/'.test()->stop->id.'/complete', $data)->assertOk();
}

test('a work order raised on a visit lands on the company list, once', function () {
    $text = "Skimmer lid is cracked\nOrder a new one from the supplier";
    $visit = ['office_notes' => 'Gate code changed', 'work_order' => $text, 'work_order_urgent' => true];
    finishWith($visit);
    finishWith($visit); // the same visit sent again (a replay from the phone)

    $order = WorkOrder::query()->sole();
    $visit = ServiceVisit::query()->where('route_stop_id', $this->stop->id)->sole();
    expect($order->title)->toBe('Skimmer lid is cracked')
        ->and($order->details)->toBe('Order a new one from the supplier')
        ->and($order->urgent)->toBeTrue()
        ->and($order->status)->toBe('open')
        ->and($order->pool_id)->toBe($this->pool->id)
        ->and($order->service_visit_id)->toBe($visit->id)
        ->and($order->created_by)->toBe($this->agent->id)
        ->and($order->tenant_id)->toBe($this->tenant->id)
        ->and($visit->office_notes)->toBe('Gate code changed'); // the office note stays its own thing
});

test('a visit with nothing in the work order box raises none', function () {
    finishWith(['office_notes' => 'All good', 'work_order' => "  \n ", 'work_order_urgent' => true]);

    expect(WorkOrder::query()->count())->toBe(0);
});

test('the office hears about it: admins and Agent+, not the tech who raised it, other techs or other companies', function () {
    $agentPlus = User::factory()->agent()->for($this->tenant)->create();
    $agentPlus->forceFill(['agent_plus' => true])->save();
    $otherTech = User::factory()->agent()->for($this->tenant)->create();
    $inactive = User::factory()->for($this->tenant)->create();
    $inactive->forceFill(['is_active' => false])->save();
    $elsewhere = User::factory()->for(Tenant::factory())->create();
    Notification::fake();

    finishWith(['work_order' => 'Pump is making a grinding noise', 'work_order_urgent' => true]);

    Notification::assertSentTo([$this->admin, $agentPlus], WorkOrderRaised::class, function (WorkOrderRaised $n, array $channels, object $to): bool {
        $alert = $n->toArray($to);

        return $channels === ['database', 'broadcast']
            && $alert['title'] === 'Urgent work order: Pump is making a grinding noise'
            && $alert['body'] === 'Anderson Pool — raised by '.$this->agent->displayName();
    });
    Notification::assertNotSentTo([$this->agent, $otherTech, $inactive, $elsewhere], WorkOrderRaised::class);
});

test('a long first line is cut to fit the title, and then the details keep the whole text', function () {
    $line = str_repeat('Replace the cracked return fitting ', 8);

    $parts = CreateWorkOrder::fromText($line);

    expect(mb_strlen($parts['title']))->toBeLessThanOrEqual(160)
        ->and($parts['details'])->toBe(trim($line))
        ->and(CreateWorkOrder::fromText('Order chlorine tabs'))->toBe(['title' => 'Order chlorine tabs', 'details' => null]);
});

test('the report shows the work order its visit raised, and deleting the report keeps the work order', function () {
    finishWith(['work_order' => 'Order a new skimmer basket']);
    $visit = ServiceVisit::query()->where('route_stop_id', $this->stop->id)->sole();

    $this->actingAs($this->admin)
        ->get("/reports?selected={$visit->id}")
        ->assertInertia(fn (Assert $page) => $page
            ->where('selected.work_order.title', 'Order a new skimmer basket')
            ->where('selected.work_order.status', 'open'));

    $this->actingAs($this->admin)->delete("/reports/{$visit->id}")->assertRedirect();

    expect(WorkOrder::query()->sole()->service_visit_id)->toBeNull();
});
