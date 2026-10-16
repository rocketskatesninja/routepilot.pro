<?php

declare(strict_types=1);

use App\Models\Customer;
use App\Models\Pool;
use App\Models\ServiceRequest;
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

/** Give the customer's pool an active plan (needed to pause or stop service). */
function portalActivePlan(Pool $pool, ServiceType $service): void
{
    $pool->subscriptions()->create(['service_type_id' => $service->id, 'frequency' => 'weekly', 'preferred_day' => 'tuesday', 'status' => 'active']);
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
            ->where('services.0.offer', '$50.00 per visit · weekly')
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

test('pausing service needs dates, in order, on a pool that has service', function () {
    $from = now()->addDays(5)->toDateString();
    $to = now()->addDays(12)->toDateString();

    // No active plan on the pool yet — nothing to pause.
    $this->actingAs($this->user)
        ->post('/requests', ['type' => 'hold', 'pool_id' => $this->pool->id, 'preferred_date' => $from, 'ends_on' => $to])
        ->assertInvalid('pool_id');

    portalActivePlan($this->pool, $this->service);

    $this->actingAs($this->user)->post('/requests', ['type' => 'hold', 'pool_id' => $this->pool->id])->assertInvalid(['preferred_date', 'ends_on']);
    $this->actingAs($this->user)
        ->post('/requests', ['type' => 'hold', 'pool_id' => $this->pool->id, 'preferred_date' => $to, 'ends_on' => $from])
        ->assertInvalid('ends_on');
    $this->actingAs($this->user)
        ->post('/requests', ['type' => 'hold', 'pool_id' => $this->pool->id, 'preferred_date' => $from, 'ends_on' => $to])
        ->assertSessionHasNoErrors();

    expect(ServiceRequest::query()->where('type', 'hold')->sole()->getAttribute('ends_on')?->toDateString())->toBe($to);
});

test('stopping service can be as soon as possible', function () {
    portalActivePlan($this->pool, $this->service);

    $this->actingAs($this->user)
        ->post('/requests', ['type' => 'cancel', 'pool_id' => $this->pool->id, 'message' => 'Moving house'])
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
