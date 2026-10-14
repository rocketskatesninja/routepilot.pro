<?php

declare(strict_types=1);

use App\Actions\ArchiveCustomer;
use App\Actions\CreateSubscription;
use App\Actions\UpdateSubscription;
use App\Models\Customer;
use App\Models\Pool;
use App\Models\Route;
use App\Models\RouteStop;
use App\Models\ServiceSubscription;
use App\Models\ServiceType;
use App\Models\ServiceVisit;
use App\Models\Tenant;
use App\Models\User;
use App\Services\Chat\ToolRegistry;
use App\Services\Chat\Tools\ChangePreferredDay;
use App\Services\Chat\Tools\ReassignAgent;
use Illuminate\Support\Carbon;

/**
 * The scheduling core: every plan change keeps its upcoming stops in sync, every
 * schedule type does what it says, and "today" is always the company's date.
 * The company is in America/New_York while the server clock runs on UTC.
 * Window: 8 weeks from Monday 2026-06-08 (through 2026-08-03).
 */
beforeEach(function () {
    Carbon::setTestNow('2026-06-08 12:00:00'); // Monday, 8 am Eastern

    $this->tenant = Tenant::factory()->create(); // America/New_York
    app()->instance('tenant', $this->tenant);
    app()->instance('tenant_id', $this->tenant->id);
    $this->admin = User::factory()->for($this->tenant)->create(); // tenant_admin
    $this->agent = User::factory()->agent()->for($this->tenant)->create(['first_name' => 'Ann', 'last_name' => 'Tech']);
    $this->customer = Customer::factory()->for($this->tenant)->create(['first_name' => 'Jan', 'last_name' => 'Harris']);
    $this->pool = Pool::factory()->for($this->tenant)->for($this->customer)->create();
    $this->type = ServiceType::factory()->for($this->tenant)->create();
});

afterEach(fn () => Carbon::setTestNow());

/** Create a plan the way the app does (so it's scheduled immediately). */
function newPlan(array $attrs = []): ServiceSubscription
{
    $t = test();

    return app(CreateSubscription::class)->handle([
        'pool_id' => $t->pool->id,
        'service_type_id' => $t->type->id,
        'assigned_agent_id' => $t->agent->id,
        'frequency' => 'weekly',
        'preferred_day' => 'tuesday',
        ...$attrs,
    ]);
}

/** @return list<string> pending stop dates for a plan, ascending */
function planDates(ServiceSubscription $plan): array
{
    return RouteStop::query()
        ->where('service_subscription_id', $plan->id)
        ->where('status', 'pending')
        ->with('route')
        ->get()
        ->map(fn (RouteStop $s): string => $s->route->scheduled_date->toDateString())
        ->sort()->values()->all();
}

// --- schedule types -------------------------------------------------------

test('a new plan is on the routes straight away', function () {
    $dates = planDates(newPlan());

    expect($dates)->toHaveCount(8)->and($dates[0])->toBe('2026-06-09');
});

test('a one-time job is a single visit on its date', function () {
    $plan = newPlan(['frequency' => 'one_time', 'preferred_day' => null, 'starts_on' => '2026-06-17']);
    $this->artisan('app:materialize-schedules')->assertSuccessful(); // re-running adds nothing

    expect(planDates($plan))->toBe(['2026-06-17']);
});

test('every 2 weeks counts from the plan\'s start date', function () {
    $plan = newPlan(['frequency' => 'biweekly', 'starts_on' => '2026-06-16']);

    expect(planDates($plan))->toBe(['2026-06-16', '2026-06-30', '2026-07-14', '2026-07-28']);
});

test('monthly lands on the chosen day, clamped to short months', function () {
    $plan = newPlan(['frequency' => 'monthly', 'preferred_day' => null, 'day_of_month' => 31]);

    expect(planDates($plan))->toBe(['2026-06-30', '2026-07-31']);
});

test('a plan with an end date stops there (seasonal service)', function () {
    $plan = newPlan(['ends_on' => '2026-06-23']);

    expect(planDates($plan))->toBe(['2026-06-09', '2026-06-16', '2026-06-23']);
});

test('a plan that starts later begins on its start date', function () {
    $dates = planDates(newPlan(['starts_on' => '2026-07-01']));

    expect($dates)->toBe(['2026-07-07', '2026-07-14', '2026-07-21', '2026-07-28']);
});

// --- one clock: the company's date ----------------------------------------

test('after 8 pm Eastern, today is still today', function () {
    Carbon::setTestNow('2026-06-09 01:00:00'); // Monday 9 pm Eastern = Tuesday on the server

    expect(planDates(newPlan(['preferred_day' => 'monday']))[0])->toBe('2026-06-08');
});

test('changing the day in the evening keeps today\'s visit', function () {
    Carbon::setTestNow('2026-06-09 01:00:00'); // Monday 9 pm Eastern
    $plan = newPlan(['preferred_day' => 'tuesday']);

    app(UpdateSubscription::class)->handle($plan, ['preferred_day' => 'monday']);

    expect(planDates($plan)[0])->toBe('2026-06-08');
});

test('the nightly job schedules by the company\'s date', function () {
    Carbon::setTestNow('2026-06-09 02:00:00'); // the job's 02:00 UTC slot = Monday 10 pm Eastern
    $plan = ServiceSubscription::factory()->for($this->tenant)->for($this->pool)->for($this->type)
        ->create(['assigned_agent_id' => $this->agent->id, 'frequency' => 'weekly', 'preferred_day' => 'monday']);

    $this->artisan('app:materialize-schedules')->assertSuccessful();

    expect(planDates($plan)[0])->toBe('2026-06-08');
});

// --- one write path: every change goes through the plan actions ------------

test('ending a plan clears its upcoming visits', function () {
    $plan = newPlan();

    app(UpdateSubscription::class)->handle($plan, ['status' => 'cancelled']);

    expect(planDates($plan))->toBe([]);
});

test('deleting a plan clears its upcoming visits', function () {
    $plan = newPlan();

    $this->actingAs($this->admin)->delete("/subscriptions/{$plan->id}")->assertRedirect();

    expect(ServiceSubscription::query()->find($plan->id))->toBeNull()
        ->and(RouteStop::query()->where('pool_id', $this->pool->id)->count())->toBe(0);
});

test('a plan with completed visits can\'t be deleted — it can only be ended', function () {
    $plan = newPlan();
    $stop = RouteStop::query()->where('service_subscription_id', $plan->id)->first();
    $stop->update(['status' => 'completed']);
    ServiceVisit::factory()->for($this->tenant)->for($this->pool)->create([
        'route_stop_id' => $stop->id, 'agent_id' => $this->agent->id, 'status' => 'completed',
    ]);

    $this->actingAs($this->admin)->delete("/subscriptions/{$plan->id}")->assertSessionHas('error');

    expect(ServiceSubscription::query()->find($plan->id))->not->toBeNull();
});

test('archiving a customer clears their upcoming visits', function () {
    $plan = newPlan();

    app(ArchiveCustomer::class)->handle($this->customer);

    expect(RouteStop::query()->where('service_subscription_id', $plan->id)->where('status', 'pending')->count())->toBe(0);
});

test('the AI "reassign" tool moves upcoming visits to the new tech', function () {
    $plan = newPlan();
    $bo = User::factory()->agent()->for($this->tenant)->create(['first_name' => 'Bo', 'last_name' => 'Diver']);

    (new ReassignAgent)->execute(['customer_name' => 'Jan Harris', 'agent_name' => 'Bo Diver'], $this->tenant->id);

    $agents = RouteStop::query()->where('service_subscription_id', $plan->id)->with('route')->get()
        ->map(fn (RouteStop $s) => $s->route->agent_id)->unique()->values()->all();
    expect($agents)->toBe([$bo->id]);
});

test('the AI "reassign" tool only assigns plans to staff, never to a customer', function () {
    $plan = newPlan();
    User::factory()->for($this->tenant)->create(['role' => 'customer', 'first_name' => 'Bo', 'last_name' => 'Diver']);

    (new ReassignAgent)->execute(['customer_name' => 'Jan Harris', 'agent_name' => 'Bo Diver'], $this->tenant->id);

    expect($plan->fresh()->assigned_agent_id)->toBe($this->agent->id);
});

test('the AI "change day" tool moves upcoming visits to the new day', function () {
    $plan = newPlan(['preferred_day' => 'tuesday']);

    (new ChangePreferredDay)->execute(['customer_name' => 'Jan Harris', 'new_day' => 'friday'], $this->tenant->id);

    expect(planDates($plan)[0])->toBe('2026-06-12');
});

test('the old "delete stops" workaround tool is gone', function () {
    expect(ToolRegistry::find('delete_stops'))->toBeNull();
});

test('the schedule board can\'t drag a visit onto a different day', function () {
    $plan = newPlan();
    $stop = RouteStop::query()->where('service_subscription_id', $plan->id)->with('route')->get()
        ->first(fn (RouteStop $s) => $s->route->scheduled_date->toDateString() === '2026-06-09');
    $wednesday = Route::factory()->for($this->tenant)->create(['agent_id' => $this->agent->id, 'scheduled_date' => '2026-06-10']);

    $this->actingAs($this->admin)->post('/schedule/arrange', [
        'routes' => [['id' => $wednesday->id, 'stop_ids' => [$stop->id]]],
    ]);

    expect($stop->fresh()->route_id)->toBe($stop->route_id);
});

// --- what people see --------------------------------------------------------

test('form mistakes are explained in plain words', function () {
    $this->actingAs($this->admin)->post('/subscriptions', [
        'pool_id' => $this->pool->id, 'service_type_id' => $this->type->id, 'frequency' => 'weekly',
    ])->assertSessionHasErrors(['preferred_day' => 'Pick which day of the week.']);

    $this->actingAs($this->admin)->post('/subscriptions', [
        'pool_id' => $this->pool->id, 'service_type_id' => $this->type->id, 'frequency' => 'one_time', 'starts_on' => '',
    ])->assertSessionHasErrors(['starts_on' => 'Pick the date for this one-time job.']);
});

test('the pool page shows a plan\'s vacation hold and keeps ended plans visible', function () {
    $held = newPlan();
    app(UpdateSubscription::class)->handle($held, ['hold_starts_at' => '2026-06-20', 'hold_ends_at' => '2026-07-04']);
    $ended = newPlan(['preferred_day' => 'friday']);
    app(UpdateSubscription::class)->handle($ended, ['status' => 'cancelled']);

    $plans = collect($this->actingAs($this->admin)->get("/pools?selected={$this->pool->id}")
        ->assertOk()->viewData('page')['props']['selected']['subscriptions']);

    expect($plans->firstWhere('id', $held->id)['hold_label'])->toBe('On hold Jun 20 – Jul 4')
        ->and($plans->firstWhere('id', $ended->id)['status'])->toBe('cancelled')
        ->and($plans->last()['id'])->toBe($ended->id); // ended plans sort last
});

test('the schedule board says which service each visit is', function () {
    newPlan();

    $routes = $this->actingAs($this->admin)->get('/schedule?date=2026-06-09')->assertOk()->viewData('page')['props']['routes'];
    $stop = collect($routes)->flatMap(fn ($r) => $r['stops'])->first();

    expect($stop['service'])->toBe($this->type->name);
});
