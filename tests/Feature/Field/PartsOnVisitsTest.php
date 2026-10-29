<?php

declare(strict_types=1);

use App\Models\ChemicalInventory;
use App\Models\Customer;
use App\Models\Pool;
use App\Models\Route;
use App\Models\RouteStop;
use App\Models\ServiceVisit;
use App\Models\Tenant;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

/*
 * Repair parts used on a visit: picked from the company's parts in the field app
 * (on any visit), taken off the shelf in whole units, shown on the report apart
 * from the chemicals, and editable from the office like the chemicals are.
 */

beforeEach(function () {
    $this->tenant = Tenant::factory()->create();
    app()->instance('tenant_id', $this->tenant->id);
    $this->admin = User::factory()->for($this->tenant)->create();
    $this->agent = User::factory()->agent()->for($this->tenant)->create();
    $this->pool = Pool::factory()->for($this->tenant)->for(Customer::factory()->for($this->tenant))->create();
    $route = Route::factory()->for($this->tenant)->create(['agent_id' => $this->agent->id, 'scheduled_date' => $this->tenant->today()]);
    $this->stop = RouteStop::factory()->for($route)->for($this->pool)->create(['status' => 'pending', 'stop_order' => 1]);
    $this->oring = ChemicalInventory::factory()->for($this->tenant)->create(['chemical_name' => 'Pump lid O-ring', 'kind' => 'part', 'part_number' => 'R172009', 'unit' => 'unit', 'current_stock' => 0]);
    $this->oring->move('restock', 10);
    $this->chlorine = ChemicalInventory::factory()->for($this->tenant)->create(['chemical_name' => 'Liquid Chlorine', 'unit' => 'gal', 'current_stock' => 0]);
    $this->chlorine->move('restock', 20);
});

/** The report's chemicals and parts as the office sees them. */
function partsReportLists(User $admin, ServiceVisit $visit): array
{
    $out = [];
    test()->actingAs($admin)->get('/reports?selected='.$visit->id)->assertInertia(function (Assert $page) use (&$out) {
        $props = $page->toArray()['props'];
        $out = [array_column($props['selected']['treatments'], 'name'), $props['selected']['parts'], array_column($props['parts'], 'name'), array_column($props['chemicals'], 'name')];
    });

    return $out;
}

test('the field app gets each item’s kind and part number', function () {
    $inventory = collect($this->actingAs($this->agent)->getJson('/api/field/today')->assertOk()->json('inventory'))->keyBy('name');

    expect($inventory['Pump lid O-ring'])->toMatchArray(['kind' => 'part', 'part_number' => 'R172009', 'unit' => 'unit'])
        ->and($inventory['Liquid Chlorine'])->toMatchArray(['kind' => 'chemical', 'part_number' => null]);
});

test('parts used come off the shelf in whole units and show on the report apart from chemicals', function () {
    $other = ChemicalInventory::factory()->for(Tenant::factory()->create())->create(['kind' => 'part', 'unit' => 'unit', 'current_stock' => 5]);

    $this->actingAs($this->agent)->postJson("/api/field/visits/{$this->stop->id}/complete", [
        'treatments' => [['chemical_id' => $this->chlorine->id, 'amount' => 2]],
        'parts' => [['part_id' => $this->oring->id, 'amount' => 1.2], ['part_id' => $other->id, 'amount' => 1]],
    ])->assertOk();

    [$chemicals, $parts, $partChoices, $chemicalChoices] = partsReportLists($this->admin, ServiceVisit::query()->sole());
    expect((float) $this->oring->fresh()?->current_stock)->toBe(8.0) // 1.2 rounds up to 2
        ->and((float) $this->chlorine->fresh()?->current_stock)->toBe(18.0)
        ->and((float) $other->fresh()?->current_stock)->toBe(5.0) // another company's: ignored
        ->and($chemicals)->toBe(['Liquid Chlorine'])
        ->and($parts)->toBe([['part_id' => $this->oring->id, 'name' => 'Pump lid O-ring', 'part_number' => 'R172009', 'amount' => 2]])
        ->and($partChoices)->toBe(['Pump lid O-ring'])
        ->and($chemicalChoices)->toBe(['Liquid Chlorine']);
});

test('the office edits a report’s parts, and editing without them keeps them', function () {
    $this->actingAs($this->agent)->postJson("/api/field/visits/{$this->stop->id}/complete", [
        'parts' => [['part_id' => $this->oring->id, 'amount' => 3]],
    ])->assertOk();
    $visit = ServiceVisit::query()->sole();

    $this->actingAs($this->admin)->patch("/reports/{$visit->id}", ['parts' => [['part_id' => $this->oring->id, 'amount' => 1]]])->assertRedirect();
    expect((float) $this->oring->fresh()?->current_stock)->toBe(9.0);

    // A page from before parts sends only the chemicals: the part stays, and so does its stock.
    $this->actingAs($this->admin)->patch("/reports/{$visit->id}", ['treatments' => [['chemical_id' => $this->chlorine->id, 'amount' => 1]]])->assertRedirect();
    expect((float) $this->oring->fresh()?->current_stock)->toBe(9.0)
        ->and(partsReportLists($this->admin, $visit)[1])->toHaveCount(1);

    $this->actingAs($this->admin)->patch("/reports/{$visit->id}", ['treatments' => [], 'parts' => []])->assertRedirect();
    expect((float) $this->oring->fresh()?->current_stock)->toBe(10.0)
        ->and(partsReportLists($this->admin, $visit)[1])->toBe([]);
});
