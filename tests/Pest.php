<?php

use App\Models\Charge;
use App\Models\Customer;
use App\Models\ServiceSubscription;
use App\Models\ServiceVisit;
use App\Models\User;
use App\Services\BillingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
|
| The closure you provide to your test functions is always bound to a specific PHPUnit test
| case class. By default, that class is "PHPUnit\Framework\TestCase". Of course, you may
| need to change it using the "pest()" function to bind a different classes or traits.
|
*/

pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->in('Feature');

/*
|--------------------------------------------------------------------------
| Expectations
|--------------------------------------------------------------------------
|
| When you're writing tests, you often need to check that values meet certain conditions. The
| "expect()" function gives you access to a set of "expectations" methods that you can use
| to assert different things. Of course, you may extend the Expectation API at any time.
|
*/

expect()->extend('toBeOne', function () {
    return $this->toBe(1);
});

/*
|--------------------------------------------------------------------------
| Functions
|--------------------------------------------------------------------------
|
| While Pest is very powerful out-of-the-box, you may have some testing code specific to your
| project that you don't want to repeat in every file. Here you can also expose helpers as
| global functions to help you to reduce the number of lines of code in your test files.
|
*/

/** A completed visit on a plan, charged on the ledger the way the field app does it. */
function chargedVisit(ServiceSubscription $plan, array $attrs = []): ServiceVisit
{
    $visit = ServiceVisit::factory()->create([
        'tenant_id' => $plan->getAttribute('tenant_id'),
        'pool_id' => $plan->pool_id,
        'service_subscription_id' => $plan->id,
        'agent_id' => $plan->assigned_agent_id ?? User::factory()->agent()->create(['tenant_id' => $plan->getAttribute('tenant_id')])->id,
        'status' => 'completed',
        'visited_at' => now(),
        'completed_at' => now(),
        ...$attrs,
    ]);
    app(BillingService::class)->chargeVisit($visit);

    return $visit;
}

/** A one-off charge on a customer's account. */
function manualCharge(Customer $customer, float $amount, string $description = 'Charge', bool $taxable = false): Charge
{
    return app(BillingService::class)->addCharge($customer, 'manual', $description, $amount, $taxable, null, null);
}
