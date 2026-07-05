<?php

declare(strict_types=1);

use App\Actions\RecordPayment;
use App\Models\Customer;
use App\Models\Payment;
use App\Models\Pool;
use App\Models\ServiceSubscription;
use App\Models\ServiceType;
use App\Models\ServiceVisit;
use App\Models\Tenant;
use App\Models\User;
use App\Services\BillingService;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    config(['services.stripe.secret' => 'sk_test_x']);
    $this->tenant = Tenant::factory()->create();
    app()->instance('tenant_id', $this->tenant->id);
    $agent = User::factory()->agent()->for($this->tenant)->create();

    $this->customer = Customer::factory()->for($this->tenant)->create(['email' => 'r@x.test']);
    $pool = Pool::factory()->for($this->tenant)->for($this->customer)->create();
    $type = ServiceType::factory()->for($this->tenant)->create(['price' => 60]);
    ServiceSubscription::factory()->for($this->tenant)->for($pool)->for($type)->create(['status' => 'active', 'assigned_agent_id' => $agent->id]);
    ServiceVisit::factory()->for($this->tenant)->for($pool)->create(['status' => 'completed', 'paid_at' => null, 'completed_at' => now(), 'agent_id' => $agent->id]);
    // outstanding balance = $60
});

function fakePaidSession(int $customerId, int $tenantId, string $intent): void
{
    Http::fake(['api.stripe.com/v1/checkout/sessions*' => Http::response(['data' => [[
        'payment_status' => 'paid',
        'payment_intent' => $intent,
        'metadata' => ['customer_id' => (string) $customerId, 'tenant_id' => (string) $tenantId],
    ]]], 200)]);
}

test('reconcile settles a paid checkout session the webhook missed', function () {
    fakePaidSession($this->customer->id, $this->tenant->id, 'pi_missed');

    $this->artisan('app:reconcile-stripe-payments')->assertSuccessful();

    expect(app(BillingService::class)->outstandingForCustomer($this->customer->fresh()))->toBe(0.0);
    expect(Payment::query()->where('stripe_payment_intent_id', 'pi_missed')->where('method', 'card')->exists())->toBeTrue();
});

test('reconcile does not re-settle a session already recorded (idempotent on the intent)', function () {
    // The webhook already settled this intent.
    app(RecordPayment::class)->handle($this->customer, 'card', null, 'pi_dupe');
    $before = Payment::query()->count();

    fakePaidSession($this->customer->id, $this->tenant->id, 'pi_dupe');
    $this->artisan('app:reconcile-stripe-payments')->assertSuccessful();

    expect(Payment::query()->count())->toBe($before);
    expect(Payment::query()->where('stripe_payment_intent_id', 'pi_dupe')->count())->toBe(1);
});

test('reconcile ignores an unpaid session', function () {
    Http::fake(['api.stripe.com/v1/checkout/sessions*' => Http::response(['data' => [[
        'payment_status' => 'unpaid',
        'payment_intent' => 'pi_unpaid',
        'metadata' => ['customer_id' => (string) $this->customer->id, 'tenant_id' => (string) $this->tenant->id],
    ]]], 200)]);

    $this->artisan('app:reconcile-stripe-payments')->assertSuccessful();

    expect(app(BillingService::class)->outstandingForCustomer($this->customer->fresh()))->toBe(60.0);
    expect(Payment::query()->count())->toBe(0);
});
