<?php

declare(strict_types=1);

use App\Models\AuditLog;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Pool;
use App\Models\ServiceSubscription;
use App\Models\ServiceType;
use App\Models\ServiceVisit;
use App\Models\Tenant;
use App\Models\User;
use App\Services\BillingMeter;
use Illuminate\Support\Carbon;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->tenant = Tenant::factory()->create();
    app()->instance('tenant_id', $this->tenant->id);
    $this->admin = User::factory()->for($this->tenant)->create();
    $this->agent = User::factory()->agent()->for($this->tenant)->create();
    $this->customer = Customer::factory()->for($this->tenant)->create();
    $this->pool = Pool::factory()->for($this->tenant)->for($this->customer)->create();
    $type = ServiceType::factory()->for($this->tenant)->create(['price' => 50]);
    $this->sub = ServiceSubscription::factory()->for($this->tenant)->for($this->pool)->for($type)->create(['assigned_agent_id' => $this->agent->id, 'status' => 'active']);
    $this->visit = ServiceVisit::factory()->for($this->tenant)->for($this->pool)->create(['agent_id' => $this->agent->id, 'status' => 'completed', 'completed_at' => now()]);
});

test('archiving a customer soft-deletes them + their pools, cancels active subs, and drops them from metering', function () {
    expect(app(BillingMeter::class)->for($this->tenant->fresh())['pools']['used'])->toBe(1);

    $this->actingAs($this->admin)->delete("/customers/{$this->customer->id}")->assertRedirect();

    expect(Customer::withTrashed()->find($this->customer->id)?->trashed())->toBeTrue()
        ->and(Pool::withTrashed()->find($this->pool->id)?->trashed())->toBeTrue()
        ->and($this->sub->fresh()->status)->toBe('cancelled')
        ->and(app(BillingMeter::class)->for($this->tenant->fresh())['pools']['used'])->toBe(0)
        ->and(AuditLog::where('action', 'customer.deleted')->where('model_id', $this->customer->id)->exists())->toBeTrue();
});

test('restoring a customer brings back the customer and only the pools archived with them', function () {
    // A pool the admin deleted separately earlier must stay archived on restore.
    Carbon::setTestNow('2026-07-01 09:00:00');
    $separatelyDeleted = Pool::factory()->for($this->tenant)->for($this->customer)->create();
    $separatelyDeleted->delete();

    Carbon::setTestNow('2026-07-02 09:00:00');
    $this->actingAs($this->admin)->delete("/customers/{$this->customer->id}")->assertRedirect();

    Carbon::setTestNow('2026-07-02 09:05:00');
    $this->actingAs($this->admin)->post("/customers/{$this->customer->id}/restore")->assertRedirect();

    expect(Customer::find($this->customer->id))->not->toBeNull()            // customer restored (visible again)
        ->and(Pool::find($this->pool->id))->not->toBeNull()                // cascade-archived pool restored
        ->and(Pool::withTrashed()->find($separatelyDeleted->id)?->trashed())->toBeTrue() // separate one stays archived
        ->and($this->sub->fresh()->status)->toBe('cancelled')             // subs stay cancelled
        ->and(AuditLog::where('action', 'customer.restored')->where('model_id', $this->customer->id)->exists())->toBeTrue();

    Carbon::setTestNow();
});

test('permanently deleting an archived customer cascades all their data away', function () {
    $poolId = $this->pool->id;
    $visitId = $this->visit->id;
    Invoice::create(['customer_id' => $this->customer->id, 'number' => 'INV-T1', 'status' => 'sent', 'subtotal' => 50, 'tax' => 0, 'total' => 50, 'amount_paid' => 0]);

    $this->actingAs($this->admin)->delete("/customers/{$this->customer->id}")->assertRedirect(); // archive first
    $this->actingAs($this->admin)->delete("/customers/{$this->customer->id}/force")->assertRedirect();

    expect(Customer::withTrashed()->find($this->customer->id))->toBeNull()
        ->and(Pool::withTrashed()->find($poolId))->toBeNull()
        ->and(ServiceVisit::find($visitId))->toBeNull()
        ->and(Invoice::query()->where('customer_id', $this->customer->id)->exists())->toBeFalse()
        ->and(AuditLog::where('action', 'customer.purged')->where('model_id', $this->customer->id)->exists())->toBeTrue();
});

test('the archived tab lists only soft-deleted customers', function () {
    $this->actingAs($this->admin)->delete("/customers/{$this->customer->id}")->assertRedirect();

    $this->actingAs($this->admin)->get('/people?type=archived')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('people/Index')
            ->where('counts.archived', 1)
            ->has('people.data', 1)
            ->where('people.data.0.id', $this->customer->id));
});

test('agents cannot restore or permanently delete customers', function () {
    $agent = User::factory()->agent()->for($this->tenant)->create();
    $this->customer->delete();

    $this->actingAs($agent)->post("/customers/{$this->customer->id}/restore")->assertForbidden();
    $this->actingAs($agent)->delete("/customers/{$this->customer->id}/force")->assertForbidden();
    expect(Customer::withTrashed()->find($this->customer->id))->not->toBeNull();
});

test('a customer from another tenant cannot be restored or purged', function () {
    $other = Customer::factory()->for(Tenant::factory())->create();
    $other->delete();

    $this->actingAs($this->admin)->post("/customers/{$other->id}/restore")->assertNotFound();
    $this->actingAs($this->admin)->delete("/customers/{$other->id}/force")->assertNotFound();
});
