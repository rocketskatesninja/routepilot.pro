<?php

declare(strict_types=1);

use App\Models\Customer;
use App\Models\Pool;
use App\Models\ServiceRequest;
use App\Models\Tenant;
use App\Models\User;

/*
 * Production boots a fresh app per request, so nothing is bound until
 * ResolveTenant runs. These tests start each request from that same blank
 * state: if route-model binding ran before the tenant was known, TenantScope
 * would filter nothing and another company's record would resolve.
 */
beforeEach(function () {
    $this->victimTenant = Tenant::factory()->create();
    app()->instance('tenant_id', $this->victimTenant->id);
    $this->customer = Customer::factory()->for($this->victimTenant)->create(['first_name' => 'Victim']);
    $this->pool = Pool::factory()->for($this->victimTenant)->for($this->customer)->create(['name' => 'Their pool']);
    $this->request = ServiceRequest::create(['customer_id' => $this->customer->id, 'type' => 'other', 'message' => 'Hi']);

    $this->outsider = User::factory()->for(Tenant::factory())->create(); // another company's admin

    app()->forgetInstance('tenant_id');
    app()->forgetInstance('tenant');
});

test('another company\'s customer can\'t be read or changed by id', function () {
    $this->actingAs($this->outsider)->get("/customers/{$this->customer->id}/export")->assertNotFound();
    $this->actingAs($this->outsider)->patch("/customers/{$this->customer->id}", ['first_name' => 'Hacked'])->assertNotFound();

    expect(Customer::query()->withoutGlobalScopes()->find($this->customer->id)?->first_name)->toBe('Victim');
});

test('another company\'s pool can\'t be changed or removed by id', function () {
    $this->actingAs($this->outsider)->patch("/pools/{$this->pool->id}", ['name' => 'Hacked'])->assertNotFound();
    $this->actingAs($this->outsider)->delete("/pools/{$this->pool->id}")->assertNotFound();

    expect(Pool::query()->withoutGlobalScopes()->find($this->pool->id)?->getAttribute('name'))->toBe('Their pool');
});

test('another company\'s request can\'t be answered by id', function () {
    $this->actingAs($this->outsider)->post("/requests/{$this->request->id}/decline", ['reply' => 'x'])->assertNotFound();

    expect(ServiceRequest::query()->withoutGlobalScopes()->find($this->request->id)?->status)->toBe('pending');
});
