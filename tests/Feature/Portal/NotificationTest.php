<?php

declare(strict_types=1);

use App\Models\Customer;
use App\Models\Tenant;
use App\Models\User;
use App\Support\NotificationTypes;
use Illuminate\Support\Str;
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
        ->post('/requests', ['type' => 'other', 'message' => 'Algae bloom', 'preferred_date' => now()->addDay()->toDateString()])
        ->assertRedirect();

    expect($this->admin->notifications()->count())->toBe(1);
});

test('the unread count is shared to the frontend', function () {
    $this->actingAs($this->user)->post('/requests', ['type' => 'other', 'message' => 'Help'])->assertRedirect();

    $this->actingAs($this->admin)
        ->get('/dashboard')
        ->assertInertia(fn (Assert $page) => $page->where('auth.unread', 1));
});

test('a user reads a notification', function () {
    $this->actingAs($this->user)->post('/requests', ['type' => 'other', 'message' => 'Help'])->assertRedirect();
    $notification = $this->admin->notifications()->first();

    $this->actingAs($this->admin)->post("/notifications/{$notification?->id}/read")->assertRedirect();

    expect($this->admin->unreadNotifications()->count())->toBe(0);
});

test('a user marks a notification back to unread', function () {
    $this->actingAs($this->user)->post('/requests', ['type' => 'other', 'message' => 'Help'])->assertRedirect();
    $notification = $this->admin->notifications()->first();
    $this->actingAs($this->admin)->post("/notifications/{$notification?->id}/read")->assertRedirect();
    expect($this->admin->unreadNotifications()->count())->toBe(0);

    $this->actingAs($this->admin)->post("/notifications/{$notification?->id}/unread")->assertRedirect();

    expect($this->admin->unreadNotifications()->count())->toBe(1);
});

test('a user dismisses a single notification, leaving the rest', function () {
    $drop = $this->admin->notifications()->create(['id' => (string) Str::uuid(), 'type' => 'App\Notifications\OpsAlert', 'data' => ['title' => 'A'], 'read_at' => null]);
    $keep = $this->admin->notifications()->create(['id' => (string) Str::uuid(), 'type' => 'App\Notifications\VisitCompleted', 'data' => ['title' => 'B'], 'read_at' => null]);

    $this->actingAs($this->admin)->delete("/notifications/{$drop->id}")->assertRedirect();

    expect($this->admin->notifications()->count())->toBe(1)
        ->and($this->admin->notifications()->first()->id)->toBe($keep->id);
});

test('the index exposes a severity and icon derived from the notification type', function () {
    $this->admin->notifications()->create(['id' => (string) Str::uuid(), 'type' => 'App\Notifications\OpsAlert', 'data' => ['title' => 'Down'], 'read_at' => null]);

    $this->actingAs($this->admin)
        ->get('/notifications')
        ->assertInertia(fn (Assert $page) => $page
            ->has('notifications', 1)
            ->where('notifications.0.severity', 'alert')
            ->where('notifications.0.icon', 'TriangleAlert'));
});

test('a user clears all their notifications', function () {
    $this->actingAs($this->user)->post('/requests', ['type' => 'other', 'message' => 'Help'])->assertRedirect();
    expect($this->admin->notifications()->count())->toBe(1);

    $this->actingAs($this->admin)->delete('/notifications')->assertRedirect();

    expect($this->admin->notifications()->count())->toBe(0);
});

test('the page lists only the notification types the user gets, all on by default', function () {
    $this->actingAs($this->admin)
        ->get('/notifications')
        ->assertInertia(fn (Assert $page) => $page
            ->has('types.service_request')
            ->has('types.missed_stops')
            ->missing('types.visit_completed')
            ->where('preferences.service_request', true));

    $this->actingAs($this->user)
        ->get('/notifications')
        ->assertInertia(fn (Assert $page) => $page
            ->has('types.visit_completed')
            ->missing('types.service_request'));
});

test('only Agent+ agents get the new-work-order type', function () {
    $agent = User::factory()->agent()->for($this->tenant)->create();
    $plus = User::factory()->agent()->for($this->tenant)->create(['agent_plus' => true]);

    expect(NotificationTypes::for($agent))->toHaveKey('work_order_assigned')->not->toHaveKey('work_order_raised')
        ->and(NotificationTypes::for($plus))->toHaveKey('work_order_raised');
});

test('a user turns a type off and stops getting it, and can turn it back on', function () {
    $this->actingAs($this->admin)->patch('/notifications/types', ['service_request' => false])->assertRedirect();

    $this->actingAs($this->user)->post('/requests', ['type' => 'other', 'message' => 'Help'])->assertRedirect();
    expect($this->admin->notifications()->count())->toBe(0);

    $this->actingAs($this->admin)->patch('/notifications/types', ['service_request' => true])->assertRedirect();

    $this->actingAs($this->user)->post('/requests', ['type' => 'other', 'message' => 'Help again'])->assertRedirect();
    expect($this->admin->notifications()->count())->toBe(1);
});

test('a user cannot save a type they do not get', function () {
    $this->actingAs($this->user)->patch('/notifications/types', ['service_request' => false, 'visit_completed' => false])->assertRedirect();

    expect($this->user->notificationPreferences()->pluck('category')->all())->toBe(['visit_completed']);
});
