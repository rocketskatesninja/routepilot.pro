<?php

declare(strict_types=1);

use App\Models\Customer;
use App\Models\Tenant;
use App\Models\User;

beforeEach(function () {
    $this->tenant = Tenant::factory()->create();
    app()->instance('tenant_id', $this->tenant->id);
    $this->admin = User::factory()->for($this->tenant)->create();
});

test('an admin can grant a customer portal access', function () {
    $customer = Customer::factory()->for($this->tenant)->create(['email' => 'cust@portal.test']);

    $this->actingAs($this->admin)
        ->post("/customers/{$customer->id}/portal", ['password' => 'password123'])
        ->assertRedirect();

    $customer->refresh();
    expect($customer->user_id)->not->toBeNull();

    $portalUser = User::query()->where('email', 'cust@portal.test')->first();
    expect($portalUser?->role)->toBe('customer');
});

test('portal access needs an email on file', function () {
    $customer = Customer::factory()->for($this->tenant)->create(['email' => null]);

    $this->actingAs($this->admin)
        ->post("/customers/{$customer->id}/portal", ['password' => 'password123'])
        ->assertRedirect();

    expect($customer->fresh()?->user_id)->toBeNull();
});

test('granting twice is a no-op', function () {
    $customer = Customer::factory()->for($this->tenant)->create(['email' => 'dup@portal.test']);
    $customer->forceFill(['user_id' => User::factory()->customer()->for($this->tenant)->create()->id])->save();

    $before = User::query()->count();

    $this->actingAs($this->admin)->post("/customers/{$customer->id}/portal", ['password' => 'password123'])->assertRedirect();

    expect(User::query()->count())->toBe($before);
});

test('agents cannot grant portal access', function () {
    $agent = User::factory()->agent()->for($this->tenant)->create();
    $customer = Customer::factory()->for($this->tenant)->create(['email' => 'x@portal.test']);

    $this->actingAs($agent)->post("/customers/{$customer->id}/portal", ['password' => 'password123'])->assertForbidden();
});
