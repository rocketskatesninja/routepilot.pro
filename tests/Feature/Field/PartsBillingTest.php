<?php

declare(strict_types=1);

use App\Mail\VisitRecapMail;
use App\Models\Charge;
use App\Models\ChemicalInventory;
use App\Models\Customer;
use App\Models\Pool;
use App\Models\Route;
use App\Models\RouteStop;
use App\Models\ServiceVisit;
use App\Models\Tenant;
use App\Models\User;
use App\Support\PortalVisit;

/*
 * Repair parts used on a visit are billed to every customer (a part with a sell
 * price is its own "Parts" line, taxed like an added charge), apart from the
 * chemicals, which only some customers pay for. Customers see the parts on their
 * report, without the part numbers.
 */

beforeEach(function () {
    $this->tenant = Tenant::factory()->create();
    $this->tenant->forceFill(['tax_rate' => 0.07])->save();
    app()->instance('tenant_id', $this->tenant->id);
    $this->admin = User::factory()->for($this->tenant)->create();
    $this->agent = User::factory()->agent()->for($this->tenant)->create();
    $this->customer = Customer::factory()->for($this->tenant)->create(['bill_chemicals' => false]);
    $this->pool = Pool::factory()->for($this->tenant)->for($this->customer)->create(['name' => 'Cruz Pool']);
    $route = Route::factory()->for($this->tenant)->create(['agent_id' => $this->agent->id, 'scheduled_date' => $this->tenant->today()]);
    $this->stop = RouteStop::factory()->for($route)->for($this->pool)->create(['status' => 'pending', 'stop_order' => 1]);
    $item = fn (array $a) => ChemicalInventory::factory()->for($this->tenant)->create(['current_stock' => 50, ...$a]);
    $this->oring = $item(['chemical_name' => 'Pump lid O-ring', 'kind' => 'part', 'part_number' => 'R172009', 'unit' => 'unit', 'sell_price' => 12.50]);
    $this->gasket = $item(['chemical_name' => 'Filter gasket', 'kind' => 'part', 'unit' => 'unit', 'sell_price' => null]);
    $this->chlorine = $item(['chemical_name' => 'Liquid Chlorine', 'unit' => 'gal', 'sell_price' => 6]);
});

function finishWithParts(array $payload): ServiceVisit
{
    test()->actingAs(test()->agent)->postJson('/api/field/visits/'.test()->stop->id.'/complete', $payload)->assertOk();

    return ServiceVisit::query()->sole();
}

test('parts are billed to every customer, on their own taxed line; chemicals only to those who pay for them', function () {
    $visit = finishWithParts([
        'treatments' => [['chemical_id' => $this->chlorine->id, 'amount' => 2]],
        'parts' => [['part_id' => $this->oring->id, 'amount' => 2], ['part_id' => $this->gasket->id, 'amount' => 1]],
    ]);

    $parts = Charge::query()->where('kind', 'parts')->sole();
    expect($parts->only(['service_visit_id', 'description', 'taxable']))->toBe(['service_visit_id' => $visit->id, 'description' => 'Parts — Cruz Pool: Pump lid O-ring ×2', 'taxable' => true])
        ->and((float) $parts->amount)->toBe(25.0) // the gasket has no sell price
        ->and((float) $parts->tax)->toBe(1.75)
        ->and(Charge::query()->where('kind', 'chemicals')->exists())->toBeFalse(); // this customer doesn't pay for chemicals
});

test('a customer who pays for chemicals gets both lines, each with only its own', function () {
    $this->customer->update(['bill_chemicals' => true]);

    finishWithParts([
        'treatments' => [['chemical_id' => $this->chlorine->id, 'amount' => 2]],
        'parts' => [['part_id' => $this->oring->id, 'amount' => 1]],
    ]);

    expect(Charge::query()->where('kind', 'chemicals')->sole()->only(['description', 'amount']))->toBe(['description' => 'Chemicals — Cruz Pool: Liquid Chlorine 2 gal', 'amount' => '12.00'])
        ->and((float) Charge::query()->where('kind', 'parts')->sole()->amount)->toBe(12.5);
});

test('the parts charge is made once; editing the report later doesn’t re-price it', function () {
    $visit = finishWithParts(['parts' => [['part_id' => $this->oring->id, 'amount' => 1]]]);

    $this->actingAs($this->admin)->patch("/reports/{$visit->id}", ['parts' => [['part_id' => $this->oring->id, 'amount' => 4]]])->assertRedirect();

    expect(Charge::query()->where('kind', 'parts')->count())->toBe(1)
        ->and((float) Charge::query()->where('kind', 'parts')->sole()->amount)->toBe(12.5)
        ->and((float) $this->oring->fresh()?->current_stock)->toBe(46.0);
});

test('customers see the parts replaced on their report and email, without part numbers', function () {
    $visit = finishWithParts([
        'treatments' => [['chemical_id' => $this->chlorine->id, 'amount' => 2]],
        'parts' => [['part_id' => $this->oring->id, 'amount' => 1]],
    ]);

    $portal = PortalVisit::toArray(ServiceVisit::query()->with(PortalVisit::WITH)->findOrFail($visit->id));
    expect(array_column($portal['treatments'], 'name'))->toBe(['Liquid Chlorine'])
        ->and($portal['parts'])->toBe([['name' => 'Pump lid O-ring', 'amount' => 1.0]]);

    $html = (new VisitRecapMail($visit, 0.0, null))->render();
    expect($html)->toContain('Parts replaced')->toContain('1 part replaced')->toContain('1 chemical added')
        ->toContain('Pump lid O-ring')->not->toContain('R172009');
});
