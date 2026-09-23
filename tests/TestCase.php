<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Pin the clock to mid-morning UTC so the UTC calendar date always matches
        // US tenant timezones (the factory uses America/New_York). Controllers key
        // "today" off Tenant::localToday() while tests create data with today() —
        // between 00:00–05:00 UTC those dates diverge, which made the schedule/route
        // tests flaky when the suite ran in the US evening. Framework resets test-now
        // in tearDown.
        $this->travelTo(now()->startOfDay()->addHours(15));
    }
}
