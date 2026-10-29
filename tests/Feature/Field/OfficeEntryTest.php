<?php

declare(strict_types=1);

use App\Mail\VisitRecapMail;
use App\Models\AuditLog;
use App\Models\Charge;
use App\Models\Customer;
use App\Models\Pool;
use App\Models\Route;
use App\Models\RouteStop;
use App\Models\ServiceSubscription;
use App\Models\ServiceType;
use App\Models\ServiceVisit;
use App\Models\Tenant;
use App\Models\User;
use App\Notifications\VisitCompleted;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Inertia\Testing\AssertableInertia as Assert;

/*
 * The desktop field app lets the office open any tech's route on any day up to
 * today and fill a report in for them. The visit is the tech's, on that day; it
 * remembers who typed it in, carries no GPS, and the office may hold back the email.
 */

beforeEach(function () {
    Carbon::setTestNow('2026-10-29 15:00:00'); // Thursday 11:00 in New York
    $this->tenant = Tenant::factory()->create(['timezone' => 'America/New_York']);
    app()->instance('tenant_id', $this->tenant->id);
    $this->admin = User::factory()->for($this->tenant)->create(['first_name' => 'Sarah', 'last_name' => 'Owner']);
    $this->tech = User::factory()->agent()->for($this->tenant)->create(['first_name' => 'Marcus', 'last_name' => 'Bennett']);
    $portal = User::factory()->customer()->for($this->tenant)->create();
    $this->customer = Customer::factory()->for($this->tenant)->create(['email' => 'jane@example.com']);
    $this->customer->forceFill(['user_id' => $portal->id])->save();
    $this->pool = Pool::factory()->for($this->tenant)->for($this->customer)->create();
    $type = ServiceType::factory()->for($this->tenant)->create(['price' => 75]);
    $this->plan = ServiceSubscription::factory()->for($this->tenant)->create(['pool_id' => $this->pool->id, 'service_type_id' => $type->id]);

    $monday = Route::factory()->for($this->tenant)->create(['agent_id' => $this->tech->id, 'scheduled_date' => '2026-10-26']);
    $this->pastStop = RouteStop::factory()->for($monday)->for($this->pool)->create(['service_subscription_id' => $this->plan->id, 'stop_order' => 1, 'status' => 'missed']);
});

test('the office opens a tech’s earlier route, with its tech picker', function () {
    $this->actingAs($this->admin)->getJson('/api/field/today?agent='.$this->tech->id.'&date=2026-10-26')
        ->assertOk()
        ->assertJsonPath('agent.name', 'Marcus Bennett')
        ->assertJsonPath('office', true)
        ->assertJsonPath('past', true)
        ->assertJsonPath('stops.0.id', $this->pastStop->id)
        ->assertJsonCount(2, 'techs');
});

test('a tech can’t open someone else’s route, and the office can’t pick outsiders or the future', function () {
    $other = User::factory()->agent()->create(); // another company

    $this->actingAs($this->tech)->getJson('/api/field/today?agent='.$this->admin->id)
        ->assertOk()->assertJsonPath('agent.name', 'Marcus Bennett')->assertJsonPath('office', false)->assertJsonPath('techs', null);
    $this->actingAs($this->admin)->getJson('/api/field/today?agent='.$other->id)->assertStatus(422);
    $this->actingAs($this->admin)->getJson('/api/field/today?date=2026-10-30')->assertStatus(422);
});

test('a report the office fills in is the tech’s, on that day, billed that day, with no GPS', function () {
    Notification::fake();
    $this->actingAs($this->admin)
        ->postJson("/api/field/visits/{$this->pastStop->id}/complete", ['notes' => 'From the paper sheet', 'completed_lat' => 40.0, 'completed_lng' => -73.0])
        ->assertOk();

    $visit = ServiceVisit::query()->sole();
    expect($visit->agent_id)->toBe($this->tech->id)
        ->and($visit->entered_by_id)->toBe($this->admin->id)
        ->and($visit->completed_at?->toIso8601String())->toBe('2026-10-26T16:00:00+00:00') // noon in New York
        ->and($visit->getAttribute('completed_lat'))->toBeNull()
        ->and($this->pastStop->fresh()?->status)->toBe('completed')
        ->and(Charge::query()->where('service_visit_id', $visit->id)->first()?->occurred_on?->toDateString())->toBe('2026-10-26')
        ->and(AuditLog::query()->where('action', 'report.entered')->sole()->changes)->toBe(['tech' => $this->tech->id, 'day' => '2026-10-26']);
});

test('the office may hold back the customer’s email; the portal still hears', function () {
    Mail::fake();
    Notification::fake();

    $this->actingAs($this->admin)->postJson("/api/field/visits/{$this->pastStop->id}/complete", ['email_customer' => false])->assertOk();

    Mail::assertNotQueued(VisitRecapMail::class);
    Notification::assertSentTo($this->customer->user, VisitCompleted::class);
});

test('a tech can’t hold back the email, and their own visits are as before', function () {
    Mail::fake();
    $today = Route::factory()->for($this->tenant)->create(['agent_id' => $this->tech->id, 'scheduled_date' => '2026-10-29']);
    $stop = RouteStop::factory()->for($today)->for($this->pool)->create(['stop_order' => 1, 'status' => 'pending']);

    $this->actingAs($this->tech)->postJson("/api/field/visits/{$stop->id}/complete", ['email_customer' => false, 'completed_lat' => 31.1, 'completed_lng' => -81.4])->assertOk();

    $visit = ServiceVisit::query()->where('route_stop_id', $stop->id)->sole();
    expect($visit->entered_by_id)->toBeNull()
        ->and($visit->completed_at?->equalTo(now()))->toBeTrue()
        ->and((float) $visit->getAttribute('completed_lat'))->toBe(31.1);
    Mail::assertQueued(VisitRecapMail::class);
});

test('a tech’s visit that syncs the next day stays on its own day', function () {
    $this->actingAs($this->tech)->postJson("/api/field/visits/{$this->pastStop->id}/complete", [])->assertOk();

    $visit = ServiceVisit::query()->sole();
    expect($visit->completed_at?->toIso8601String())->toBe('2026-10-26T16:00:00+00:00')
        ->and($visit->entered_by_id)->toBeNull();
});

test('the office adds an extra visit to a tech’s earlier day', function () {
    $other = Pool::factory()->for($this->tenant)->for($this->customer)->create();

    $id = $this->actingAs($this->admin)->postJson("/api/field/pools/{$other->id}/visit", ['agent' => $this->tech->id, 'date' => '2026-10-20'])
        ->assertOk()->json('stop_id');

    $route = RouteStop::query()->findOrFail($id)->route;
    expect($route?->agent_id)->toBe($this->tech->id)
        ->and($route?->scheduled_date->toDateString())->toBe('2026-10-20');
});

test('staff see who typed a report in', function () {
    $this->actingAs($this->admin)->postJson("/api/field/visits/{$this->pastStop->id}/complete", [])->assertOk();
    $visit = ServiceVisit::query()->sole();

    $this->actingAs($this->admin)->get('/reports?selected='.$visit->id)
        ->assertInertia(fn (Assert $page) => $page->where('selected.entered_by', 'Sarah Owner')->where('selected.agent', 'Marcus Bennett'));
});
