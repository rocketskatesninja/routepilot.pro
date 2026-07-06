<?php

declare(strict_types=1);

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Carbon;
use Inertia\Testing\AssertableInertia as Assert;

afterEach(fn () => Carbon::setTestNow());

test('tenant today() uses the tenant timezone, not UTC', function () {
    Carbon::setTestNow('2026-07-06 03:00:00'); // Monday 03:00 UTC …

    // … which is still Sunday 23:00 in New York.
    expect((new Tenant(['timezone' => 'America/New_York']))->today()->toDateString())->toBe('2026-07-05')
        ->and((new Tenant(['timezone' => null]))->today()->toDateString())->toBe('2026-07-06'); // no tz → app UTC
});

test('the schedule default date + "today" are the tenant local date, not UTC', function () {
    Carbon::setTestNow('2026-07-06 03:00:00'); // late Sunday evening in New York
    $tenant = Tenant::factory()->create(['timezone' => 'America/New_York']);
    $admin = User::factory()->for($tenant)->create();

    $this->actingAs($admin)
        ->get('/schedule')
        ->assertInertia(fn (Assert $page) => $page
            ->where('today', '2026-07-05')
            ->where('date', '2026-07-05')
        );
});
