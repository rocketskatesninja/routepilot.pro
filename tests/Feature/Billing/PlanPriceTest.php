<?php

declare(strict_types=1);

use App\Models\AuditLog;
use App\Models\Customer;
use App\Models\Pool;
use App\Models\ServiceRequest;
use App\Models\ServiceSubscription;
use App\Models\ServiceType;
use App\Models\Tenant;
use App\Models\User;
use App\Notifications\ServiceRequestAnswered;
use App\Services\Chat\PublicLeadContext;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Inertia\Testing\AssertableInertia as Assert;

/*
 * Pools aren't all priced alike: each plan keeps the price agreed for that pool,
 * and a service's price is only where a new plan starts. A company that quotes
 * pool by pool can keep its prices from customers, and sets each pool's price
 * when it approves the request.
 */

beforeEach(function () {
    Carbon::setTestNow('2026-10-15 14:00:00'); // a Thursday
    Mail::fake();
    Notification::fake();

    $this->tenant = Tenant::factory()->create(['slug' => 'acme']);
    app()->instance('tenant_id', $this->tenant->id);
    $this->admin = User::factory()->for($this->tenant)->create();
    $this->agent = User::factory()->agent()->for($this->tenant)->create();
    $this->login = User::factory()->customer()->for($this->tenant)->create();
    $this->customer = Customer::factory()->for($this->tenant)->create(['user_id' => $this->login->id, 'email' => 'robert@example.test']);
    $this->pool = Pool::factory()->for($this->tenant)->for($this->customer)->create(['name' => 'Backyard pool']);
    $this->service = ServiceType::factory()->for($this->tenant)->create(['name' => 'Weekly Pool Service', 'price' => 50, 'requestable' => true]);
});

function pricedPlan(array $attrs = []): ServiceSubscription
{
    return ServiceSubscription::factory()->for(test()->tenant)->for(test()->pool)->for(test()->service)
        ->create(['assigned_agent_id' => test()->agent->id, 'status' => 'active', ...$attrs]);
}

test('a new plan starts at its service\'s price, unless it\'s given its own', function () {
    expect((float) pricedPlan()->price)->toBe(50.0)
        ->and((float) pricedPlan(['price' => 72.5])->price)->toBe(72.5);
});

test('a visit is billed at its plan\'s price, not the service\'s', function () {
    $visit = chargedVisit(pricedPlan(['price' => 65]));

    expect((float) $visit->charges()->sole()->amount)->toBe(65.0);
});

test('a new service price is where new plans start; existing plans keep theirs', function () {
    $before = pricedPlan();
    $this->service->update(['price' => 80]);

    expect((float) $before->fresh()->price)->toBe(50.0)
        ->and((float) pricedPlan()->price)->toBe(80.0);
});

test('the office sets and changes a pool\'s price on its plan', function () {
    $this->actingAs($this->admin)->post('/subscriptions', [
        'pool_id' => $this->pool->id, 'service_type_id' => $this->service->id, 'price' => 42,
        'frequency' => 'weekly', 'preferred_day' => 'tuesday', 'starts_on' => '2026-10-20',
    ])->assertRedirect()->assertSessionHasNoErrors();
    $plan = ServiceSubscription::query()->where('pool_id', $this->pool->id)->sole();
    expect((float) $plan->price)->toBe(42.0);

    $this->actingAs($this->admin)->patch("/subscriptions/{$plan->id}", [
        'service_type_id' => $this->service->id, 'price' => 47.5, 'frequency' => 'weekly', 'preferred_day' => 'tuesday', 'status' => 'active',
    ])->assertRedirect()->assertSessionHasNoErrors();
    expect((float) $plan->fresh()->price)->toBe(47.5);

    // A change can leave the price out, but not blank it.
    $this->actingAs($this->admin)->patch("/subscriptions/{$plan->id}", [
        'service_type_id' => $this->service->id, 'price' => '', 'frequency' => 'weekly', 'preferred_day' => 'tuesday', 'status' => 'active',
    ])->assertInvalid('price');
});

test('approving a request sets this pool\'s price, and the answer tells the customer', function () {
    $request = ServiceRequest::create(['customer_id' => $this->customer->id, 'pool_id' => $this->pool->id, 'type' => 'service', 'service_type_id' => $this->service->id]);

    $this->actingAs($this->admin)->post("/requests/{$request->id}/approve", [
        'price' => 65, 'frequency' => 'weekly', 'preferred_day' => 'tuesday', 'starts_on' => '2026-10-20', 'assigned_agent_id' => $this->agent->id,
    ])->assertRedirect()->assertSessionHasNoErrors();

    expect((float) ServiceSubscription::query()->where('pool_id', $this->pool->id)->sole()->price)->toBe(65.0);
    Notification::assertSentTo($this->login, ServiceRequestAnswered::class, fn ($n) => str_contains($n->body, 'at $65.00 a visit'));
});

test('the answer leaves the price out when the company bills another way', function () {
    $this->tenant->forceFill(['customer_billing' => false])->save();
    $request = ServiceRequest::create(['customer_id' => $this->customer->id, 'pool_id' => $this->pool->id, 'type' => 'service', 'service_type_id' => $this->service->id]);

    $this->actingAs($this->admin)->post("/requests/{$request->id}/approve", [
        'price' => 65, 'frequency' => 'weekly', 'preferred_day' => 'tuesday', 'starts_on' => '2026-10-20',
    ])->assertRedirect();

    Notification::assertSentTo($this->login, ServiceRequestAnswered::class, fn ($n) => ! str_contains($n->body, '$'));
});

test('a company that quotes each pool keeps its prices from customers', function () {
    $this->tenant->forceFill(['show_prices' => false])->save();

    $this->actingAs($this->login)->get('/requests')->assertInertia(fn (Assert $page) => $page
        ->where('services.0.name', 'Weekly Pool Service')
        ->where('services.0.price', null));

    auth()->logout();
    app()->forgetInstance('tenant_id');
    $this->get('/t/acme')->assertInertia(fn (Assert $page) => $page
        ->where('live.services', fn ($services) => collect($services)->every(fn ($s) => $s['price'] === null)));

    $chat = app(PublicLeadContext::class)->build($this->tenant->fresh());
    expect($chat)->not->toContain('$50')->and($chat)->toContain('never give or guess');
});

test('by default customers see the price, and the website chat may quote it', function () {
    $this->actingAs($this->login)->get('/requests')->assertInertia(fn (Assert $page) => $page->where('services.0.price', 50));
    expect(app(PublicLeadContext::class)->build($this->tenant))->toContain('$50.00 per visit');
});

test('an admin switches prices off for customers, and it\'s audited', function () {
    $this->actingAs($this->admin)->patch('/company/features', ['show_prices' => false])
        ->assertRedirect()->assertSessionHas('success', 'Showing prices to customers is off.');

    expect($this->tenant->fresh()->show_prices)->toBeFalse()
        ->and(AuditLog::query()->where('action', 'company.show_prices')->exists())->toBeTrue();
});

test('customers see what they pay for each of their services, even when list prices are hidden', function () {
    pricedPlan(['price' => 65]);
    $this->tenant->forceFill(['show_prices' => false])->save();

    $this->actingAs($this->login)->get('/requests')->assertInertia(fn (Assert $page) => $page
        ->where('plans.0.service', 'Weekly Pool Service')
        ->where('plans.0.price', 65)
        ->where('services.0.price', null));

    $this->tenant->forceFill(['customer_billing' => false])->save();
    $this->actingAs($this->login)->get('/requests')->assertInertia(fn (Assert $page) => $page->where('plans.0.price', null));
});
