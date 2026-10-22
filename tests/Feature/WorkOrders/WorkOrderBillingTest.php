<?php

declare(strict_types=1);

use App\Models\Charge;
use App\Models\Customer;
use App\Models\Pool;
use App\Models\Route;
use App\Models\RouteStop;
use App\Models\Tenant;
use App\Models\User;
use App\Models\WorkOrder;
use Illuminate\Testing\TestResponse;
use Inertia\Testing\AssertableInertia as Assert;

/*
 * Billing a finished work order: it can carry a price; an admin who marks it
 * done is offered to bill it; "Add a charge" opens the Balances form filled in
 * from it; the charge remembers the work order, so it shows as billed and is
 * never billed twice.
 */

beforeEach(function () {
    $this->tenant = Tenant::factory()->create();
    app()->instance('tenant_id', $this->tenant->id);
    $this->admin = User::factory()->for($this->tenant)->create();
    $this->agentPlus = User::factory()->agent()->for($this->tenant)->create();
    $this->agentPlus->forceFill(['agent_plus' => true])->save();
    $this->customer = Customer::factory()->for($this->tenant)->create(['first_name' => 'Robert', 'last_name' => 'Anderson']);
    $this->pool = Pool::factory()->for($this->tenant)->for($this->customer)->create(['name' => 'Anderson Pool']);
    $this->order = WorkOrder::factory()->for($this->tenant)->create(['title' => 'Replace the skimmer lid', 'price' => 85, 'pool_id' => $this->pool->id]);
});

/** The work order's detail as a user sees it on the Work orders page. */
function billingDetail(User $user, WorkOrder $order): array
{
    $detail = [];
    test()->actingAs($user)->get("/work-orders?status={$order->fresh()?->status}&selected={$order->id}")->assertOk()
        ->assertInertia(function (Assert $page) use (&$detail) {
            $detail = $page->toArray()['props']['selected'];
        });

    return $detail;
}

/** The add-charge prefill the Balances page sends for ?work_order=id. */
function chargePrefill(User $user, int $orderId): ?array
{
    $prefill = [];
    test()->actingAs($user)->get("/balances?work_order={$orderId}")->assertOk()
        ->assertInertia(function (Assert $page) use (&$prefill) {
            $prefill = $page->toArray()['props']['chargePrefill'];
        });

    return $prefill;
}

function billIt(array $overrides = []): TestResponse
{
    return test()->actingAs(test()->admin)->post('/balances/charges', [
        'customer_id' => test()->customer->id, 'description' => 'Replace the skimmer lid — Anderson Pool', 'amount' => 85, 'taxable' => false,
        'work_order_id' => test()->order->id, ...$overrides,
    ]);
}

test('a work order carries a price, from the office form and from the field', function () {
    $this->actingAs($this->admin)->post('/work-orders', ['title' => 'New heater igniter', 'price' => '42.50'])->assertRedirect();
    expect(WorkOrder::query()->where('title', 'New heater igniter')->sole()->price)->toBe('42.50');

    $tech = User::factory()->agent()->for($this->tenant)->create();
    $route = Route::factory()->for($this->tenant)->create(['agent_id' => $tech->id, 'scheduled_date' => $this->tenant->today()]);
    $stop = RouteStop::factory()->for($route)->for($this->pool)->create(['stop_order' => 1, 'status' => 'pending']);
    $this->actingAs($tech)->postJson("/api/field/visits/{$stop->id}/complete", ['work_order_title' => 'Cracked return fitting', 'work_order_price' => '18'])->assertOk();

    expect(WorkOrder::query()->where('title', 'Cracked return fitting')->sole()->price)->toBe('18.00')
        ->and(billingDetail($this->admin, $this->order)['price'])->toBe('85.00');
});

test('once it\'s done, an admin is offered to bill it; Agent+ (who can\'t add charges) and open ones are not', function () {
    expect(billingDetail($this->admin, $this->order)['can']['bill'])->toBeFalse(); // still open

    $this->actingAs($this->admin)->post("/work-orders/{$this->order->id}/done")->assertRedirect();
    $poolless = WorkOrder::factory()->for($this->tenant)->create(['title' => 'Order chlorine tabs']);
    $poolless->forceFill(['status' => 'done', 'completed_at' => now()])->save();

    expect(billingDetail($this->admin, $this->order)['can']['bill'])->toBeTrue()
        ->and(billingDetail($this->agentPlus, $this->order)['can']['bill'])->toBeFalse()
        ->and(billingDetail($this->admin, $poolless)['can']['bill'])->toBeFalse(); // no customer to bill
});

test('"Add a charge" opens the Balances form filled in from the work order, for admins only', function () {
    expect(chargePrefill($this->admin, $this->order->id))->toBe([
        'work_order_id' => $this->order->id,
        'work_order' => 'Replace the skimmer lid',
        'customer_id' => $this->customer->id,
        'description' => 'Replace the skimmer lid — Anderson Pool',
        'amount' => '85.00',
    ])
        ->and(chargePrefill($this->agentPlus, $this->order->id))->toBeNull()
        ->and(chargePrefill($this->admin, 999999))->toBeNull();
});

test('the charge remembers the work order: back to it, shown as billed, never offered again', function () {
    $this->order->forceFill(['status' => 'done', 'completed_at' => now()])->save();

    billIt()->assertRedirect(route('work-orders.index', ['status' => 'done', 'selected' => $this->order->id]))
        ->assertSessionHas('success', 'Charge added to Robert Anderson’s balance.');

    $charge = Charge::query()->sole();
    $detail = billingDetail($this->admin, $this->order);
    expect($charge->work_order_id)->toBe($this->order->id)
        ->and($charge->pool_id)->toBe($this->pool->id)
        ->and((float) $charge->amount)->toBe(85.0)
        ->and($detail['billed'])->toEqual(['amount' => 85.0, 'on' => $this->tenant->today()->toDateString()])
        ->and($detail['can']['bill'])->toBeFalse()
        ->and(chargePrefill($this->admin, $this->order->id))->toBeNull();

    billIt()->assertSessionHasErrors(['work_order_id' => 'That work order has already been billed.']);
    expect(Charge::query()->count())->toBe(1);

    // Cancel the charge and it can be billed again.
    $charge->forceFill(['voided_at' => now(), 'void_reason' => 'Wrong amount'])->save();
    expect(billingDetail($this->admin, $this->order)['can']['bill'])->toBeTrue();
});

test('a work order can\'t be billed to someone else, or from another company', function () {
    $someoneElse = Customer::factory()->for($this->tenant)->create();
    billIt(['customer_id' => $someoneElse->id])->assertSessionHasErrors(['work_order_id' => 'That work order is for a different customer.']);

    $theirs = WorkOrder::factory()->for(Tenant::factory())->create();
    billIt(['work_order_id' => $theirs->id])->assertSessionHasErrors('work_order_id');

    expect(Charge::query()->count())->toBe(0);
});
