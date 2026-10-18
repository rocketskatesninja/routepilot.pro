<?php

declare(strict_types=1);

use App\Actions\MarkMissedStops;
use App\Models\Pool;
use App\Models\Route;
use App\Models\RouteStop;
use App\Models\Tenant;
use App\Models\User;
use App\Notifications\OpsAlert;
use App\Services\DailyOpsChecks;
use Illuminate\Support\Facades\Notification;

beforeEach(function () {
    $this->tenant = Tenant::factory()->create();
    app()->instance('tenant_id', $this->tenant->id);
    $this->admin = User::factory()->for($this->tenant)->create();
    $this->agent = User::factory()->agent()->for($this->tenant)->create();
    $this->pool = Pool::factory()->for($this->tenant)->create();
});

/** A stop on the agent's route for the given day. */
function missedTestStop(string $date, string $status = 'pending'): RouteStop
{
    $route = Route::query()->firstOrCreate(
        ['tenant_id' => test()->tenant->id, 'agent_id' => test()->agent->id, 'scheduled_date' => $date],
        ['status' => 'scheduled'],
    );

    return RouteStop::factory()->for($route)->for(test()->pool)->create(['status' => $status]);
}

test('a stop still pending after its day becomes missed; today and finished stops are left alone', function () {
    $lastWeek = missedTestStop(today()->subWeek()->toDateString());
    $yesterday = missedTestStop(today()->subDay()->toDateString());
    $today = missedTestStop(today()->toDateString());
    $done = missedTestStop(today()->subDay()->toDateString(), 'completed');
    $skipped = missedTestStop(today()->subDay()->toDateString(), 'skipped');

    expect(app(MarkMissedStops::class)->handle())->toBe(2);

    expect($lastWeek->fresh()?->status)->toBe('missed');
    expect($yesterday->fresh()?->status)->toBe('missed');
    expect($today->fresh()?->status)->toBe('pending');
    expect($done->fresh()?->status)->toBe('completed');
    expect($skipped->fresh()?->status)->toBe('skipped');
});

test('marking missed stops never touches another company', function () {
    $other = Tenant::factory()->create();
    $otherRoute = Route::factory()->for($other)->create(['scheduled_date' => today()->subDay()]);
    $otherStop = RouteStop::factory()->for($otherRoute)->for(Pool::factory()->for($other))->create(['status' => 'pending']);

    app(MarkMissedStops::class)->handle();

    expect($otherStop->fresh()?->status)->toBe('pending');
});

test('stops missed yesterday raise an alert linking to that day', function () {
    missedTestStop(today()->subDay()->toDateString(), 'missed');
    Notification::fake();

    app(DailyOpsChecks::class)->run($this->tenant->id);

    Notification::assertSentTo($this->admin, OpsAlert::class, fn (OpsAlert $n): bool => $n->kind === 'missed_stops'
        && $n->url === '/schedule?date='.today()->subDay()->toDateString());
});

test('older missed stops do not alert again', function () {
    missedTestStop(today()->subDays(3)->toDateString(), 'missed');
    Notification::fake();

    app(DailyOpsChecks::class)->run($this->tenant->id);

    $kinds = collect(Notification::sent($this->admin, OpsAlert::class))->map(fn (OpsAlert $n) => $n->kind);
    expect($kinds)->not->toContain('missed_stops');
});

test('the nightly run marks yesterday\'s unfinished stops and alerts on them', function () {
    $stop = missedTestStop(today()->subDay()->toDateString());
    Notification::fake();

    $this->artisan('app:daily-ops-alerts')->assertSuccessful();

    expect($stop->fresh()?->status)->toBe('missed');
    Notification::assertSentTo($this->admin, OpsAlert::class, fn (OpsAlert $n): bool => $n->kind === 'missed_stops');
});
