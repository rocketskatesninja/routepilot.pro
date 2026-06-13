<?php

declare(strict_types=1);

use App\Models\Customer;
use App\Models\Pool;
use App\Models\ServiceRequest;
use App\Models\Tenant;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->tenant = Tenant::factory()->create();
    app()->instance('tenant_id', $this->tenant->id);
    $this->admin = User::factory()->for($this->tenant)->create();

    $this->user = User::factory()->customer()->for($this->tenant)->create();
    $this->customer = Customer::factory()->for($this->tenant)->create();
    $this->customer->forceFill(['user_id' => $this->user->id])->save();
    $this->pool = Pool::factory()->for($this->tenant)->for($this->customer)->create();
});

test('a customer can submit a request', function () {
    $this->actingAs($this->user)
        ->post('/requests', ['type' => 'service', 'pool_id' => $this->pool->id, 'message' => 'Algae bloom — please come sooner', 'preferred_date' => now()->addDay()->toDateString()])
        ->assertRedirect();

    $request = ServiceRequest::query()->where('customer_id', $this->customer->id)->first();
    expect($request?->type)->toBe('service');
    expect($request?->status)->toBe('pending');
});

test('the requests page lists the customer\'s own requests', function () {
    ServiceRequest::create(['customer_id' => $this->customer->id, 'type' => 'hold', 'message' => 'Away in July']);

    $this->actingAs($this->user)
        ->get('/requests')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('portal/Requests')->has('requests', 1)->has('pools', 1));
});

test('a request needs a message', function () {
    $this->actingAs($this->user)->post('/requests', ['type' => 'service'])->assertInvalid('message');
});

test('a request cannot target another customer\'s pool', function () {
    $otherPool = Pool::factory()->for($this->tenant)->for(Customer::factory()->for($this->tenant))->create();

    $this->actingAs($this->user)
        ->post('/requests', ['type' => 'service', 'pool_id' => $otherPool->id, 'message' => 'x'])
        ->assertInvalid('pool_id');
});

test('an admin can resolve a request', function () {
    $request = ServiceRequest::create(['customer_id' => $this->customer->id, 'type' => 'service', 'message' => 'Need a hand']);

    $this->actingAs($this->admin)->post("/requests/{$request->id}/resolve")->assertRedirect();

    expect($request->fresh()?->status)->toBe('resolved');
    expect($request->fresh()?->getAttribute('resolved_by'))->toBe($this->admin->id);
});

test('pending requests surface on the admin dashboard', function () {
    ServiceRequest::create(['customer_id' => $this->customer->id, 'type' => 'service', 'message' => 'Please call']);

    $this->actingAs($this->admin)
        ->get('/dashboard')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('dashboards/Admin')->has('widgets.requests', 1));
});

test('staff cannot submit a customer request', function () {
    $this->actingAs($this->admin)->post('/requests', ['type' => 'service', 'message' => 'x'])->assertForbidden();
});

test('a customer cannot resolve requests', function () {
    $request = ServiceRequest::create(['customer_id' => $this->customer->id, 'type' => 'service', 'message' => 'x']);

    $this->actingAs($this->user)->post("/requests/{$request->id}/resolve")->assertForbidden();
});
