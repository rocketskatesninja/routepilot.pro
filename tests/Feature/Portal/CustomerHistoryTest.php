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

    $this->user = User::factory()->customer()->for($this->tenant)->create();
    $this->agent = User::factory()->agent()->for($this->tenant)->create();
    $this->customer = Customer::factory()->for($this->tenant)->create();
    $this->customer->forceFill(['user_id' => $this->user->id])->save();
    $this->pool = Pool::factory()->for($this->tenant)->for($this->customer)->create(['name' => 'My Pool']);

    $this->visit = ServiceVisit::factory()->for($this->tenant)->for($this->pool)->create(['status' => 'completed', 'completed_at' => now(), 'agent_id' => $this->agent->id]);
    $this->visit->chemicalReading()->create(['free_chlorine' => 2.0, 'ph' => 7.4, 'lsi_score' => -0.1]);
});

test('a customer sees their own service history', function () {
    $this->actingAs($this->user)
        ->get('/history')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('portal/History')->has('visits.data', 1)->where('visits.data.0.pool', 'My Pool'));
});

test('the visit detail includes the reading', function () {
    $this->actingAs($this->user)
        ->get("/history?selected={$this->visit->id}")
        ->assertInertia(fn (Assert $page) => $page->where('selected.id', $this->visit->id)->has('selected.reading'));
});

test('a customer cannot open another customer\'s visit', function () {
    $otherCustomer = Customer::factory()->for($this->tenant)->create();
    $otherPool = Pool::factory()->for($this->tenant)->for($otherCustomer)->create();
    $otherVisit = ServiceVisit::factory()->for($this->tenant)->for($otherPool)->create(['status' => 'completed', 'completed_at' => now(), 'agent_id' => $this->agent->id]);

    $this->actingAs($this->user)
        ->get("/history?selected={$otherVisit->id}")
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->where('selected', null));
});

test('staff cannot use the customer history endpoint', function () {
    $agent = User::factory()->agent()->for($this->tenant)->create();

    $this->actingAs($agent)->get('/history')->assertForbidden();
});

test('a portal user with no customer record gets a 404', function () {
    $orphan = User::factory()->customer()->for($this->tenant)->create();

    $this->actingAs($orphan)->get('/history')->assertNotFound();
});
