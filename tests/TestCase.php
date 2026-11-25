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

    /**
     * Requests start the way they do in production: with no company bound, so
     * ResolveTenant has to do it. Tests bind `tenant_id` up front to build their
     * data, and leaving that in place for the request hid a real binding-order
     * bug once. The test's own binding is put back afterwards for its assertions.
     */
    public function call($method, $uri, $parameters = [], $cookies = [], $files = [], $server = [], $content = null)
    {
        $tenant = $this->app->has('tenant') ? $this->app->make('tenant') : null;
        $tenantId = $this->app->has('tenant_id') ? $this->app->make('tenant_id') : null;
        $this->app->forgetInstance('tenant');
        $this->app->forgetInstance('tenant_id');

        try {
            return parent::call($method, $uri, $parameters, $cookies, $files, $server, $content);
        } finally {
            $this->app->forgetInstance('tenant');
            $this->app->forgetInstance('tenant_id');
            if ($tenant !== null) {
                $this->app->instance('tenant', $tenant);
            }
            if ($tenantId !== null) {
                $this->app->instance('tenant_id', $tenantId);
            }
        }
    }
}
