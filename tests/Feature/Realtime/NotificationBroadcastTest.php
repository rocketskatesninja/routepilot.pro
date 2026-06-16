<?php

declare(strict_types=1);

use App\Models\Customer;
use App\Models\Pool;
use App\Models\ServiceVisit;
use App\Models\Tenant;
use App\Models\User;
use App\Notifications\VisitCompleted;
use Illuminate\Broadcasting\Broadcasters\Broadcaster;
use Illuminate\Contracts\Broadcasting\Factory as BroadcastFactory;
use Illuminate\Support\Facades\Notification;

beforeEach(function () {
    $this->tenant = Tenant::factory()->create();
    app()->instance('tenant_id', $this->tenant->id);
    $this->agent = User::factory()->agent()->for($this->tenant)->create();
});

test('the user notification channel authorizes only its owner', function () {
    // Invoke the registered channel callback directly — the security boundary.
    $broadcaster = app(BroadcastFactory::class)->connection();
    $channels = (new ReflectionProperty(Broadcaster::class, 'channels'))->getValue($broadcaster);
    $authorize = $channels['App.Models.User.{id}'];

    $user = User::factory()->for($this->tenant)->create();

    expect($authorize($user, (string) $user->id))->toBeTrue()
        ->and($authorize($user, (string) ($user->id + 1)))->toBeFalse();
});

test('an in-app notification is also sent on the broadcast channel', function () {
    Notification::fake();

    $customerUser = User::factory()->customer()->for($this->tenant)->create();
    $customer = Customer::factory()->for($this->tenant)->create(['user_id' => $customerUser->id]);
    $pool = Pool::factory()->for($this->tenant)->for($customer)->create();
    $visit = ServiceVisit::factory()->for($this->tenant)->for($pool)->create(['agent_id' => $this->agent->id]);

    $customerUser->notify(new VisitCompleted($visit));

    Notification::assertSentTo(
        $customerUser,
        VisitCompleted::class,
        fn ($notification, array $channels): bool => in_array('database', $channels, true) && in_array('broadcast', $channels, true),
    );
});

test('a notification respects the opt-out (neither stored nor broadcast)', function () {
    Notification::fake();

    $customerUser = User::factory()->customer()->for($this->tenant)->create();
    $customerUser->notificationPreferences()->create(['category' => 'service', 'in_app' => false, 'email' => false]);
    $customer = Customer::factory()->for($this->tenant)->create(['user_id' => $customerUser->id]);
    $pool = Pool::factory()->for($this->tenant)->for($customer)->create();
    $visit = ServiceVisit::factory()->for($this->tenant)->for($pool)->create(['agent_id' => $this->agent->id]);

    $customerUser->notify(new VisitCompleted($visit));

    Notification::assertNotSentTo($customerUser, VisitCompleted::class);
});
