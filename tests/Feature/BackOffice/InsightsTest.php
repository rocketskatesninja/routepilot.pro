<?php

declare(strict_types=1);

use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Pool;
use App\Models\ServiceVisit;
use App\Models\Tenant;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->tenant = Tenant::factory()->create();
    app()->instance('tenant_id', $this->tenant->id);
    $this->admin = User::factory()->for($this->tenant)->create();
    $this->agent = User::factory()->agent()->for($this->tenant)->create();
});

test('an admin sees tenant insights', function () {
    $customer = Customer::factory()->for($this->tenant)->create();
    $pool = Pool::factory()->for($this->tenant)->for($customer)->create();
    ServiceVisit::factory()->for($this->tenant)->for($pool)->create(['agent_id' => $this->agent->id, 'status' => 'completed', 'completed_at' => now()]);

    $this->actingAs($this->admin)
        ->get('/insights')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('reports/Insights')
            ->where('visits_month', 1)
            ->has('top_agents', 1)
            ->has('revenue_month'));
});

test('insights exposes the chart series', function () {
    $customer = Customer::factory()->for($this->tenant)->create();
    $pool = Pool::factory()->for($this->tenant)->for($customer)->create();
    ServiceVisit::factory()->for($this->tenant)->for($pool)->create(['agent_id' => $this->agent->id, 'status' => 'completed', 'completed_at' => now()]);

    $this->actingAs($this->admin)
        ->get('/insights')
        ->assertInertia(fn (Assert $page) => $page
            ->has('revenue_series', 12)
            ->has('visits_series', 12)
            ->has('ar_aging', 5)
            ->where('visits_series.11.value', 1) // this week's completed visit → last bucket
            ->where('ar_aging.0.label', 'Current'));
});

test('AR aging buckets an overdue invoice by days past due', function () {
    $customer = Customer::factory()->for($this->tenant)->create();
    Invoice::create([
        'customer_id' => $customer->id, 'number' => 'INV-1', 'status' => 'overdue',
        'subtotal' => 100, 'tax' => 0, 'total' => 100, 'amount_paid' => 0,
        'issued_at' => now()->subDays(60), 'due_at' => now()->subDays(45),
    ]);

    $this->actingAs($this->admin)
        ->get('/insights')
        ->assertInertia(fn (Assert $page) => $page
            ->where('ar_aging.2.label', '31–60') // index 2 = 31–60 days
            ->where('ar_aging.2.value', 100));
});

test('agents cannot see insights', function () {
    $this->actingAs($this->agent)->get('/insights')->assertForbidden();
});
