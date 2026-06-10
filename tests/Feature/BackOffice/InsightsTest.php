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

test('agents cannot see insights', function () {
    $this->actingAs($this->agent)->get('/insights')->assertForbidden();
});
