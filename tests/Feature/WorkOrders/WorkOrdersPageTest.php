<?php

declare(strict_types=1);

use App\Models\AuditLog;
use App\Models\Customer;
use App\Models\Pool;
use App\Models\Tenant;
use App\Models\User;
use App\Models\WorkOrder;
use App\Models\WorkOrderComment;
use App\Notifications\WorkOrderAssigned;
use Illuminate\Support\Facades\Notification;
use Inertia\Testing\AssertableInertia as Assert;

/*
 * The Work orders page: admins and Agent+ run the whole list; a regular tech
 * sees, notes and closes only the work orders assigned to them.
 */

beforeEach(function () {
    $this->tenant = Tenant::factory()->create();
    app()->instance('tenant_id', $this->tenant->id);
    $this->admin = User::factory()->for($this->tenant)->create();
    $this->agentPlus = User::factory()->agent()->for($this->tenant)->create();
    $this->agentPlus->forceFill(['agent_plus' => true])->save();
    $this->tech = User::factory()->agent()->for($this->tenant)->create();
    $this->otherTech = User::factory()->agent()->for($this->tenant)->create();
    $this->pool = Pool::factory()->for($this->tenant)->for(Customer::factory()->for($this->tenant))->create(['name' => 'Anderson Pool']);
});

/** A work order in the test company. */
function workOrder(array $attributes = []): WorkOrder
{
    return WorkOrder::factory()->create(['tenant_id' => test()->tenant->id, ...$attributes]);
}

/** The titles on the list a user sees. */
function listedFor(User $user, string $query = ''): array
{
    $titles = [];
    test()->actingAs($user)->get('/work-orders'.$query)->assertOk()
        ->assertInertia(function (Assert $page) use (&$titles) {
            $titles = array_column($page->toArray()['props']['orders']['data'], 'title');
        });

    return $titles;
}

test('the office and Agent+ see every work order; a tech sees only the ones assigned to them', function () {
    workOrder(['title' => 'Theirs', 'assigned_to' => $this->tech->id]);
    workOrder(['title' => 'Nobody yet']);
    workOrder(['title' => 'Someone else', 'assigned_to' => $this->otherTech->id]);
    workOrder(['title' => 'For Agent+', 'assigned_to' => $this->agentPlus->id]);

    expect(listedFor($this->admin))->toHaveCount(4)
        ->and(listedFor($this->agentPlus))->toHaveCount(4)
        ->and(listedFor($this->agentPlus, '?mine=1'))->toBe(['For Agent+'])
        ->and(listedFor($this->tech))->toBe(['Theirs'])
        ->and(listedFor($this->tech, '?mine=0'))->toBe(['Theirs']); // "Mine" can't widen a tech's list
});

test('open work orders sort urgent first, then by due date (overdue first, none last), then newest', function () {
    $today = $this->tenant->today();
    workOrder(['title' => 'Due tomorrow', 'due_on' => $today->copy()->addDay()->toDateString()]);
    workOrder(['title' => 'Overdue', 'due_on' => $today->copy()->subDay()->toDateString()]);
    workOrder(['title' => 'No date, older']);
    workOrder(['title' => 'Urgent', 'urgent' => true]);
    workOrder(['title' => 'No date, newer']);
    workOrder(['title' => 'Finished', 'status' => 'done']);

    expect(listedFor($this->admin))->toBe(['Urgent', 'Overdue', 'Due tomorrow', 'No date, newer', 'No date, older'])
        ->and(listedFor($this->admin, '?status=done'))->toBe(['Finished']);

    $this->actingAs($this->admin)->get('/work-orders')
        ->assertInertia(fn (Assert $page) => $page->where('orders.data.1.overdue', true)->where('orders.data.2.overdue', false));
});

test('a tech can see, note and close their own work order, but not edit, add or delete one', function () {
    $theirs = workOrder(['title' => 'Replace the skimmer lid', 'assigned_to' => $this->tech->id]);
    $notTheirs = workOrder(['assigned_to' => $this->otherTech->id]);

    $this->actingAs($this->tech)->get("/work-orders?selected={$theirs->id}")
        ->assertInertia(fn (Assert $page) => $page
            ->where('selected.title', 'Replace the skimmer lid')
            ->where('selected.can', ['update' => false, 'complete' => true, 'comment' => true, 'delete' => false])
            ->where('canCreate', false));
    $this->actingAs($this->tech)->post("/work-orders/{$theirs->id}/comments", ['body' => 'Ordered, arrives Friday'])->assertRedirect();
    $this->actingAs($this->tech)->post("/work-orders/{$theirs->id}/done")->assertRedirect();

    expect(WorkOrderComment::query()->sole()->body)->toBe('Ordered, arrives Friday')
        ->and($theirs->fresh()?->status)->toBe('done')
        ->and($theirs->fresh()?->completed_by)->toBe($this->tech->id);

    $this->actingAs($this->tech)->patch("/work-orders/{$theirs->id}", ['title' => 'Changed'])->assertForbidden();
    $this->actingAs($this->tech)->post('/work-orders', ['title' => 'New'])->assertForbidden();
    $this->actingAs($this->tech)->delete("/work-orders/{$theirs->id}")->assertForbidden();

    // Someone else's reads as not there, and can't be touched.
    $this->actingAs($this->tech)->get("/work-orders?selected={$notTheirs->id}")->assertInertia(fn (Assert $page) => $page->where('selected', null));
    $this->actingAs($this->tech)->post("/work-orders/{$notTheirs->id}/done")->assertForbidden();
    $this->actingAs($this->tech)->post("/work-orders/{$notTheirs->id}/comments", ['body' => 'Hi'])->assertForbidden();
    expect($notTheirs->fresh()?->status)->toBe('open');
});

test('admins and Agent+ add, edit and assign; the person given it hears about it', function () {
    Notification::fake();

    $this->actingAs($this->agentPlus)->post('/work-orders', [
        'title' => 'Order chlorine tabs', 'details' => 'Two buckets', 'urgent' => true,
        'due_on' => $this->tenant->today()->addDays(2)->toDateString(), 'assigned_to' => $this->tech->id, 'pool_id' => $this->pool->id,
    ])->assertRedirect();

    $order = WorkOrder::query()->sole();
    expect($order->title)->toBe('Order chlorine tabs')
        ->and($order->urgent)->toBeTrue()
        ->and($order->created_by)->toBe($this->agentPlus->id)
        ->and($order->pool_id)->toBe($this->pool->id);
    Notification::assertSentTo($this->tech, WorkOrderAssigned::class, function (WorkOrderAssigned $n, array $channels, object $to): bool {
        $alert = $n->toArray($to);

        return $alert['title'] === 'Urgent work order for you: Order chlorine tabs'
            && $alert['url'] === "/work-orders?selected={$n->workOrder->id}";
    });

    $this->actingAs($this->admin)->patch("/work-orders/{$order->id}", ['title' => 'Order chlorine tabs', 'assigned_to' => $this->otherTech->id])->assertRedirect();
    $this->actingAs($this->admin)->patch("/work-orders/{$order->id}", ['title' => 'Order chlorine tabs', 'assigned_to' => $this->admin->id])->assertRedirect();
    $this->actingAs($this->admin)->patch("/work-orders/{$order->id}", ['title' => 'Order 3 buckets', 'assigned_to' => $this->admin->id])->assertRedirect();

    Notification::assertSentTo($this->otherTech, WorkOrderAssigned::class);
    Notification::assertNotSentTo($this->admin, WorkOrderAssigned::class); // they gave it to themselves
    expect(Notification::sent($this->otherTech, WorkOrderAssigned::class))->toHaveCount(1) // a later edit doesn't re-alert
        ->and($order->fresh()?->title)->toBe('Order 3 buckets');
});

test('reopening a finished work order clears who closed it', function () {
    $order = workOrder();

    $this->actingAs($this->admin)->post("/work-orders/{$order->id}/done");
    $this->actingAs($this->admin)->post("/work-orders/{$order->id}/reopen");

    expect($order->fresh()?->status)->toBe('open')
        ->and($order->fresh()?->completed_by)->toBeNull()
        ->and($order->fresh()?->completed_at)->toBeNull();
});

test('only admins delete a work order, and it is audit-logged', function () {
    $order = workOrder(['title' => 'Fix the gate latch']);

    $this->actingAs($this->agentPlus)->delete("/work-orders/{$order->id}")->assertForbidden();
    $this->actingAs($this->admin)->delete("/work-orders/{$order->id}")->assertRedirect('/work-orders');

    expect(WorkOrder::query()->find($order->id))->toBeNull()
        ->and(AuditLog::query()->where('action', 'work_order.deleted')->where('model_id', $order->id)->sole()->user_id)->toBe($this->admin->id);
});

test('the pool and the person must be from the same company', function () {
    $elsewhere = Tenant::factory()->create();
    $theirPool = Pool::factory()->for($elsewhere)->for(Customer::factory()->for($elsewhere))->create();
    $theirTech = User::factory()->agent()->for($elsewhere)->create();

    $this->actingAs($this->admin)->post('/work-orders', ['title' => 'Sneaky', 'pool_id' => $theirPool->id, 'assigned_to' => $theirTech->id])
        ->assertSessionHasErrors(['pool_id', 'assigned_to']);

    expect(WorkOrder::query()->count())->toBe(0);
});

test('another company cannot reach a work order by id', function () {
    $order = workOrder(['title' => 'Ours']);
    $outsider = User::factory()->for(Tenant::factory())->create();
    app()->forgetInstance('tenant_id');
    app()->forgetInstance('tenant');

    $this->actingAs($outsider)->get("/work-orders?selected={$order->id}")->assertInertia(fn (Assert $page) => $page->where('selected', null)->where('orders.data', []));
    $this->actingAs($outsider)->patch("/work-orders/{$order->id}", ['title' => 'Hacked'])->assertNotFound();
    $this->actingAs($outsider)->post("/work-orders/{$order->id}/done")->assertNotFound();
    $this->actingAs($outsider)->post("/work-orders/{$order->id}/comments", ['body' => 'Hi'])->assertNotFound();
    $this->actingAs($outsider)->delete("/work-orders/{$order->id}")->assertNotFound();

    expect(WorkOrder::query()->withoutGlobalScopes()->find($order->id)?->title)->toBe('Ours');
});

test('the work order page shows where it came from and its notes', function () {
    $order = workOrder(['title' => 'Pump grinding', 'details' => 'Bearing noise', 'pool_id' => $this->pool->id, 'assigned_to' => $this->tech->id]);
    $order->forceFill(['created_by' => $this->tech->id])->save();
    $this->actingAs($this->admin)->post("/work-orders/{$order->id}/comments", ['body' => 'Booked for Tuesday']);

    $this->actingAs($this->admin)->get("/work-orders?selected={$order->id}")
        ->assertInertia(fn (Assert $page) => $page
            ->where('selected.pool', 'Anderson Pool')
            ->where('selected.assignee', $this->tech->displayName())
            ->where('selected.raised_by', $this->tech->displayName())
            ->where('selected.comments.0.body', 'Booked for Tuesday')
            ->where('selected.can.delete', true)
            ->has('pools', 1)
            ->has('people', 4));
});
