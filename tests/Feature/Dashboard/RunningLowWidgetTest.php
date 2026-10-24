<?php

declare(strict_types=1);

use App\Models\ChemicalInventory;
use App\Models\Tenant;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

/*
 * The Running low dashboard widget: chemicals at or below their reorder point,
 * the furthest under first, archived ones left out.
 */

beforeEach(function () {
    $this->tenant = Tenant::factory()->create();
    app()->instance('tenant_id', $this->tenant->id);
    $this->admin = User::factory()->for($this->tenant)->create();
});

/** The widget's data as the admin's (default) dashboard sends it. */
function runningLowWidget(User $user): ?array
{
    $data = null;
    test()->actingAs($user)->get('/dashboard')->assertOk()
        ->assertInertia(function (Assert $page) use (&$data) {
            $data = $page->toArray()['props']['widgets']['running_low'] ?? null;
        });

    return $data;
}

test('it lists what is low, the furthest under its reorder point first', function () {
    $make = fn (string $name, float $stock, ?float $reorder, bool $active = true) => ChemicalInventory::factory()->for($this->tenant)
        ->create(['chemical_name' => $name, 'current_stock' => $stock, 'reorder_threshold' => $reorder, 'is_active' => $active]);
    $make('Soda Ash', 35, 10);          // fine
    $make('Cal Hypo', 8, 10);           // 80% of its reorder point
    $make('Liquid Chlorine', 0, 15);    // out
    $make('Salt', 5, null);             // no reorder point, never "low"
    $make('Old Acid', 0, 5, false);     // archived
    ChemicalInventory::factory()->for(Tenant::factory()->create())->create(['current_stock' => 0, 'reorder_threshold' => 5]);

    $widget = runningLowWidget($this->admin);

    expect(array_column($widget['items'], 'name'))->toBe(['Liquid Chlorine', 'Cal Hypo'])
        ->and($widget['items'][1])->toMatchArray(['stock' => 8.0, 'reorder_at' => 10.0])
        ->and($widget['tracked'])->toBe(4);
});

test('it is on a new admin dashboard but not offered to techs', function () {
    $tech = User::factory()->agent()->for($this->tenant)->create();

    expect(runningLowWidget($this->admin))->toBe(['items' => [], 'tracked' => 0])
        ->and(runningLowWidget($tech))->toBeNull();
});
