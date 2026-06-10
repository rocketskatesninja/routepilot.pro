<?php

declare(strict_types=1);

use App\Models\ChemicalInventory;
use App\Models\Customer;
use App\Models\Pool;
use App\Models\Route;
use App\Models\RouteStop;
use App\Models\ServiceSubscription;
use App\Models\ServiceType;
use App\Models\Tenant;
use App\Models\User;
use App\Services\Chat\Tools\LookupCustomer;
use App\Services\Chat\Tools\LookupInventory;
use App\Services\Chat\Tools\ReassignAgent;
use App\Services\Chat\Tools\SkipStop;

/**
 * AI tools execute against the live (tenant-scoped) database.
 */
beforeEach(function () {
    $this->tenant = Tenant::factory()->create();
    app()->instance('tenant', $this->tenant);
    app()->instance('tenant_id', $this->tenant->id);
});

test('lookup_customer returns matching customers and their pools', function () {
    $customer = Customer::factory()->for($this->tenant)->create(['first_name' => 'John', 'last_name' => 'Smith']);
    Pool::factory()->for($this->tenant)->for($customer)->create(['name' => 'Main Pool']);

    $out = (new LookupCustomer)->execute(['name' => 'Smith'], $this->tenant->id);

    expect($out)->toContain('John Smith')->toContain('Main Pool');
});

test('reassign_agent moves the subscription to the new agent', function () {
    $customer = Customer::factory()->for($this->tenant)->create(['first_name' => 'Jane', 'last_name' => 'Doe']);
    $pool = Pool::factory()->for($this->tenant)->for($customer)->create(['name' => 'Backyard']);
    $type = ServiceType::factory()->for($this->tenant)->create();
    $oldAgent = User::factory()->agent()->for($this->tenant)->create(['first_name' => 'Old', 'last_name' => 'Agent']);
    $newAgent = User::factory()->agent()->for($this->tenant)->create(['first_name' => 'New', 'last_name' => 'Agent']);
    $sub = ServiceSubscription::factory()->for($this->tenant)->for($pool)->for($type)->create([
        'assigned_agent_id' => $oldAgent->id, 'status' => 'active',
    ]);

    $out = (new ReassignAgent)->execute(['customer_name' => 'Jane', 'agent_name' => 'New Agent'], $this->tenant->id);

    expect($out)->toContain('Done!');
    expect($sub->fresh()?->assigned_agent_id)->toBe($newAgent->id);
});

test('skip_stop marks today\'s pending stop as skipped', function () {
    $customer = Customer::factory()->for($this->tenant)->create(['first_name' => 'Sam', 'last_name' => 'Lee']);
    $pool = Pool::factory()->for($this->tenant)->for($customer)->create();
    $agent = User::factory()->agent()->for($this->tenant)->create();
    $route = Route::factory()->for($this->tenant)->create(['agent_id' => $agent->id, 'scheduled_date' => today()]);
    $stop = RouteStop::factory()->for($route)->for($pool)->create(['status' => 'pending', 'stop_order' => 1]);

    $out = (new SkipStop)->execute(['customer_name' => 'Lee', 'reason' => 'gate locked'], $this->tenant->id);

    expect($out)->toContain('Skipped');
    expect($stop->fresh()?->status)->toBe('skipped');
});

test('lookup_inventory flags low stock', function () {
    ChemicalInventory::factory()->low()->for($this->tenant)->create(['chemical_name' => 'Liquid Chlorine']);

    $out = (new LookupInventory)->execute([], $this->tenant->id);

    expect($out)->toContain('Liquid Chlorine')->toContain('LOW STOCK');
});
