<?php

declare(strict_types=1);

use App\Actions\GenerateInvoice;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Pool;
use App\Models\RouteStop;
use App\Models\ServiceSubscription;
use App\Models\ServiceType;
use App\Models\Tenant;
use App\Models\User;

beforeEach(function () {
    $this->tenant = Tenant::factory()->create();
    app()->instance('tenant_id', $this->tenant->id);
    $this->agent = User::factory()->agent()->for($this->tenant)->create();
});

test('the materialize command creates route stops for active subscriptions', function () {
    $customer = Customer::factory()->for($this->tenant)->create();
    $pool = Pool::factory()->for($this->tenant)->for($customer)->create();
    $type = ServiceType::factory()->for($this->tenant)->create();
    ServiceSubscription::factory()->for($this->tenant)->for($pool)->for($type)->create(['assigned_agent_id' => $this->agent->id, 'status' => 'active']);

    $this->artisan('app:materialize-schedules')->assertSuccessful();

    expect(RouteStop::query()->count())->toBeGreaterThan(0);
});

test('the overdue command flags unpaid invoices past due', function () {
    $customer = Customer::factory()->for($this->tenant)->create();
    manualCharge($customer, 50, 'Weekly service');
    $invoice = app(GenerateInvoice::class)->handle($customer);
    $invoice?->update(['status' => 'sent', 'due_at' => now()->subDays(10)]);

    $this->artisan('app:flag-overdue-invoices')->assertSuccessful();

    expect($invoice?->fresh()?->status)->toBe('overdue');
});

test('the monthly command invoices owing customers', function () {
    $customer = Customer::factory()->for($this->tenant)->create();
    $pool = Pool::factory()->for($this->tenant)->for($customer)->create();
    $type = ServiceType::factory()->for($this->tenant)->create(['price' => 60]);
    chargedVisit(ServiceSubscription::factory()->for($this->tenant)->for($pool)->for($type)->create(['assigned_agent_id' => $this->agent->id, 'status' => 'active']));

    $this->artisan('app:generate-invoices')->assertSuccessful();

    expect(Invoice::query()->where('customer_id', $customer->id)->exists())->toBeTrue();
});

test('the backup command runs', function () {
    $this->artisan('app:backup-database')->assertSuccessful();
});
