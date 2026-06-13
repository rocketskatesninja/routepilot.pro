<?php

declare(strict_types=1);

use App\Actions\AssembleDashboardData;
use App\Dashboard\DashboardWidgets;
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
            ->has('layout', 4)
            ->has('catalog.stats')
            ->has('available', 0)
            ->has('widgets.stats.today_stops')
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
            ->has('available', 3)
        );
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
