<?php

declare(strict_types=1);

use App\Dashboard\DashboardWidgets;
use App\Models\Customer;
use App\Models\Pool;
use App\Models\ServiceType;
use App\Models\Tenant;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

/*
 * A customer's dashboard has "Pay your bill" (the balance and a Pay button that
 * goes straight to checkout) and "Request a service" (what the company offers,
 * each a tap from its request form). Both are on new dashboards, and a one-time
 * migration added them to the end of saved ones.
 */

beforeEach(function () {
    config(['services.stripe.secret' => 'sk_test_x']);
    $this->tenant = Tenant::factory()->create();
    app()->instance('tenant_id', $this->tenant->id);
    $this->portal = User::factory()->customer()->for($this->tenant)->create();
    $this->customer = Customer::factory()->for($this->tenant)->create(['user_id' => $this->portal->id]);
    Pool::factory()->for($this->tenant)->for($this->customer)->create();
});

/** A widget's data as the customer's dashboard sends it. */
function customerWidget(User $user, string $key): mixed
{
    $data = null;
    test()->actingAs($user)->get('/dashboard')->assertOk()
        ->assertInertia(function (Assert $page) use (&$data, $key) {
            $data = $page->toArray()['props']['widgets'][$key] ?? null;
        });

    return $data;
}

test('Pay your bill: what they owe, and whether they can pay it online', function () { // (JSON sends 0.0 as 0, so compared loosely)
    expect(customerWidget($this->portal, 'pay_bill'))->toEqual(['balance' => 0, 'autopay' => false, 'can_pay' => true]);

    manualCharge($this->customer, 85, 'Filter');
    expect(customerWidget($this->portal, 'pay_bill'))->toEqual(['balance' => 85, 'autopay' => false, 'can_pay' => true]);

    $this->customer->forceFill(['autopay_enabled' => true])->save();
    config(['services.stripe.secret' => '']);
    expect(customerWidget($this->portal, 'pay_bill'))->toEqual(['balance' => 85, 'autopay' => true, 'can_pay' => false]);
});

test('Request a service: up to four the company offers customers, cheapest first, with the count', function () {
    $make = fn (string $name, float $price, bool $requestable = true, bool $active = true) => ServiceType::factory()->for($this->tenant)
        ->create(['name' => $name, 'price' => $price, 'requestable' => $requestable, 'is_active' => $active, 'frequency' => 'weekly']);
    $make('Weekly service', 50);
    $make('Filter clean', 95);
    $make('Chemistry check', 25);
    $make('Green-to-clean', 350);
    $make('Opening', 225);
    $make('Staff only', 10, requestable: false);
    $make('Retired', 5, active: false);

    $widget = customerWidget($this->portal, 'request_service');

    expect(array_column($widget['services'], 'name'))->toBe(['Chemistry check', 'Weekly service', 'Filter clean', 'Opening'])
        ->and($widget['services'][0])->toMatchArray(['price' => 25.0, 'frequency' => 'weekly'])
        ->and($widget['total'])->toBe(5);
});

test('both are on a new customer dashboard, desktop and phone', function () {
    $layouts = DashboardWidgets::layoutsFor($this->portal);

    expect(array_column($layouts['desktop'], 'i'))->toContain('pay_bill', 'request_service')
        ->and(array_column($layouts['mobile'], 'i'))->toContain('pay_bill', 'request_service');
});

test('the migration adds them at the end of saved customer layouts, once', function () {
    $migration = require database_path('migrations/2026_11_08_000000_add_customer_widgets_to_saved_dashboards.php');
    $this->portal->forceFill(['dashboard_layout' => [
        'desktop' => [['i' => 'next_visit', 'x' => 0, 'y' => 0, 'w' => 6, 'h' => 3], ['i' => 'my_pools', 'x' => 0, 'y' => 3, 'w' => 12, 'h' => 4]],
        'mobile' => [['i' => 'my_pools', 'x' => 0, 'y' => 0, 'w' => 12, 'h' => 4], ['i' => 'pay_bill', 'x' => 0, 'y' => 4, 'w' => 12, 'h' => 3]],
    ]])->save();
    $staff = User::factory()->for($this->tenant)->create(['dashboard_layout' => ['desktop' => [['i' => 'stats', 'x' => 0, 'y' => 0, 'w' => 12, 'h' => 3]]]]);
    $empty = User::factory()->customer()->for($this->tenant)->create(['dashboard_layout' => ['desktop' => [], 'mobile' => []]]);

    $migration->up();
    $migration->up();

    expect($this->portal->fresh()?->dashboard_layout)->toBe([
        'desktop' => [
            ['i' => 'next_visit', 'x' => 0, 'y' => 0, 'w' => 6, 'h' => 3],
            ['i' => 'my_pools', 'x' => 0, 'y' => 3, 'w' => 12, 'h' => 4],
            ['i' => 'pay_bill', 'x' => 0, 'y' => 7, 'w' => 6, 'h' => 4],
            ['i' => 'request_service', 'x' => 6, 'y' => 7, 'w' => 6, 'h' => 5],
        ],
        'mobile' => [
            ['i' => 'my_pools', 'x' => 0, 'y' => 0, 'w' => 12, 'h' => 4],
            ['i' => 'pay_bill', 'x' => 0, 'y' => 4, 'w' => 12, 'h' => 3], // already there, left as it was
            ['i' => 'request_service', 'x' => 0, 'y' => 7, 'w' => 12, 'h' => 5],
        ],
    ])->and($staff->fresh()?->dashboard_layout)->toBe(['desktop' => [['i' => 'stats', 'x' => 0, 'y' => 0, 'w' => 12, 'h' => 3]]])
        ->and($empty->fresh()?->dashboard_layout)->toBe(['desktop' => [], 'mobile' => []]);
});
