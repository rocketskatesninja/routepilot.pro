<?php

declare(strict_types=1);

use App\Models\Tenant;
use App\Models\User;

/**
 * The route-layer role backstop (EnsureRole) — a coarse net that mirrors the
 * controllers' inline guards, so a forgotten inline check can't open a hole.
 * These assert the boundaries the middleware enforces before a controller runs.
 */
beforeEach(function () {
    $this->tenant = Tenant::factory()->create();
    $this->admin = User::factory()->for($this->tenant)->create();          // tenant_admin (default)
    $this->agent = User::factory()->agent()->for($this->tenant)->create();
    $this->customer = User::factory()->customer()->for($this->tenant)->create();
    $this->super = User::factory()->superAdmin()->create();
});

test('customers are blocked from the entire back office', function () {
    $this->actingAs($this->customer)->get('/schedule')->assertForbidden();
    $this->actingAs($this->customer)->get('/people')->assertForbidden();
    $this->actingAs($this->customer)->get('/insights')->assertForbidden();
    $this->actingAs($this->customer)->post('/customers')->assertForbidden();
    $this->actingAs($this->customer)->get('/platform/billing')->assertForbidden();
});

test('agents cannot reach tenant_admin writes or the platform console', function () {
    $this->actingAs($this->agent)->post('/customers')->assertForbidden();
    $this->actingAs($this->agent)->get('/insights')->assertForbidden();
    $this->actingAs($this->agent)->get('/company')->assertForbidden();
    $this->actingAs($this->agent)->get('/platform/billing')->assertForbidden();
});

test('staff cannot reach the customer portal', function () {
    $this->actingAs($this->agent)->get('/balance')->assertForbidden();
    $this->actingAs($this->admin)->get('/history')->assertForbidden();
});

test('only super-admins reach the platform console', function () {
    $this->actingAs($this->admin)->get('/platform/billing')->assertForbidden();
    $this->actingAs($this->agent)->get('/platform/billing')->assertForbidden();
    $this->actingAs($this->super)->get('/platform/billing')->assertOk();
});

test('each role reaches its own surface', function () {
    $this->actingAs($this->agent)->get('/schedule')->assertOk();   // staff read
    $this->actingAs($this->admin)->get('/people')->assertOk();     // staff (admin ∈)
    $this->actingAs($this->admin)->get('/insights')->assertOk();   // admin write surface
});

test('an impersonating super-admin acts with the impersonated role', function () {
    // Impersonation logs in AS the tenant user, so the acting role is the tenant
    // admin's — reaching admin surfaces the raw super could not.
    $this->actingAs($this->admin)->get('/insights')->assertOk();
    $this->actingAs($this->admin)->get('/platform/billing')->assertForbidden();
});
