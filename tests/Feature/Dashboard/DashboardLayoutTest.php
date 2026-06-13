<?php

declare(strict_types=1);

use App\Actions\AssembleDashboardData;
use App\Dashboard\DashboardWidgets;
use App\Models\Customer;
use App\Models\Pool;
use App\Models\Route;
use App\Models\RouteStop;
use App\Models\ServiceLocation;
use App\Models\Tenant;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

/**
 * The tenant_admin command-center is a per-user customizable widget grid: the
 * saved layout decides which widgets are placed and computed, and the save is
 * a trust boundary (known keys only, geometry clamped, per-user).
 */
beforeEach(function () {
    $this->tenant = Tenant::factory()->create();
    app()->instance('tenant_id', $this->tenant->id);
    $this->admin = User::factory()->for($this->tenant)->create();
});

test('the admin dashboard renders the default widget grid', function () {
    $this->actingAs($this->admin)
        ->get('/dashboard')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('dashboards/Admin')
            ->has('layout', 5)
            ->has('catalog.stats')
            ->has('catalog.route_map')
            ->has('catalog.weather')
            ->has('available', 5) // the opt-in widgets (week_strip, today_stops, weather, billing, notifications)
            ->has('widgets.stats.today_stops')
            ->has('widgets.route_map.markers')
            ->has('widgets.recent_visits')
        );
});

test('saving a layout sanitizes unknown keys and clamps geometry', function () {
    $this->actingAs($this->admin)
        ->post('/dashboard/layout', ['layout' => [
            ['i' => 'stats', 'x' => 0, 'y' => 0, 'w' => 99, 'h' => 1],   // w over-max, h under-min
            ['i' => 'requests', 'x' => 0, 'y' => 5, 'w' => 6, 'h' => 5],
            ['i' => 'bogus', 'x' => 0, 'y' => 0, 'w' => 4, 'h' => 4],     // unknown -> dropped
        ]])
        ->assertRedirect();

    $layout = collect($this->admin->refresh()->dashboard_layout);

    expect($layout->pluck('i')->sort()->values()->all())->toBe(['requests', 'stats']);

    $stats = $layout->firstWhere('i', 'stats');
    expect($stats['w'])->toBe(12)   // clamped down from 99
        ->and($stats['h'])->toBe(2); // clamped up to minH
});

test('a saved layout is per-user', function () {
    $other = User::factory()->for($this->tenant)->create();

    $this->actingAs($this->admin)
        ->post('/dashboard/layout', ['layout' => [['i' => 'stats', 'x' => 0, 'y' => 0, 'w' => 12, 'h' => 3]]])
        ->assertRedirect();

    expect($this->admin->refresh()->dashboard_layout)->toHaveCount(1)
        ->and($other->refresh()->dashboard_layout)->toBeNull();
});

test('only the placed widgets compute data; the rest are offered to add', function () {
    $this->admin->forceFill(['dashboard_layout' => [['i' => 'stats', 'x' => 0, 'y' => 0, 'w' => 12, 'h' => 3]]])->save();

    $this->actingAs($this->admin)
        ->get('/dashboard')
        ->assertInertia(fn (Assert $page) => $page
            ->component('dashboards/Admin')
            ->has('layout', 1)
            ->has('widgets.stats')
            ->missing('widgets.my_route')
            ->has('available', 9)
        );
});

test('the week strip covers seven days of stop counts', function () {
    $customer = Customer::factory()->for($this->tenant)->create();
    $pool = Pool::factory()->for($this->tenant)->for($customer)->create();
    $route = Route::factory()->for($this->tenant)->create(['agent_id' => null, 'scheduled_date' => today()]);
    RouteStop::factory()->for($route)->for($pool)->create(['stop_order' => 1, 'status' => 'completed']);
    RouteStop::factory()->for($route)->for($pool)->create(['stop_order' => 2, 'status' => 'pending']);

    $strip = app(AssembleDashboardData::class)->handle($this->admin, ['week_strip'])['week_strip'];

    expect($strip['days'])->toHaveCount(7)
        ->and($strip['days'][0])->toMatchArray(['total' => 2, 'completed' => 1, 'is_today' => true]);
});

test('the billing summary reports zero when nothing is outstanding', function () {
    $summary = app(AssembleDashboardData::class)->handle($this->admin, ['billing_summary'])['billing_summary'];

    expect($summary)->toMatchArray(['outstanding_total' => 0.0, 'customer_count' => 0])
        ->and($summary['top'])->toBe([]);
});

test('weather degrades to null without a business address', function () {
    $this->tenant->forceFill(['lat' => null, 'lng' => null])->save();

    $data = app(AssembleDashboardData::class)->handle($this->admin, ['weather']);

    expect($data['weather'])->toBeNull();
});

test('the route map widget assembles geocoded stops, HQ, and the maps key', function () {
    config(['services.google.browser_maps_key' => 'browser-test-key']);
    $this->tenant->forceFill(['lat' => 33.5, 'lng' => -112.0])->save();

    $agent = User::factory()->agent()->for($this->tenant)->create(['map_color' => '#ff0000']);
    $customer = Customer::factory()->for($this->tenant)->create();

    $geocoded = Pool::factory()->for($this->tenant)->for($customer)->create(['name' => 'Sunny Pool']);
    ServiceLocation::factory()->for($geocoded)->create(['lat' => 33.6, 'lng' => -112.1]);
    $unmapped = Pool::factory()->for($this->tenant)->for($customer)->create(); // no service location

    $route = Route::factory()->for($this->tenant)->create(['agent_id' => $agent->id, 'scheduled_date' => today()]);
    RouteStop::factory()->for($route)->for($geocoded)->create(['stop_order' => 1, 'status' => 'pending']);
    RouteStop::factory()->for($route)->for($unmapped)->create(['stop_order' => 2, 'status' => 'pending']);

    $map = app(AssembleDashboardData::class)->handle($this->admin, ['route_map'])['route_map'];

    expect($map['maps_key'])->toBe('browser-test-key')
        ->and($map['hq'])->toMatchArray(['lat' => 33.5, 'lng' => -112.0])
        ->and($map['markers'])->toHaveCount(1)                 // the unmapped stop is dropped
        ->and($map['markers'][0])->toMatchArray([
            'order' => 1,
            'pool' => 'Sunny Pool',
            'color' => '#ff0000',
            'agent' => $agent->displayName(),
        ]);
});

test('the route map degrades without a maps key', function () {
    config(['services.google.browser_maps_key' => null]);

    $map = app(AssembleDashboardData::class)->handle($this->admin, ['route_map'])['route_map'];

    expect($map['maps_key'])->toBeNull()
        ->and($map['markers'])->toBe([]);
});

test('guests cannot save a dashboard layout', function () {
    $this->post('/dashboard/layout', ['layout' => []])->assertRedirect('/login');
});

test('the widget catalog role-filters', function () {
    expect(DashboardWidgets::keysForRole('tenant_admin'))->toContain('stats', 'my_route', 'requests', 'recent_visits')
        ->and(DashboardWidgets::keysForRole('agent'))->toBe(['stats']);

    $agent = User::factory()->agent()->for($this->tenant)->create();
    expect(collect(DashboardWidgets::available($agent, []))->pluck('key')->all())->toBe(['stats']);
});

test('assembled widget data is tenant-scoped and lazy', function () {
    User::factory()->agent()->for($this->tenant)->create();
    $otherTenant = Tenant::factory()->create();
    User::factory()->count(3)->agent()->for($otherTenant)->create();

    $data = app(AssembleDashboardData::class)->handle($this->admin, ['stats']);

    expect($data['stats']['agents'])->toBe(1)        // only this tenant's active agent
        ->and($data)->toHaveKey('stats')
        ->and($data)->not->toHaveKey('my_route');     // only the enabled widget was computed
});
