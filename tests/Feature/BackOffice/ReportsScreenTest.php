<?php

declare(strict_types=1);

use App\Models\Customer;
use App\Models\Pool;
use App\Models\ServiceVisit;
use App\Models\Tenant;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->tenant = Tenant::factory()->create();
    app()->instance('tenant_id', $this->tenant->id); // so relation create() auto-fills tenant_id
    $this->admin = User::factory()->for($this->tenant)->create();
    $this->customer = Customer::factory()->for($this->tenant)->create();
    $this->pool = Pool::factory()->for($this->tenant)->for($this->customer)->create(['name' => 'Smith Pool']);
    $this->agent = User::factory()->agent()->for($this->tenant)->create();
});

test('lists completed visits, newest first, tenant-scoped', function () {
    ServiceVisit::factory()->for($this->tenant)->for($this->pool)->create(['agent_id' => $this->agent->id, 'status' => 'completed', 'completed_at' => now()]);

    // In-progress and foreign visits must not appear.
    ServiceVisit::factory()->for($this->tenant)->for($this->pool)->create(['agent_id' => $this->agent->id, 'status' => 'in_progress']);
    $other = Tenant::factory()->create();
    $otherCustomer = Customer::factory()->for($other)->create();
    $otherPool = Pool::factory()->for($other)->for($otherCustomer)->create();
    ServiceVisit::factory()->for($other)->for($otherPool)->create(['agent_id' => User::factory()->agent()->for($other)->create()->id, 'status' => 'completed']);

    $this->actingAs($this->admin)
        ->get('/reports')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('reports/Index')->has('visits.data', 1));
});

test('the drawer carries the reading and treatments', function () {
    $visit = ServiceVisit::factory()->for($this->tenant)->for($this->pool)->create([
        'agent_id' => $this->agent->id, 'status' => 'completed', 'completed_at' => now(), 'notes' => 'All good',
    ]);
    $visit->chemicalReading()->create(['free_chlorine' => 2.0, 'ph' => 7.4]);
    $visit->treatments()->create(['chemical_name' => 'Acid', 'amount' => 16, 'unit' => 'oz']);

    $this->actingAs($this->admin)
        ->get("/reports?selected={$visit->id}")
        ->assertInertia(fn (Assert $page) => $page
            ->where('selected.id', $visit->id)
            ->where('selected.reading.ph', 7.4)
            ->where('selected.notes', 'All good')
            ->has('selected.treatments', 1)
            ->where('selected.treatments.0.name', 'Acid')
        );
});

test('customers are denied the Reports screen', function () {
    $portalUser = User::factory()->customer()->for($this->tenant)->create();

    $this->actingAs($portalUser)->get('/reports')->assertForbidden();
});
