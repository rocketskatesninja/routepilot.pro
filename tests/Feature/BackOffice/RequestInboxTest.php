<?php

declare(strict_types=1);

use App\Mail\ServiceRequestAnsweredMail;
use App\Models\Customer;
use App\Models\Pool;
use App\Models\RouteStop;
use App\Models\ServiceRequest;
use App\Models\ServiceSubscription;
use App\Models\ServiceType;
use App\Models\Tenant;
use App\Models\User;
use App\Notifications\ServiceRequestAnswered;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    Carbon::setTestNow('2026-10-15 14:00:00'); // Thursday, mid-morning in New York
    Mail::fake();
    Notification::fake();

    $this->tenant = Tenant::factory()->create();
    app()->instance('tenant_id', $this->tenant->id);
    $this->admin = User::factory()->for($this->tenant)->create();
    $this->agent = User::factory()->agent()->for($this->tenant)->create();

    $this->user = User::factory()->customer()->for($this->tenant)->create();
    $this->customer = Customer::factory()->for($this->tenant)->create(['email' => 'robert@example.test']);
    $this->customer->forceFill(['user_id' => $this->user->id])->save();
    $this->pool = Pool::factory()->for($this->tenant)->for($this->customer)->create(['name' => 'Backyard pool']);
    $this->service = ServiceType::factory()->for($this->tenant)->create(['name' => 'Weekly Pool Service', 'requestable' => true]);
});

/** @param  array<string, mixed>  $attrs */
function customerRequest(array $attrs = []): ServiceRequest
{
    $test = test();

    return ServiceRequest::create(array_merge([
        'customer_id' => $test->customer->id,
        'pool_id' => $test->pool->id,
        'type' => 'service',
        'service_type_id' => $test->service->id,
        'preferred_date' => '2026-10-20',
    ], $attrs));
}

/** Stops on the routes for a plan, as Y-m-d dates. */
function stopDates(ServiceSubscription $plan): array
{
    return RouteStop::query()->where('service_subscription_id', $plan->id)->with('route')->get()
        ->map(fn (RouteStop $s) => $s->route?->scheduled_date?->toDateString())->sort()->values()->all();
}

test('the inbox lists open requests and opens one with approve defaults', function () {
    $request = customerRequest(['message' => 'Gate code 1234']);
    customerRequest()->forceFill(['status' => 'declined'])->save(); // closed — not in the open list

    $this->actingAs($this->admin)
        ->get('/inbox')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('inbox/Index')
            ->where('tab', 'requests')
            ->has('rows.data', 1)
            ->where('counts.requests', 1));

    $this->actingAs($this->admin)
        ->get("/inbox?selected={$request->id}")
        ->assertInertia(fn (Assert $page) => $page
            ->where('selected.title', 'Weekly Pool Service')
            ->where('selected.message', 'Gate code 1234')
            ->where('selected.defaults.frequency', 'weekly')
            ->where('selected.defaults.preferred_day', 'tuesday') // Oct 20 is a Tuesday
            ->where('selected.defaults.starts_on', '2026-10-20'));
});

test('approving a service request creates the plan and puts it on the route', function () {
    $request = customerRequest();

    $this->actingAs($this->admin)->post("/requests/{$request->id}/approve", [
        'frequency' => 'weekly',
        'preferred_day' => 'tuesday',
        'starts_on' => '2026-10-20',
        'assigned_agent_id' => $this->agent->id,
    ])->assertRedirect()->assertSessionHasNoErrors();

    $plan = ServiceSubscription::query()->where('pool_id', $this->pool->id)->sole();
    expect($plan->getAttribute('service_type_id'))->toBe($this->service->id)
        ->and($plan->getAttribute('assigned_agent_id'))->toBe($this->agent->id)
        ->and(stopDates($plan)[0] ?? null)->toBe('2026-10-20');

    $request->refresh();
    expect($request->status)->toBe('approved')
        ->and($request->getAttribute('service_subscription_id'))->toBe($plan->id)
        ->and($request->getAttribute('resolved_by'))->toBe($this->admin->id);

    Notification::assertSentTo($this->user, ServiceRequestAnswered::class);
    Mail::assertQueued(ServiceRequestAnsweredMail::class, fn ($mail) => $mail->hasTo('robert@example.test'));
});

test('a one-time job books a single visit', function () {
    $job = ServiceType::factory()->for($this->tenant)->create(['name' => 'Green to clean', 'frequency' => 'one_time', 'price' => 350]);
    $request = customerRequest(['service_type_id' => $job->id, 'preferred_date' => '2026-10-17']);

    $this->actingAs($this->admin)
        ->post("/requests/{$request->id}/approve", ['frequency' => 'one_time', 'starts_on' => '2026-10-17'])
        ->assertSessionHasNoErrors();

    expect(stopDates(ServiceSubscription::query()->sole()))->toBe(['2026-10-17']);
});

test('the approve form checks the schedule like the plan form', function () {
    $request = customerRequest();

    $this->actingAs($this->admin)->post("/requests/{$request->id}/approve", ['frequency' => 'weekly'])->assertInvalid('preferred_day');
    expect(ServiceSubscription::query()->count())->toBe(0)
        ->and($request->fresh()?->status)->toBe('pending');
});

test('a request can only be answered once', function () {
    $request = customerRequest();
    $approve = ['frequency' => 'weekly', 'preferred_day' => 'tuesday', 'starts_on' => '2026-10-20'];

    $this->actingAs($this->admin)->post("/requests/{$request->id}/approve", $approve)->assertSessionHasNoErrors();
    $this->actingAs($this->admin)->post("/requests/{$request->id}/approve", $approve)->assertSessionHas('error');
    $this->actingAs($this->admin)->post("/requests/{$request->id}/decline", ['reply' => 'No'])->assertSessionHas('error');

    expect(ServiceSubscription::query()->count())->toBe(1)
        ->and($request->fresh()?->status)->toBe('approved');
});

test('declining needs a reason, and the customer is told', function () {
    $request = customerRequest();

    $this->actingAs($this->admin)->post("/requests/{$request->id}/decline", [])->assertInvalid('reply');

    $this->actingAs($this->admin)
        ->post("/requests/{$request->id}/decline", ['reply' => 'We don\'t service your area yet.'])
        ->assertSessionHasNoErrors();

    $request->refresh();
    expect($request->status)->toBe('declined')->and($request->getAttribute('reply'))->toBe('We don\'t service your area yet.');
    Notification::assertSentTo($this->user, ServiceRequestAnswered::class);
    Mail::assertQueued(ServiceRequestAnsweredMail::class);
});

test('approving a pause holds only the service it\'s for', function () {
    $chem = ServiceType::factory()->for($this->tenant)->create(['name' => 'Chemistry Check']);
    $cleaning = $this->pool->subscriptions()->create(['service_type_id' => $this->service->id, 'frequency' => 'weekly', 'preferred_day' => 'tuesday', 'status' => 'active']);
    $testing = $this->pool->subscriptions()->create(['service_type_id' => $chem->id, 'frequency' => 'weekly', 'preferred_day' => 'friday', 'status' => 'active']);
    $request = customerRequest(['type' => 'hold', 'service_type_id' => null, 'preferred_date' => '2026-10-19', 'ends_on' => '2026-10-31']);
    $request->forceFill(['service_subscription_id' => $testing->id])->save();

    $this->actingAs($this->admin)
        ->get("/inbox?selected={$request->id}")
        ->assertInertia(fn (Assert $page) => $page->where('selected.title', 'Pause Chemistry Check'));

    $this->actingAs($this->admin)->post("/requests/{$request->id}/approve")->assertSessionHasNoErrors();

    $testing->refresh();
    expect($testing->hold_starts_at?->toDateString())->toBe('2026-10-19')
        ->and($testing->hold_ends_at?->toDateString())->toBe('2026-10-31')
        ->and(array_filter(stopDates($testing), fn ($d) => $d >= '2026-10-19' && $d <= '2026-10-31'))->toBe([])
        ->and($cleaning->fresh()?->hold_starts_at)->toBeNull()
        ->and(array_filter(stopDates($cleaning), fn ($d) => $d >= '2026-10-19' && $d <= '2026-10-31'))->not->toBe([])
        ->and($request->fresh()?->status)->toBe('approved');
});

test('approving a stop ends only that service — on the date asked, or right away', function () {
    $chem = ServiceType::factory()->for($this->tenant)->create(['name' => 'Chemistry Check']);
    $cleaning = $this->pool->subscriptions()->create(['service_type_id' => $this->service->id, 'frequency' => 'weekly', 'preferred_day' => 'monday', 'status' => 'active']);
    $plan = $this->pool->subscriptions()->create(['service_type_id' => $chem->id, 'frequency' => 'weekly', 'preferred_day' => 'tuesday', 'status' => 'active', 'starts_on' => '2026-10-01']);
    $later = customerRequest(['type' => 'cancel', 'service_type_id' => null, 'preferred_date' => '2026-11-03']);
    $later->forceFill(['service_subscription_id' => $plan->id])->save();

    $this->actingAs($this->admin)->post("/requests/{$later->id}/approve")->assertSessionHasNoErrors();
    expect($plan->fresh()?->ends_on?->toDateString())->toBe('2026-11-03')
        ->and(max(stopDates($plan)))->toBe('2026-11-03')
        ->and($later->fresh()?->statusLabel())->toBe('Stop confirmed');
    Mail::assertQueued(ServiceRequestAnsweredMail::class, fn ($mail) => $mail->headline === 'Your service is stopping'
        && $mail->summary === 'Your Chemistry Check for Backyard pool will stop after Tuesday, November 3.');

    $asap = customerRequest(['type' => 'cancel', 'service_type_id' => null, 'preferred_date' => null]);
    $asap->forceFill(['service_subscription_id' => $plan->id])->save();
    $this->actingAs($this->admin)->post("/requests/{$asap->id}/approve")->assertSessionHasNoErrors();
    expect($plan->fresh()?->status)->toBe('cancelled')->and(stopDates($plan))->toBe([])
        ->and($cleaning->fresh()?->status)->toBe('active')
        ->and($cleaning->fresh()?->ends_on)->toBeNull();
});

test('"something else" is marked done with an optional reply', function () {
    $request = customerRequest(['type' => 'other', 'service_type_id' => null, 'message' => 'New gate code 4321']);

    $this->actingAs($this->admin)->post("/requests/{$request->id}/approve", ['reply' => 'Thanks, noted!'])->assertSessionHasNoErrors();

    expect($request->fresh()?->status)->toBe('approved')->and($request->fresh()?->getAttribute('reply'))->toBe('Thanks, noted!');
});

test('a customer who opted out of email still gets the portal notice but no email', function () {
    $this->customer->forceFill(['email_opt_out' => true])->save();
    $request = customerRequest(['type' => 'other', 'service_type_id' => null, 'message' => 'Hi']);

    $this->actingAs($this->admin)->post("/requests/{$request->id}/approve")->assertSessionHasNoErrors();

    Notification::assertSentTo($this->user, ServiceRequestAnswered::class);
    Mail::assertNothingQueued();
});

test('only company admins can use the inbox or answer requests', function () {
    $request = customerRequest();

    $this->actingAs($this->agent)->get('/inbox')->assertForbidden();
    $this->actingAs($this->agent)->post("/requests/{$request->id}/decline", ['reply' => 'x'])->assertForbidden();
    $this->actingAs($this->user)->post("/requests/{$request->id}/decline", ['reply' => 'x'])->assertForbidden();

    expect($request->fresh()?->status)->toBe('pending');
});

test('another company\'s request can\'t be answered', function () {
    $request = customerRequest();
    $outsider = User::factory()->for(Tenant::factory())->create();

    $this->actingAs($outsider)->post("/requests/{$request->id}/decline", ['reply' => 'x'])->assertNotFound();
});

test('the dashboard widget shows pending requests', function () {
    customerRequest();

    $this->actingAs($this->admin)
        ->get('/dashboard')
        ->assertInertia(fn (Assert $page) => $page->component('dashboards/Grid')
            ->has('widgets.requests', 1)
            ->where('widgets.requests.0.title', 'Weekly Pool Service'));
});

test('a customer without a portal login is emailed without the portal link', function () {
    $this->customer->forceFill(['user_id' => null])->save();
    $request = customerRequest(['type' => 'other', 'service_type_id' => null, 'message' => 'Hi']);

    $this->actingAs($this->admin)->post("/requests/{$request->id}/approve")->assertSessionHasNoErrors();

    Mail::assertQueued(ServiceRequestAnsweredMail::class, fn ($mail) => $mail->portalUrl === null && ! str_contains($mail->render(), 'customer portal'));
});

test('the inbox shows the company\'s date for a request sent late in the evening', function () {
    Carbon::setTestNow(Carbon::parse('2026-10-15 23:30', 'America/New_York')); // already Oct 16 in UTC
    $request = customerRequest();

    $this->actingAs($this->admin)
        ->get("/inbox?selected={$request->id}")
        ->assertInertia(fn (Assert $page) => $page->where('selected.on', '2026-10-15'));
});
