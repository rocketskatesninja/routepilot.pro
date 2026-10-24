<?php

declare(strict_types=1);

use App\Models\ChemicalInventory;
use App\Models\Customer;
use App\Models\Pool;
use App\Models\ServiceVisit;
use App\Models\Tenant;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->tenant = Tenant::factory()->create();
    $this->admin = User::factory()->for($this->tenant)->create();
});

test('lists this tenant\'s active chemicals with a low-stock flag', function () {
    ChemicalInventory::factory()->for($this->tenant)->create(['chemical_name' => 'Soda Ash', 'current_stock' => 50, 'reorder_threshold' => 10]);
    ChemicalInventory::factory()->low()->for($this->tenant)->create(['chemical_name' => 'Liquid Chlorine']);

    $other = Tenant::factory()->create();
    ChemicalInventory::factory()->for($other)->create();

    $this->actingAs($this->admin)
        ->get('/inventory')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('inventory/Index')
            ->has('items.data', 2)
            ->where('items.data', fn ($items) => collect($items)->firstWhere('name', 'Liquid Chlorine')['low'] === true)
        );
});

test('the drawer computes stock value', function () {
    $item = ChemicalInventory::factory()->for($this->tenant)->create([
        'chemical_name' => 'Cal Hypo', 'current_stock' => 10, 'cost_per_unit' => 2.5,
    ]);

    $this->actingAs($this->admin)
        ->get("/inventory?selected={$item->id}")
        ->assertInertia(fn (Assert $page) => $page
            ->where('selected.id', $item->id)
            ->where('selected.value', 25)
        );
});

test('the pane lists recent changes newest first, signed by direction', function () {
    $item = ChemicalInventory::factory()->for($this->tenant)->create(['current_stock' => 10]);
    app()->instance('tenant_id', $this->tenant->id);
    $item->move('restock', 5);
    $item->move('usage', -2);

    $this->actingAs($this->admin)
        ->get("/inventory?selected={$item->id}")
        ->assertInertia(fn (Assert $page) => $page
            ->where('selected.transactions.0.type', 'usage')
            ->where('selected.transactions.0.change', -2)
            ->where('selected.transactions.1.change', 5)
            ->where('selected.is_active', true)
            ->missing('selected.fields')
        );
});

test('a change from a visit names its pool, report and tech; one made in the office names who made it', function () {
    app()->instance('tenant_id', $this->tenant->id);
    $agent = User::factory()->agent()->for($this->tenant)->create(['first_name' => 'Marcus', 'last_name' => 'Bennett']);
    $pool = Pool::factory()->for($this->tenant)->for(Customer::factory()->for($this->tenant))->create(['name' => 'Anderson Pool']);
    $visit = ServiceVisit::factory()->for($this->tenant)->for($pool)->create(['agent_id' => $agent->id, 'status' => 'completed', 'completed_at' => now()]);
    $item = ChemicalInventory::factory()->for($this->tenant)->create(['current_stock' => 10]);
    $item->move('restock', 5, ['created_by' => $this->admin->id]);
    $item->move('usage', -1, ['service_visit_id' => $visit->id, 'agent_id' => $agent->id, 'pool_id' => $pool->id, 'created_by' => $agent->id]);

    $this->actingAs($this->admin)
        ->get("/inventory?selected={$item->id}")
        ->assertInertia(fn (Assert $page) => $page
            ->where('selected.transactions.0.pool', 'Anderson Pool')
            ->where('selected.transactions.0.pool_id', $pool->id)
            ->where('selected.transactions.0.report_id', $visit->id)
            ->where('selected.transactions.0.by', 'Marcus Bennett')
            ->where('selected.transactions.0.agent_id', $agent->id)
            ->where('selected.transactions.1.by', $this->admin->displayName())
            ->where('selected.transactions.1.agent_id', null)
            ->where('selected.transactions.1.pool', null)
            ->where('selected.transactions.1.report_id', null)
        );

    // An office admin who did the visit is named but not linked: People only opens techs.
    $item->move('usage', -1, ['service_visit_id' => $visit->id, 'agent_id' => $this->admin->id, 'pool_id' => $pool->id, 'created_by' => $this->admin->id]);
    $this->actingAs($this->admin)
        ->get("/inventory?selected={$item->id}")
        ->assertInertia(fn (Assert $page) => $page->where('selected.transactions.0.by', $this->admin->displayName())->where('selected.transactions.0.agent_id', null));

    $pool->delete(); // archived: still named, no longer linked
    $this->actingAs($this->admin)
        ->get("/inventory?selected={$item->id}")
        ->assertInertia(fn (Assert $page) => $page->where('selected.transactions.0.pool', 'Anderson Pool')->where('selected.transactions.0.pool_id', null));
});

test('customers are denied the Inventory screen', function () {
    $portalUser = User::factory()->customer()->for($this->tenant)->create();

    $this->actingAs($portalUser)->get('/inventory')->assertForbidden();
});
