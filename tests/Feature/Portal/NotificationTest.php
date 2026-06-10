<?php

declare(strict_types=1);

use App\Models\Customer;
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
});

test('a customer request notifies the tenant admins', function () {
    $this->actingAs($this->user)
        ->post('/requests', ['type' => 'service', 'message' => 'Algae bloom', 'preferred_date' => now()->addDay()->toDateString()])
        ->assertRedirect();

    expect($this->admin->notifications()->count())->toBe(1);
});

test('the unread count is shared to the frontend', function () {
    $this->actingAs($this->user)->post('/requests', ['type' => 'service', 'message' => 'Help'])->assertRedirect();

    $this->actingAs($this->admin)
        ->get('/dashboard')
        ->assertInertia(fn (Assert $page) => $page->where('auth.unread', 1));
});

test('a user reads a notification', function () {
    $this->actingAs($this->user)->post('/requests', ['type' => 'service', 'message' => 'Help'])->assertRedirect();
    $notification = $this->admin->notifications()->first();

    $this->actingAs($this->admin)->post("/notifications/{$notification?->id}/read")->assertRedirect();

    expect($this->admin->unreadNotifications()->count())->toBe(0);
});
