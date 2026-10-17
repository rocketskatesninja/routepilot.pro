<?php

declare(strict_types=1);

use App\Actions\CreateSubscription;
use App\Models\Customer;
use App\Models\Pool;
use App\Models\ServiceRequest;
use App\Models\ServiceSubscription;
use App\Models\ServiceType;
use App\Models\Tenant;
use App\Models\User;
use App\Notifications\ServiceRequestSubmitted;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Notification;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->tenant = Tenant::factory()->create();
    app()->instance('tenant_id', $this->tenant->id);
    $this->admin = User::factory()->for($this->tenant)->create();

    $this->user = User::factory()->customer()->for($this->tenant)->create();
    $this->customer = Customer::factory()->for($this->tenant)->create();
    $this->customer->forceFill(['user_id' => $this->user->id])->save();
    $this->pool = Pool::factory()->for($this->tenant)->for($this->customer)->create();
    $this->service = ServiceType::factory()->for($this->tenant)->create(['name' => 'Weekly Pool Service', 'requestable' => true]);
});

/**
 * Give a pool a plan for a service (what a customer can pause or stop).
 *
 * @param  array<string, mixed>  $attrs
 */
function portalPlan(Pool $pool, ServiceType $service, array $attrs = []): ServiceSubscription
{
    return $pool->subscriptions()->create(array_merge(['service_type_id' => $service->id, 'frequency' => 'weekly', 'preferred_day' => 'tuesday', 'status' => 'active'], $attrs));
}

test('the portal offers only services customers can request', function () {
    ServiceType::factory()->for($this->tenant)->create(['name' => 'Internal Check', 'requestable' => false]);
    ServiceType::factory()->for($this->tenant)->create(['name' => 'Retired Service', 'requestable' => true, 'is_active' => false]);

    $this->actingAs($this->user)
        ->get('/requests')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('portal/Requests')
            ->has('services', 1)
            ->where('services.0.name', 'Weekly Pool Service')
            ->where('services.0.price', 50)
            ->where('services.0.frequency', 'weekly')
            ->has('pools', 1));
});

test('a customer requests a service from the catalog and the company is notified', function () {
    Notification::fake();

    $this->actingAs($this->user)->post('/requests', [
        'type' => 'service',
        'service_type_id' => $this->service->id,
        'pool_id' => $this->pool->id,
        'preferred_date' => now()->addDays(3)->toDateString(),
        'message' => 'Gate code is 1234',
    ])->assertRedirect()->assertSessionHasNoErrors();

    $request = ServiceRequest::query()->where('customer_id', $this->customer->id)->sole();
    expect($request->type)->toBe('service')
        ->and($request->getAttribute('service_type_id'))->toBe($this->service->id)
        ->and($request->status)->toBe('pending');
    Notification::assertSentTo($this->admin, ServiceRequestSubmitted::class);
});

test('a service request needs a service and a pool', function () {
    $this->actingAs($this->user)->post('/requests', ['type' => 'service'])->assertInvalid(['service_type_id', 'pool_id']);
});

test('a customer cannot request an internal, inactive or other company\'s service', function () {
    $internal = ServiceType::factory()->for($this->tenant)->create(['requestable' => false]);
    $inactive = ServiceType::factory()->for($this->tenant)->create(['requestable' => true, 'is_active' => false]);
    $foreign = ServiceType::factory()->for(Tenant::factory())->create(['requestable' => true]);

    foreach ([$internal, $inactive, $foreign] as $service) {
        $this->actingAs($this->user)
            ->post('/requests', ['type' => 'service', 'service_type_id' => $service->id, 'pool_id' => $this->pool->id])
            ->assertInvalid('service_type_id');
    }
});

test('a request cannot target another customer\'s pool', function () {
    $otherPool = Pool::factory()->for($this->tenant)->for(Customer::factory()->for($this->tenant))->create();

    $this->actingAs($this->user)
        ->post('/requests', ['type' => 'service', 'service_type_id' => $this->service->id, 'pool_id' => $otherPool->id])
        ->assertInvalid('pool_id');
});

test('the portal lists the customer\'s current services, and what they already have', function () {
    $plan = portalPlan($this->pool, $this->service, ['preferred_day' => 'wednesday']);
    portalPlan($this->pool, $this->service, ['status' => 'cancelled']); // ended — not listed
    ServiceRequest::create(['customer_id' => $this->customer->id, 'pool_id' => $this->pool->id, 'type' => 'cancel'])
        ->forceFill(['service_subscription_id' => $plan->id])->save();

    $this->actingAs($this->user)
        ->get('/requests')
        ->assertInertia(fn (Assert $page) => $page->component('portal/Requests')
            ->has('plans', 1)
            ->where('plans.0.id', $plan->id)
            ->where('plans.0.service', 'Weekly Pool Service')
            ->where('plans.0.service_type_id', $this->service->id)
            ->where('plans.0.preferred_day', 'wednesday')
            ->where('plans.0.pending', 'cancel'));
});

test('each current service shows its next visit and what the catalog includes', function () {
    Carbon::setTestNow('2026-10-15 14:00:00'); // Thursday in New York
    $this->service->forceFill(['tasks' => ['Skim the surface', 'Brush the walls'], 'field_modules' => ['tasks' => true, 'chemistry' => true, 'treatments' => false, 'photos' => true], 'estimated_duration_minutes' => 40])->save();
    app(CreateSubscription::class)->handle(['pool_id' => $this->pool->id, 'service_type_id' => $this->service->id, 'frequency' => 'weekly', 'preferred_day' => 'tuesday']);

    $this->actingAs($this->user)
        ->get('/requests')
        ->assertInertia(fn (Assert $page) => $page
            ->where('plans.0.next_visit', '2026-10-20')
            ->where('plans.0.frequency', 'weekly')
            ->where('plans.0.preferred_day', 'tuesday')
            ->where('services.0.minutes', 40)
            ->where('services.0.tasks', ['Skim the surface', 'Brush the walls'])
            ->where('services.0.modules', ['tasks', 'chemistry', 'photos']));
});

test('pausing is for one of the customer\'s own services, with dates in order', function () {
    $plan = portalPlan($this->pool, $this->service);
    $from = now()->addDays(5)->toDateString();
    $to = now()->addDays(12)->toDateString();

    $this->actingAs($this->user)->post('/requests', ['type' => 'hold'])->assertInvalid(['service_subscription_id', 'preferred_date', 'ends_on']);
    $this->actingAs($this->user)
        ->post('/requests', ['type' => 'hold', 'service_subscription_id' => $plan->id, 'preferred_date' => $to, 'ends_on' => $from])
        ->assertInvalid('ends_on');
    $this->actingAs($this->user)
        ->post('/requests', ['type' => 'hold', 'service_subscription_id' => $plan->id, 'preferred_date' => $from, 'ends_on' => $to])
        ->assertSessionHasNoErrors();

    $request = ServiceRequest::query()->where('type', 'hold')->sole();
    expect($request->getAttribute('service_subscription_id'))->toBe($plan->id)
        ->and($request->getAttribute('pool_id'))->toBe($this->pool->id)
        ->and($request->ends_on?->toDateString())->toBe($to);
});

test('a customer can\'t pause or stop someone else\'s service, an ended one, or pause a one-time job', function () {
    $theirs = portalPlan(Pool::factory()->for($this->tenant)->for(Customer::factory()->for($this->tenant))->create(), $this->service);
    $ended = portalPlan($this->pool, $this->service, ['status' => 'cancelled']);
    $oneTime = portalPlan($this->pool, $this->service, ['frequency' => 'one_time', 'preferred_day' => null, 'starts_on' => now()->addDays(3)->toDateString()]);
    $dates = ['preferred_date' => now()->addDays(5)->toDateString(), 'ends_on' => now()->addDays(9)->toDateString()];

    foreach ([$theirs, $ended, $oneTime] as $plan) {
        $this->actingAs($this->user)->post('/requests', ['type' => 'hold', 'service_subscription_id' => $plan->id, ...$dates])->assertInvalid('service_subscription_id');
    }
    foreach ([$theirs, $ended] as $plan) {
        $this->actingAs($this->user)->post('/requests', ['type' => 'cancel', 'service_subscription_id' => $plan->id])->assertInvalid('service_subscription_id');
    }
    // A one-time job can be cancelled.
    $this->actingAs($this->user)->post('/requests', ['type' => 'cancel', 'service_subscription_id' => $oneTime->id])->assertSessionHasNoErrors();
});

test('stopping a service can be as soon as possible', function () {
    $plan = portalPlan($this->pool, $this->service);

    $this->actingAs($this->user)
        ->post('/requests', ['type' => 'cancel', 'service_subscription_id' => $plan->id, 'message' => 'Moving house'])
        ->assertSessionHasNoErrors();

    expect(ServiceRequest::query()->where('type', 'cancel')->sole()->preferred_date)->toBeNull();
});

test('"something else" needs a message', function () {
    $this->actingAs($this->user)->post('/requests', ['type' => 'other'])->assertInvalid('message');
    $this->actingAs($this->user)->post('/requests', ['type' => 'other', 'message' => 'New gate code: 4321'])->assertSessionHasNoErrors();
});

test('the earliest preferred date is the company\'s today, not the server\'s', function () {
    // 11:30 pm Thursday in New York is already Friday in UTC.
    Carbon::setTestNow(Carbon::parse('2026-10-15 23:30', 'America/New_York'));

    $this->actingAs($this->user)
        ->post('/requests', ['type' => 'service', 'service_type_id' => $this->service->id, 'pool_id' => $this->pool->id, 'preferred_date' => '2026-10-15'])
        ->assertSessionHasNoErrors();
    $this->actingAs($this->user)
        ->post('/requests', ['type' => 'service', 'service_type_id' => $this->service->id, 'pool_id' => $this->pool->id, 'preferred_date' => '2026-10-14'])
        ->assertInvalid('preferred_date');
});

test('the requests page shows each request\'s outcome and the company\'s reply', function () {
    $plan = $this->pool->subscriptions()->create(['service_type_id' => $this->service->id, 'frequency' => 'weekly', 'preferred_day' => 'thursday', 'status' => 'active']);
    $scheduled = ServiceRequest::create(['customer_id' => $this->customer->id, 'pool_id' => $this->pool->id, 'type' => 'service', 'service_type_id' => $this->service->id]);
    $scheduled->forceFill(['status' => 'approved', 'service_subscription_id' => $plan->id])->save();
    $declined = ServiceRequest::create(['customer_id' => $this->customer->id, 'pool_id' => $this->pool->id, 'type' => 'service', 'service_type_id' => $this->service->id]);
    $declined->forceFill(['status' => 'declined', 'reply' => 'We don\'t cover your area yet.'])->save();

    $this->actingAs($this->user)
        ->get('/requests')
        ->assertInertia(fn (Assert $page) => $page->component('portal/Requests')
            ->has('requests', 2)
            ->where('requests.0.status_label', 'Declined')
            ->where('requests.0.reply', 'We don\'t cover your area yet.')
            ->where('requests.1.title', 'Weekly Pool Service')
            ->where('requests.1.status_label', 'Scheduled')
            ->where('requests.1.plan', 'Weekly on Thursday'));
});

test('staff cannot submit a customer request', function () {
    $this->actingAs($this->admin)->post('/requests', ['type' => 'other', 'message' => 'x'])->assertForbidden();
});
