<?php

declare(strict_types=1);

use App\Models\ChemicalReading;
use App\Models\Customer;
use App\Models\Pool;
use App\Models\Route;
use App\Models\ServiceSubscription;
use App\Models\ServiceType;
use App\Models\ServiceVisit;
use App\Models\Tenant;
use App\Models\User;
use App\Notifications\OpsAlert;
use App\Services\DailyOpsChecks;
use Illuminate\Support\Facades\Notification;

beforeEach(function () {
    $this->tenant = Tenant::factory()->create();
    app()->instance('tenant_id', $this->tenant->id);
    $this->admin = User::factory()->for($this->tenant)->create(); // tenant_admin, active
    $this->serviceType = ServiceType::factory()->for($this->tenant)->create();
});

/** An active plan on a pool, with optional assignment overrides. */
function activePlan(Pool $pool, array $attrs = []): ServiceSubscription
{
    return ServiceSubscription::factory()->for($pool)->create([
        'service_type_id' => test()->serviceType->id,
        ...$attrs,
    ]);
}

/** Run the checks and return the kinds of alert raised for the admin (tolerant of zero). */
function alertKinds(User $admin): array
{
    app(DailyOpsChecks::class)->run($admin->getAttribute('tenant_id'));

    return collect(Notification::sent($admin, OpsAlert::class))->map(fn (OpsAlert $n) => $n->kind)->all();
}

test('an idle active agent raises an idle-agents alert', function () {
    User::factory()->agent()->for($this->tenant)->create(); // active, no route today
    Notification::fake();

    expect(alertKinds($this->admin))->toContain('idle_agents');
});

test('an active plan with no assigned agent raises an unassigned-pools alert', function () {
    $pool = Pool::factory()->for($this->tenant)->create();
    activePlan($pool); // assigned_agent_id null
    Notification::fake();

    expect(alertKinds($this->admin))->toContain('unassigned_pools');
});

test('an assigned plan does not raise the unassigned-pools alert', function () {
    $agent = User::factory()->agent()->for($this->tenant)->create();
    $pool = Pool::factory()->for($this->tenant)->create();
    activePlan($pool, ['assigned_agent_id' => $agent->id]);
    Route::factory()->for($this->tenant)->create(['agent_id' => $agent->id, 'scheduled_date' => today()]);
    Notification::fake();

    expect(alertKinds($this->admin))->not->toContain('unassigned_pools');
});

test('an active pool with no recent reading raises a stale-chemistry alert', function () {
    $pool = Pool::factory()->for($this->tenant)->create();
    activePlan($pool, ['assigned_agent_id' => $this->admin->id]);
    Notification::fake();

    expect(alertKinds($this->admin))->toContain('stale_chemistry');
});

test('a recently tested pool does not raise stale chemistry', function () {
    $pool = Pool::factory()->for($this->tenant)->create();
    activePlan($pool, ['assigned_agent_id' => $this->admin->id]);
    $agent = User::factory()->agent()->for($this->tenant)->create();
    $visit = ServiceVisit::factory()->for($pool)->create(['status' => 'completed', 'agent_id' => $agent->id]);
    ChemicalReading::factory()->create(['service_visit_id' => $visit->id]);
    Notification::fake();

    expect(alertKinds($this->admin))->not->toContain('stale_chemistry');
});

test('a long-unpaid completed visit raises an overdue-balances alert', function () {
    $customer = Customer::factory()->for($this->tenant)->create();
    $pool = Pool::factory()->for($this->tenant)->for($customer)->create();
    $agent = User::factory()->agent()->for($this->tenant)->create();
    ServiceVisit::factory()->for($pool)->create(['status' => 'completed', 'paid_at' => null, 'visited_at' => now()->subDays(40), 'agent_id' => $agent->id]);
    Notification::fake();

    expect(alertKinds($this->admin))->toContain('overdue_balances');
});

test('admins who turned off ops notifications are not alerted', function () {
    $this->admin->notificationPreferences()->create(['category' => 'ops', 'in_app' => false, 'email' => false]);
    User::factory()->agent()->for($this->tenant)->create(); // would trigger idle_agents
    Notification::fake();

    app(DailyOpsChecks::class)->run($this->tenant->id);

    Notification::assertNotSentTo($this->admin, OpsAlert::class);
});

test('checks are tenant-scoped — a foreign tenant\'s idle agent is not alerted here', function () {
    $other = Tenant::factory()->create();
    User::factory()->agent()->for($other)->create();
    Notification::fake();

    // Our tenant has no agents/pools — nothing to alert on.
    expect(alertKinds($this->admin))->toBe([]);
});
