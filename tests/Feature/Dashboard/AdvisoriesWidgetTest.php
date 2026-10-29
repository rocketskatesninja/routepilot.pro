<?php

declare(strict_types=1);

use App\Actions\GenerateInvoice;
use App\Dashboard\Advisories;
use App\Models\ChemicalInventory;
use App\Models\ChemicalReading;
use App\Models\Customer;
use App\Models\Lead;
use App\Models\Pool;
use App\Models\Route;
use App\Models\RouteStop;
use App\Models\ServiceLocation;
use App\Models\ServiceRequest;
use App\Models\ServiceSubscription;
use App\Models\ServiceType;
use App\Models\ServiceVisit;
use App\Models\Tenant;
use App\Models\TenantSetting;
use App\Models\User;
use App\Models\WorkOrder;
use App\Services\TenantMailer;
use Illuminate\Support\Carbon;
use Inertia\Testing\AssertableInertia as Assert;

/*
 * The Advisories widget (the toady.net idea): what needs attention right now,
 * worst first, naming the actual pools and people (two, then "and N more") and
 * linking to where it's fixed. Admins see the company; a tech sees their own day.
 */

beforeEach(function () {
    Carbon::setTestNow('2026-10-29 15:00:00'); // Thursday 11:00 in New York
    $this->tenant = Tenant::factory()->create(['logo_path' => 'logos/x.png', 'reply_to_email' => 'office@example.test']);
    app()->instance('tenant_id', $this->tenant->id);
    $this->admin = User::factory()->for($this->tenant)->create();
    $this->tech = User::factory()->agent()->for($this->tenant)->create(['first_name' => 'Marcus', 'last_name' => 'Bennett']);
    $this->type = ServiceType::factory()->for($this->tenant)->create();
});

/** A pool with a map position, an active plan on the tech, and a fresh water test (so it raises nothing). */
function advisoryPool(string $name): Pool
{
    $pool = Pool::factory()->for(test()->tenant)->create(['name' => $name, 'customer_id' => Customer::factory()->for(test()->tenant)]);
    ServiceLocation::factory()->create(['pool_id' => $pool->id, 'lat' => 31.1, 'lng' => -81.4]);
    ServiceSubscription::factory()->for($pool)->create(['service_type_id' => test()->type->id, 'assigned_agent_id' => test()->tech->id]);
    $visit = ServiceVisit::factory()->for($pool)->create(['status' => 'completed', 'agent_id' => test()->tech->id]);
    ChemicalReading::factory()->create(['service_visit_id' => $visit->id]);

    return $pool;
}

/** A stop on the tech's route for a day. */
function advisoryStop(Pool $pool, string $day, string $status = 'pending', ?User $tech = null): RouteStop
{
    $agentId = ($tech ?? test()->tech)->id;
    $route = Route::query()->where('agent_id', $agentId)->whereDate('scheduled_date', $day)->first()
        ?? Route::factory()->for(test()->tenant)->create(['agent_id' => $agentId, 'scheduled_date' => $day]);

    return RouteStop::factory()->for($route)->for($pool)->create(['status' => $status, 'stop_order' => $route->stops()->count() + 1]);
}

/** @return list<string> */
function advisoryMessages(array $items): array
{
    return array_column($items, 'msg');
}

test('a quiet, set-up company is all clear', function () {
    $pool = advisoryPool('Cruz Pool');
    advisoryStop($pool, '2026-10-29');
    advisoryStop($pool, '2026-10-30');

    expect(Advisories::forAdmin($this->admin, null))->toBe([]);
});

test('missed stops come first, named, and link to that day', function () {
    advisoryStop(advisoryPool('Cruz Pool'), '2026-10-28', 'missed');
    advisoryStop(advisoryPool('Park Pool'), '2026-10-28', 'missed');
    ChemicalInventory::factory()->for($this->tenant)->create(['chemical_name' => 'Soda Ash', 'current_stock' => 0, 'reorder_threshold' => 5]);

    $items = Advisories::forAdmin($this->admin, null);

    expect($items[0])->toBe([
        'sev' => 'red', 'icon' => 'CalendarX',
        'msg' => 'Cruz Pool and Park Pool were missed yesterday: nobody finished or skipped them.',
        'href' => '/schedule?date=2026-10-28',
    ]);
});

test('names stop at two and "N more"; one item links to itself, several to the list', function () {
    foreach (['Alpha Pool', 'Bravo Pool', 'Charlie Pool', 'Delta Pool'] as $name) {
        $pool = Pool::factory()->for($this->tenant)->create(['name' => $name]);
        ServiceSubscription::factory()->for($pool)->create(['service_type_id' => $this->type->id, 'assigned_agent_id' => $this->tech->id]);
    }
    $alone = Pool::factory()->for($this->tenant)->create(['name' => 'Echo Pool']);
    ServiceSubscription::factory()->for($alone)->create(['service_type_id' => $this->type->id, 'assigned_agent_id' => null]);
    ServiceVisit::factory()->for($alone)->create(['status' => 'completed', 'agent_id' => $this->tech->id])
        ->chemicalReading()->create(ChemicalReading::factory()->make()->getAttributes());

    $items = collect(Advisories::forAdmin($this->admin, null))->keyBy('icon');

    expect($items['TestTube'])->toMatchArray([
        'sev' => 'amber',
        'msg' => 'Alpha Pool, Bravo Pool and 2 more haven’t had a water test in over 2 weeks.',
        'href' => '/pools',
    ])->and($items['UserX'])->toMatchArray([
        'msg' => 'Echo Pool has a plan but no tech, so it won’t be routed.',
        'href' => '/pools?selected='.$alone->id,
    ]);
});

test('urgent and overdue work orders, past-due bills, waiting requests and leads, low stock', function () {
    $pool = advisoryPool('Cruz Pool');
    advisoryStop($pool, '2026-10-29');
    advisoryStop($pool, '2026-10-30');
    $urgent = WorkOrder::factory()->create(['pool_id' => $pool->id, 'title' => 'Pump leaking', 'urgent' => true]);
    $overdue = WorkOrder::factory()->create(['pool_id' => $pool->id, 'title' => 'New light', 'due_on' => '2026-10-27']);
    WorkOrder::factory()->create(['pool_id' => $pool->id, 'title' => 'Not yet due', 'due_on' => '2026-11-02']);

    $customer = Customer::factory()->for($this->tenant)->create(['first_name' => 'Jane', 'last_name' => 'Doe']);
    $invoice = app(GenerateInvoice::class)->handle(tap($customer, fn ($c) => manualCharge($c, 50, 'Weekly service')));
    $invoice?->update(['status' => 'overdue']);

    $old = ServiceRequest::create(['customer_id' => $customer->id, 'pool_id' => $pool->id, 'type' => 'service', 'message' => 'Green water']);
    $old->forceFill(['created_at' => now()->subDays(3)])->save();
    ServiceRequest::create(['customer_id' => $customer->id, 'pool_id' => $pool->id, 'type' => 'service', 'message' => 'Just asked']);
    $lead = Lead::create(['name' => 'Robert Anderson', 'email' => 'robert@anderson.test', 'message' => 'Quote please']);
    $lead->forceFill(['created_at' => now()->subDays(2)])->save();
    Lead::create(['name' => 'Fresh Lead', 'email' => 'fresh@example.test', 'message' => 'Hi']);
    $chlorine = ChemicalInventory::factory()->for($this->tenant)->create(['chemical_name' => 'Liquid Chlorine', 'current_stock' => 2, 'reorder_threshold' => 10]);

    $items = Advisories::forAdmin($this->admin, null);

    expect(array_column($items, 'href', 'msg'))->toBe([
        'Urgent work order: “Pump leaking”.' => '/work-orders?selected='.$urgent->id,
        'Jane Doe has a bill past due.' => '/balances?selected='.$customer->id,
        'Jane Doe’s request has waited over 2 days for an answer.' => '/inbox?selected='.$old->id,
        'A website lead from Robert Anderson hasn’t been contacted for over a day.' => '/inbox?tab=leads&selected='.$lead->id,
        'Work order “New light” is past its due date.' => '/work-orders?selected='.$overdue->id,
        'Liquid Chlorine is running low.' => '/inventory?selected='.$chlorine->id,
    ])->and(array_column($items, 'sev'))->toBe(['red', 'amber', 'amber', 'amber', 'amber', 'amber']);
});

test('company setup: a failing mail server, an address off the map, no logo, no reply-to', function () {
    TenantSetting::setFor($this->tenant->id, TenantMailer::FAILURE_KEY, '{"reason":"Login refused"}');
    $this->tenant->forceFill(['logo_path' => null, 'reply_to_email' => null, 'address_line1' => '1 Main St', 'city' => 'Brunswick', 'state' => 'GA', 'lat' => null])->save();

    $items = Advisories::forAdmin($this->admin->fresh(), null);

    expect(array_column($items, 'href', 'icon'))->toBe([
        'MailWarning' => '/company?tab=email',
        'MapPinOff' => '/company?tab=address',
        'Image' => '/company',
        'Reply' => '/company',
    ])->and($items[0]['sev'])->toBe('red');
});

test('a tech with nothing today is news only on a day the others are working', function () {
    $other = User::factory()->agent()->for($this->tenant)->create();
    $pool = advisoryPool('Cruz Pool');
    advisoryStop($pool, '2026-10-30');

    expect(advisoryMessages(Advisories::forAdmin($this->admin, null)))->toBe([]);

    advisoryStop($pool, '2026-10-29', tech: $other);
    expect(advisoryMessages(Advisories::forAdmin($this->admin, null)))->toBe(['Marcus Bennett has no stops today.']);
});

test('weather: storms and heat are warnings, rain is a heads-up', function () {
    $hours = fn (array $over) => ['hours' => array_map(fn ($h) => [...['hour' => '1pm', 'temp' => 80, 'code' => 1, 'precip' => 0], ...$h], $over)];

    expect(Advisories::forAgent($this->tech, $hours([[], ['hour' => '3pm', 'code' => 95, 'precip' => 80], ['temp' => 97]])))->toMatchArray([
        ['sev' => 'amber', 'icon' => 'CloudLightning', 'msg' => 'Thunderstorms likely around 3pm: stop work while there’s lightning.', 'href' => null],
        ['sev' => 'amber', 'icon' => 'ThermometerSun', 'msg' => 'Up to 97° in the next few hours: drink water and take breaks in the shade.', 'href' => null],
    ])->and(advisoryMessages(Advisories::forAgent($this->tech, $hours([['hour' => '4pm', 'code' => 61, 'precip' => 60], ['temp' => 30]]))))->toBe([
        'Down to 30° in the next few hours: watch for ice and check freeze protection.',
        'You have no stops today.',
        'Rain likely around 4pm (60%).',
    ])->and(Advisories::forAgent($this->tech, ['unavailable' => true]))->toHaveCount(1);
});

test('a tech sees their own day: urgent jobs, their missed stops, unplaced pools, jobs on today’s pools', function () {
    $other = User::factory()->agent()->for($this->tenant)->create();
    $mapped = advisoryPool('Cruz Pool');
    $unplaced = Pool::factory()->for($this->tenant)->create(['name' => 'Park Pool']);
    advisoryStop($mapped, '2026-10-29');
    advisoryStop($unplaced, '2026-10-29');
    advisoryStop($mapped, '2026-10-28', 'missed');
    advisoryStop(advisoryPool('Elsewhere Pool'), '2026-10-28', 'missed', $other); // someone else's
    $mine = WorkOrder::factory()->create(['pool_id' => $mapped->id, 'title' => 'Pump leaking', 'urgent' => true, 'assigned_to' => $this->tech->id]);
    WorkOrder::factory()->create(['pool_id' => $mapped->id, 'title' => 'Office job', 'urgent' => true, 'assigned_to' => $other->id]);
    Customer::factory()->for($this->tenant)->create(); // company matters (bills, leads) aren't a tech's

    expect(array_column(Advisories::forAgent($this->tech, null), 'href', 'msg'))->toBe([
        'Urgent work order: “Pump leaking”.' => '/work-orders?selected='.$mine->id,
        'Cruz Pool was missed yesterday, on your route: nobody finished or skipped it.' => '/schedule?date=2026-10-28',
        'Park Pool has no map position, so directions won’t work.' => '/pools?selected='.$unplaced->id,
        'Cruz Pool, on today’s route, has an open work order: you’ll see it when you open the visit.' => '/field',
    ]);
});

test('it is on new admin and tech dashboards, worst first, with the tech flag', function () {
    advisoryStop(advisoryPool('Cruz Pool'), '2026-10-28', 'missed');

    $widget = function (User $user): ?array {
        $data = null;
        $this->actingAs($user)->get('/dashboard')->assertOk()
            ->assertInertia(function (Assert $page) use (&$data) {
                $data = $page->toArray()['props']['widgets']['advisories'] ?? null;
            });

        return $data;
    };

    expect($widget($this->admin))->toMatchArray(['tech' => false])
        ->and($widget($this->admin)['items'][0]['icon'])->toBe('CalendarX')
        ->and($widget($this->tech))->toMatchArray(['tech' => true])
        ->and(advisoryMessages($widget($this->tech)['items']))->toContain('Cruz Pool was missed yesterday, on your route: nobody finished or skipped it.');
});
