<?php

declare(strict_types=1);

use App\Actions\SyncExtraPoolBilling;
use App\Models\AuditLog;
use App\Models\Customer;
use App\Models\Pool;
use App\Models\Tenant;
use App\Models\User;
use Laravel\Cashier\Subscription;
use Tests\Support\FakeStripe;

/*
 * Pools over the plan's allowance are billed on the company's subscription: the
 * per-pool price at a quantity of its extra pools. It's counted on the renewal
 * day, so changes never make a partial charge. Stripe is faked (FakeStripe).
 */

beforeEach(function () {
    config(['billing.included_pools' => 50, 'billing.prices.base' => 'price_base', 'billing.prices.pool' => 'price_pool']);
    $this->stripe = FakeStripe::install();
    $this->tenant = Tenant::factory()->create();
    app()->instance('tenant_id', $this->tenant->id);
    $this->customer = Customer::factory()->for($this->tenant)->create();
});

afterEach(fn () => FakeStripe::uninstall());

function poolsFor(int $count, ?Tenant $tenant = null): void
{
    $tenant ??= test()->tenant;
    Pool::factory()->for($tenant)->for(Customer::factory()->for($tenant)->create())->count($count)->create();
}

/** The company is on the base plan, as Stripe Checkout leaves it. */
function subscribed(?Tenant $tenant = null): Subscription
{
    $tenant ??= test()->tenant;
    $sub = $tenant->subscriptions()->create(['type' => 'default', 'stripe_id' => 'sub_'.$tenant->id, 'stripe_status' => 'active', 'stripe_price' => 'price_base', 'quantity' => 1]);
    $sub->items()->create(['stripe_id' => 'si_base_'.$tenant->id, 'stripe_product' => 'prod_fake', 'stripe_price' => 'price_base', 'quantity' => 1]);
    test()->stripe->knows('si_base_'.$tenant->id, 'price_base', 1);

    return $sub;
}

function syncPools(): ?int
{
    return app(SyncExtraPoolBilling::class)->handle(test()->tenant->fresh());
}

function billedPools(): ?int
{
    $quantity = test()->tenant->fresh()->subscription('default')?->items()->where('stripe_price', 'price_pool')->value('quantity');

    return $quantity === null ? null : (int) $quantity;
}

test('a company over the allowance gets its extra pools on its subscription, with no partial charge', function () {
    poolsFor(53);
    subscribed();

    expect(syncPools())->toBe(3)->and(billedPools())->toBe(3);

    $added = collect($this->stripe->writes())->firstWhere('path', '/v1/subscription_items');
    expect($added['params'])->toMatchArray(['subscription' => 'sub_'.$this->tenant->id, 'price' => 'price_pool', 'quantity' => 3, 'proration_behavior' => 'none']);

    $audit = AuditLog::query()->where('action', 'billing.extra_pools')->sole();
    expect($audit->getAttribute('tenant_id'))->toBe($this->tenant->id)
        ->and($audit->changes)->toBe(['from' => 0, 'to' => 3]);
});

test('the count follows the pools, and comes off once back within the allowance', function () {
    poolsFor(53);
    subscribed();
    syncPools();

    Pool::query()->limit(2)->get()->each->delete();
    expect(syncPools())->toBe(1)->and(billedPools())->toBe(1);
    $changed = collect($this->stripe->writes())->last();
    expect($changed['method'])->toBe('post')
        ->and($changed['path'])->toStartWith('/v1/subscription_items/si_')
        ->and($changed['params'])->toMatchArray(['quantity' => 1, 'proration_behavior' => 'none']);

    Pool::query()->first()->delete(); // 50: within the allowance
    expect(syncPools())->toBe(0)->and(billedPools())->toBeNull();
    $removed = collect($this->stripe->writes())->last();
    expect($removed['method'])->toBe('delete')
        ->and($removed['params'])->toMatchArray(['proration_behavior' => 'none']);
});

test('a new extra-pool price moves the line over, never billing both', function () {
    poolsFor(53);
    $sub = subscribed();
    $sub->items()->create(['stripe_id' => 'si_oldpool', 'stripe_product' => 'prod_fake', 'stripe_price' => 'price_pool_old', 'quantity' => 3]);
    $this->stripe->knows('si_oldpool', 'price_pool_old', 3);

    expect(syncPools())->toBe(3)->and(billedPools())->toBe(3);

    $writes = collect($this->stripe->writes());
    expect($writes->firstWhere('method', 'post')['params'])->toMatchArray(['price' => 'price_pool', 'quantity' => 3, 'proration_behavior' => 'none'])
        ->and($writes->last())->toMatchArray(['method' => 'delete', 'path' => '/v1/subscription_items/si_oldpool'])
        ->and($this->tenant->fresh()->subscription('default')->items()->pluck('stripe_price')->sort()->values()->all())->toBe(['price_base', 'price_pool'])
        ->and(AuditLog::query()->where('action', 'billing.extra_pools')->sole()->changes)->toMatchArray(['old_price' => 'price_pool_old', 'new_price' => 'price_pool']);

    $sent = count($this->stripe->writes());
    expect(syncPools())->toBe(3)->and(count($this->stripe->writes()))->toBe($sent); // settled: nothing more
});

test('nothing is sent when the count hasn\'t changed, or there\'s no subscription', function () {
    poolsFor(53);
    expect(syncPools())->toBeNull(); // on its trial: nothing to bill yet

    subscribed();
    syncPools();
    $sent = count($this->stripe->writes());
    expect(syncPools())->toBe(3)
        ->and(count($this->stripe->writes()))->toBe($sent);
});

test('checkout puts the extra pools on the first bill', function () {
    $admin = User::factory()->for($this->tenant)->create();
    poolsFor(53);

    $this->actingAs($admin)->post('/billing/checkout')->assertRedirect('https://checkout.stripe.test/cs_fake');

    $session = collect($this->stripe->writes())->firstWhere('path', '/v1/checkout/sessions');
    expect($session['params']['line_items'])->toEqualCanonicalizing([
        ['price' => 'price_base', 'quantity' => 1],
        ['price' => 'price_pool', 'quantity' => 3],
    ]);
});

test('checkout within the allowance is just the base plan', function () {
    $admin = User::factory()->for($this->tenant)->create();
    poolsFor(20);

    $this->actingAs($admin)->post('/billing/checkout')->assertRedirect();

    $session = collect($this->stripe->writes())->firstWhere('path', '/v1/checkout/sessions');
    expect($session['params']['line_items'])->toBe([['price' => 'price_base', 'quantity' => 1]]);
});

test('the hourly job updates every subscribed company and leaves the rest', function () {
    poolsFor(53);
    subscribed();
    $other = Tenant::factory()->create();
    poolsFor(60, $other); // over, but no subscription

    $this->artisan('app:sync-pool-billing --dry')->expectsOutputToContain('[dry-run]')->assertSuccessful();
    expect($this->stripe->writes())->toBe([]);

    $this->artisan('app:sync-pool-billing')->expectsOutputToContain('1 updated, 0 failed')->assertSuccessful();
    expect(billedPools())->toBe(3);
});
