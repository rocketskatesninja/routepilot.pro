<?php

declare(strict_types=1);

use App\Models\Charge;
use App\Models\ChemicalInventory;
use App\Models\Customer;
use App\Models\Pool;
use App\Models\Route;
use App\Models\RouteStop;
use App\Models\ServiceSubscription;
use App\Models\ServiceType;
use App\Models\ServiceVisit;
use App\Models\Tenant;
use App\Models\User;
use App\Services\BillingService;

/*
 * Chemicals billed per visit: a customer whose service doesn't include chemicals
 * pays for what was used at their pool, each chemical's whole units at its sell
 * price, on one "Chemicals" line beside the visit charge.
 */

beforeEach(function () {
    $this->tenant = Tenant::factory()->create();
    app()->instance('tenant_id', $this->tenant->id);
    $this->admin = User::factory()->for($this->tenant)->create();
    $this->agent = User::factory()->agent()->for($this->tenant)->create();
    $this->customer = Customer::factory()->for($this->tenant)->create(['bill_chemicals' => true]);
    $this->pool = Pool::factory()->for($this->tenant)->for($this->customer)->create(['name' => 'Anderson Pool']);
    $this->plan = ServiceSubscription::factory()->for($this->tenant)->for($this->pool)
        ->for(ServiceType::factory()->for($this->tenant)->create(['price' => 50]))
        ->create(['assigned_agent_id' => $this->agent->id, 'status' => 'active']);
    $chemical = fn (string $name, string $unit, ?float $price) => ChemicalInventory::factory()->for($this->tenant)
        ->create(['chemical_name' => $name, 'unit' => $unit, 'current_stock' => 20, 'sell_price' => $price]);
    $this->acid = $chemical('Muriatic Acid', 'gal', 4.50);
    $this->calHypo = $chemical('Cal Hypo', 'lbs', 6.50);
    $this->sodaAsh = $chemical('Soda Ash', 'lbs', null); // no sell price: not billed
});

/** The tech finishes today's stop at the pool with these chemicals. */
function chemicalVisit(array $treatments): ServiceVisit
{
    $route = Route::forDay(test()->tenant->id, test()->agent->id, test()->tenant->today()->toDateString());
    $stop = RouteStop::factory()->for($route)->for(test()->pool)->create(['service_subscription_id' => test()->plan->id, 'status' => 'pending']);
    test()->actingAs(test()->agent)->post("/api/field/visits/{$stop->id}/complete", ['ph' => 7.4, 'treatments' => $treatments])->assertOk();

    return ServiceVisit::query()->where('route_stop_id', $stop->id)->sole();
}

function chemicalsBalance(): float
{
    return app(BillingService::class)->outstandingForCustomer(test()->customer);
}

test('a customer who pays for chemicals gets one chemicals line beside the visit charge', function () {
    chemicalVisit([
        ['chemical_id' => $this->acid->id, 'amount' => 0.3],    // rounds up to 1 gal
        ['chemical_id' => $this->calHypo->id, 'amount' => 1.5], // 2 lbs
        ['chemical_id' => $this->sodaAsh->id, 'amount' => 3],   // no price
    ]);

    $chemicals = Charge::query()->where('kind', 'chemicals')->sole();
    expect($chemicals->description)->toBe('Chemicals — Anderson Pool: Muriatic Acid 1 gal, Cal Hypo 2 lbs')
        ->and((float) $chemicals->amount)->toBe(17.5) // 1 × 4.50 + 2 × 6.50
        ->and(Charge::query()->where('kind', 'visit')->count())->toBe(1)
        ->and(chemicalsBalance())->toBe(67.5);
});

test('salt is billed by the bag, and the line says so', function () {
    $salt = ChemicalInventory::factory()->for($this->tenant)->create(['chemical_name' => 'Salt', 'unit' => 'bag', 'current_stock' => 10, 'sell_price' => 9.00]);

    chemicalVisit([['chemical_id' => $salt->id, 'amount' => 1.2]]); // 48 lbs suggested → 2 bags

    $line = Charge::query()->where('kind', 'chemicals')->sole();
    expect($line->description)->toBe('Chemicals — Anderson Pool: Salt 2 bags')
        ->and((float) $line->amount)->toBe(18.0)
        ->and((float) $salt->fresh()?->current_stock)->toBe(8.0);
});

test('a customer whose service includes chemicals is never charged for them', function () {
    $this->customer->update(['bill_chemicals' => false]);

    chemicalVisit([['chemical_id' => $this->acid->id, 'amount' => 2]]);

    expect(Charge::query()->where('kind', 'chemicals')->exists())->toBeFalse()
        ->and(chemicalsBalance())->toBe(50.0)
        ->and((float) $this->acid->fresh()?->current_stock)->toBe(18.0); // still taken off the shelf
});

test('an extra visit with no plan still bills its chemicals', function () {
    $stopId = $this->actingAs($this->agent)->postJson("/api/field/pools/{$this->pool->id}/visit")->assertOk()->json('stop_id');
    $this->actingAs($this->agent)->post("/api/field/visits/{$stopId}/complete", ['treatments' => [['chemical_id' => $this->acid->id, 'amount' => 2]]])->assertOk();

    expect(Charge::query()->pluck('kind')->all())->toBe(['chemicals'])
        ->and(chemicalsBalance())->toBe(9.0);
});

test('the chemicals line is made once: the first time a report has chemicals, not again on later edits', function () {
    $visit = chemicalVisit([]);
    expect(Charge::query()->where('kind', 'chemicals')->exists())->toBeFalse();

    $edit = fn (float $gallons) => $this->actingAs($this->admin)
        ->patch("/reports/{$visit->id}", ['treatments' => [['chemical_id' => $this->acid->id, 'amount' => $gallons]]])->assertRedirect();

    $edit(2); // chemicals added in the office: billed now
    expect((float) Charge::query()->where('kind', 'chemicals')->sole()->amount)->toBe(9.0);

    $edit(3); // changed again: stock follows, the bill is left for Balances
    expect((float) Charge::query()->where('kind', 'chemicals')->sole()->amount)->toBe(9.0)
        ->and((float) $this->acid->fresh()?->current_stock)->toBe(17.0);
});

test('deleting the report cancels both its charges and puts the chemicals back', function () {
    $visit = chemicalVisit([['chemical_id' => $this->acid->id, 'amount' => 1]]);
    expect(chemicalsBalance())->toBe(54.5);

    $this->actingAs($this->admin)->delete("/reports/{$visit->id}")->assertSessionHas('success');

    expect(Charge::query()->active()->exists())->toBeFalse()
        ->and(Charge::query()->count())->toBe(2)
        ->and(chemicalsBalance())->toBe(0.0)
        ->and((float) $this->acid->fresh()?->current_stock)->toBe(20.0);
});
