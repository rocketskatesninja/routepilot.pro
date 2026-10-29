<?php

declare(strict_types=1);

use App\Models\Tenant;
use App\Models\User;

/*
 * The one-time migration that puts Advisories on admins' and techs' saved
 * dashboards: on top, full width, the rest moved down; never twice.
 */

beforeEach(function () {
    $this->tenant = Tenant::factory()->create();
    app()->instance('tenant_id', $this->tenant->id);
});

function addAdvisoriesToSavedDashboards(): void
{
    (require database_path('migrations/2026_11_06_000000_add_advisories_to_saved_dashboards.php'))->up();
}

test('it goes on top of both saved layouts, full width, with the rest moved down', function () {
    $admin = User::factory()->for($this->tenant)->create(['dashboard_layout' => [
        'desktop' => [['i' => 'stats', 'x' => 0, 'y' => 0, 'w' => 12, 'h' => 3], ['i' => 'requests', 'x' => 0, 'y' => 3, 'w' => 6, 'h' => 5]],
        'mobile' => [['i' => 'stats', 'x' => 0, 'y' => 0, 'w' => 12, 'h' => 5]],
    ]]);

    addAdvisoriesToSavedDashboards();

    expect($admin->fresh()?->dashboard_layout)->toBe([
        'desktop' => [
            ['i' => 'advisories', 'x' => 0, 'y' => 0, 'w' => 12, 'h' => 4],
            ['i' => 'stats', 'x' => 0, 'y' => 4, 'w' => 12, 'h' => 3],
            ['i' => 'requests', 'x' => 0, 'y' => 7, 'w' => 6, 'h' => 5],
        ],
        'mobile' => [
            ['i' => 'advisories', 'x' => 0, 'y' => 0, 'w' => 12, 'h' => 5],
            ['i' => 'stats', 'x' => 0, 'y' => 5, 'w' => 12, 'h' => 5],
        ],
    ]);
});

test('techs get it too, in the older one-layout shape as well', function () {
    $tech = User::factory()->agent()->for($this->tenant)->create(['dashboard_layout' => [['i' => 'agent_route', 'x' => 0, 'y' => 0, 'w' => 8, 'h' => 6]]]);

    addAdvisoriesToSavedDashboards();

    expect($tech->fresh()?->dashboard_layout)->toBe([
        ['i' => 'advisories', 'x' => 0, 'y' => 0, 'w' => 12, 'h' => 4],
        ['i' => 'agent_route', 'x' => 0, 'y' => 4, 'w' => 8, 'h' => 6],
    ]);
});

test('it leaves alone layouts that have it, empty or default ones, and customers', function () {
    $placed = ['desktop' => [['i' => 'stats', 'x' => 0, 'y' => 0, 'w' => 12, 'h' => 3], ['i' => 'advisories', 'x' => 8, 'y' => 3, 'w' => 4, 'h' => 6]]];
    $already = User::factory()->for($this->tenant)->create(['dashboard_layout' => $placed]);
    $defaults = User::factory()->for($this->tenant)->create(['dashboard_layout' => null]);
    $empty = User::factory()->for($this->tenant)->create(['dashboard_layout' => ['desktop' => [], 'mobile' => []]]);
    $customer = User::factory()->customer()->for($this->tenant)->create(['dashboard_layout' => ['desktop' => [['i' => 'my_pools', 'x' => 0, 'y' => 0, 'w' => 12, 'h' => 4]]]]);

    addAdvisoriesToSavedDashboards();
    addAdvisoriesToSavedDashboards(); // and running it again changes nothing

    expect($already->fresh()?->dashboard_layout)->toBe($placed)
        ->and($defaults->fresh()?->dashboard_layout)->toBeNull()
        ->and($empty->fresh()?->dashboard_layout)->toBe(['desktop' => [], 'mobile' => []])
        ->and($customer->fresh()?->dashboard_layout)->toBe(['desktop' => [['i' => 'my_pools', 'x' => 0, 'y' => 0, 'w' => 12, 'h' => 4]]]);
});
