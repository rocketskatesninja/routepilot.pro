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
            ->has('notifications.list.data', 1)
            ->where('notifications.list.data.0.severity', 'alert')
            ->where('notifications.list.data.0.icon', 'TriangleAlert'));
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

test('opening a notification marks it read and goes where it points', function () {
    $note = fn (array $data) => $this->admin->notifications()->create(['id' => (string) Str::uuid(), 'type' => 'App\Notifications\OpsAlert', 'data' => $data, 'read_at' => null]);

    $linked = $note(['title' => 'Missed stops', 'url' => '/schedule?date=2026-10-24']);
    $this->actingAs($this->admin)->get("/notifications/{$linked->id}/open")->assertRedirect('/schedule?date=2026-10-24');
    expect($linked->fresh()?->read_at)->not->toBeNull();

    $nowhere = $note(['title' => 'No link']);
    $this->actingAs($this->admin)->get("/notifications/{$nowhere->id}/open")->assertRedirect('/notifications');

    $offsite = $note(['title' => 'Odd', 'url' => '//evil.example/x']);
    $this->actingAs($this->admin)->get("/notifications/{$offsite->id}/open")->assertRedirect('/notifications');
});

test('nobody can open someone else\'s notification', function () {
    $theirs = $this->admin->notifications()->create(['id' => (string) Str::uuid(), 'type' => 'App\Notifications\OpsAlert', 'data' => ['title' => 'A'], 'read_at' => null]);

    $this->actingAs($this->user)->get("/notifications/{$theirs->id}/open")->assertNotFound();
    expect($theirs->fresh()?->read_at)->toBeNull();
});

/** Give the admin $n notifications of a type, newest last; $read marks them read. */
function giveNotes(int $n, string $type = 'App\Notifications\OpsAlert', bool $read = false): void
{
    for ($i = 0; $i < $n; $i++) {
        test()->admin->notifications()->create([
            'id' => (string) Str::uuid(), 'type' => $type, 'data' => ['title' => "Note {$i}"], 'read_at' => $read ? now() : null,
            'created_at' => now()->subMinutes(100 - $i),
        ]);
    }
}

test('every notification is reachable a page at a time, with true counts', function () {
    giveNotes(30);
    giveNotes(5, read: true);

    $this->actingAs($this->admin)->get('/notifications')->assertInertia(fn (Assert $page) => $page
        ->has('notifications.list.data', 25)
        ->where('notifications.list.total', 35)
        ->where('notifications.all', 35)
        ->where('notifications.unread', 30)
        ->has('notifications.list.data.0.at'));

    $this->actingAs($this->admin)->get('/notifications?page=2')->assertInertia(fn (Assert $page) => $page->has('notifications.list.data', 10));
    $this->actingAs($this->admin)->get('/notifications?filter=unread')->assertInertia(fn (Assert $page) => $page
        ->where('filter', 'unread')->where('notifications.list.total', 30));
});

test('notifications filter by kind, offering only the kinds the person has', function () {
    giveNotes(2);
    giveNotes(3, 'App\Notifications\LeadSubmitted');

    $this->actingAs($this->admin)->get('/notifications?kind=leads')->assertInertia(fn (Assert $page) => $page
        ->where('kind', 'leads')
        ->where('notifications.list.total', 3)
        ->where('kinds', ['leads' => 'Website leads', 'alerts' => 'Alerts']));
});

test('several notifications are marked read, unread or dismissed at once — only the person’s own', function () {
    giveNotes(3);
    $ids = $this->admin->notifications()->pluck('id')->all();
    $other = User::factory()->for($this->tenant)->create();
    $theirs = $other->notifications()->create(['id' => (string) Str::uuid(), 'type' => 'App\Notifications\OpsAlert', 'data' => ['title' => 'Theirs'], 'read_at' => null]);

    $this->actingAs($this->admin)->post('/notifications/bulk', ['ids' => [...$ids, $theirs->id], 'action' => 'read'])->assertRedirect();
    expect($this->admin->unreadNotifications()->count())->toBe(0)
        ->and($theirs->fresh()?->read_at)->toBeNull();

    $this->actingAs($this->admin)->post('/notifications/bulk', ['ids' => [$ids[0]], 'action' => 'unread']);
    expect($this->admin->unreadNotifications()->count())->toBe(1);

    $this->actingAs($this->admin)->post('/notifications/bulk', ['ids' => [...$ids, $theirs->id], 'action' => 'dismiss']);
    expect($this->admin->notifications()->count())->toBe(0)
        ->and($other->notifications()->count())->toBe(1);

    $this->actingAs($this->admin)->post('/notifications/bulk', ['ids' => [$ids[0]], 'action' => 'explode'])->assertSessionHasErrors('action');
});
