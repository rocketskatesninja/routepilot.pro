<?php

declare(strict_types=1);

use App\Models\Customer;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

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

test('an admin can restore a customer\'s self-deleted portal login', function () {
    $portalUser = User::factory()->customer()->for($this->tenant)->create();
    $customer = Customer::factory()->for($this->tenant)->create(['email' => 'back@portal.test']);
    $customer->forceFill(['user_id' => $portalUser->id])->save();

    // Customer deletes their own account (soft delete leaves user_id set).
    $portalUser->delete();

    $this->actingAs($this->admin)
        ->post("/customers/{$customer->id}/portal/restore")
        ->assertRedirect();

    expect($portalUser->fresh()?->trashed())->toBeFalse();
    expect($portalUser->fresh()?->is_active)->toBeTrue();
    // Same login row — history/customer link preserved, not a new user.
    expect($customer->fresh()?->user_id)->toBe($portalUser->id);
});

test('restore is a graceful no-op when there is no deleted login', function () {
    $customer = Customer::factory()->for($this->tenant)->create(['email' => 'active@portal.test']);
    $customer->forceFill(['user_id' => User::factory()->customer()->for($this->tenant)->create()->id])->save();

    $this->actingAs($this->admin)
        ->post("/customers/{$customer->id}/portal/restore")
        ->assertRedirect()
        ->assertSessionHas('error');
});

test('granting portal on a self-deleted login restores it instead of erroring', function () {
    $portalUser = User::factory()->customer()->for($this->tenant)->create();
    $customer = Customer::factory()->for($this->tenant)->create(['email' => 'stale@portal.test']);
    $customer->forceFill(['user_id' => $portalUser->id])->save();
    $portalUser->delete();

    $before = User::withTrashed()->count();

    $this->actingAs($this->admin)
        ->post("/customers/{$customer->id}/portal", ['password' => 'newpassword123'])
        ->assertRedirect()
        ->assertSessionHas('success');

    // No duplicate login created — the existing one is restored + re-passworded.
    expect(User::withTrashed()->count())->toBe($before);
    expect($portalUser->fresh()?->trashed())->toBeFalse();
    expect(Hash::check('newpassword123', (string) $portalUser->fresh()?->password))->toBeTrue();
});

test('agents cannot restore portal access', function () {
    $agent = User::factory()->agent()->for($this->tenant)->create();
    $portalUser = User::factory()->customer()->for($this->tenant)->create();
    $customer = Customer::factory()->for($this->tenant)->create(['email' => 'y@portal.test']);
    $customer->forceFill(['user_id' => $portalUser->id])->save();
    $portalUser->delete();

    $this->actingAs($agent)->post("/customers/{$customer->id}/portal/restore")->assertForbidden();
});
