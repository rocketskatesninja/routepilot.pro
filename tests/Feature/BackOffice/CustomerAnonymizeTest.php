<?php

declare(strict_types=1);

use App\Actions\GenerateInvoice;
use App\Actions\RecordPayment;
use App\Models\AuditLog;
use App\Models\Charge;
use App\Models\ChatMessage;
use App\Models\ChatSession;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Lead;
use App\Models\Payment;
use App\Models\PaymentMethod;
use App\Models\Pool;
use App\Models\ServiceLocation;
use App\Models\ServiceRequest;
use App\Models\ServiceSubscription;
use App\Models\ServiceType;
use App\Models\ServiceVisit;
use App\Models\Tenant;
use App\Models\User;
use App\Models\VisitPhoto;
use App\Models\WorkOrder;
use App\Models\WorkOrderComment;
use App\Notifications\AutopayDeclined;
use App\Notifications\LeadSubmitted;
use App\Notifications\ServiceRequestSubmitted;
use App\Notifications\WorkOrderRaised;
use App\Services\BillingService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;

/**
 * Removing an archived customer's personal details on request: everything that
 * identifies them goes — including their portal login's AI conversations — while
 * invoices, payments and visit records stay, under "Former customer #id".
 */
beforeEach(function () {
    Storage::fake('public');
    $this->tenant = Tenant::factory()->create();
    app()->instance('tenant_id', $this->tenant->id);
    $this->admin = User::factory()->for($this->tenant)->create();
    $agent = User::factory()->agent()->for($this->tenant)->create();

    // A customer with everything that can identify them.
    $this->login = User::factory()->customer()->for($this->tenant)->create(['email' => 'robert@anderson.test']);
    $this->customer = Customer::factory()->for($this->tenant)->create([
        'first_name' => 'Robert', 'last_name' => 'Anderson', 'email' => 'robert@anderson.test', 'phone' => '912-555-0100',
        'address_line1' => '12 Palm Way', 'city' => 'Brunswick', 'state' => 'GA', 'zip' => '31520',
        'notes' => 'Dog in the yard', 'admin_notes' => 'Pays late', 'security_code' => '4821', 'user_id' => $this->login->id,
    ]);
    Storage::disk('public')->put('customers/robert.jpg', 'x');
    $this->customer->forceFill(['photo_path' => 'customers/robert.jpg', 'stripe_customer_id' => 'cus_1'])->save();
    $card = PaymentMethod::create(['customer_id' => $this->customer->id, 'stripe_payment_method_id' => 'pm_1', 'brand' => 'visa', 'last4' => '4242', 'is_default' => true]);
    $this->customer->forceFill(['default_payment_method_id' => $card->id, 'autopay_enabled' => true])->save();

    $this->pool = Pool::factory()->for($this->tenant)->for($this->customer)->create(['name' => 'Anderson Pool', 'notes' => 'Robert’s heater is finicky']);
    $this->location = ServiceLocation::factory()->create(['pool_id' => $this->pool->id, 'gate_code' => '1234', 'access_notes' => 'Side gate']);
    $plan = ServiceSubscription::factory()->for($this->tenant)->for($this->pool)
        ->for(ServiceType::factory()->for($this->tenant)->create(['name' => 'Weekly Pool Service', 'price' => 50]))
        ->create(['assigned_agent_id' => $agent->id, 'status' => 'active']);
    $this->visit = chargedVisit($plan, ['notes' => 'Robert says hi', 'completed_lat' => 31.18, 'completed_lng' => -81.49]);
    Storage::disk('public')->put('visit-photos/1/a.jpg', 'x');
    VisitPhoto::query()->create(['service_visit_id' => $this->visit->id, 'photo_path' => 'visit-photos/1/a.jpg']);
    $this->invoice = app(GenerateInvoice::class)->handle($this->customer);
    app(RecordPayment::class)->handle($this->customer, 'check', $this->admin->id, amount: 20.0);

    $chat = ChatSession::create(['user_id' => $this->login->id, 'tenant_id' => $this->tenant->id, 'context' => 'portal']);
    ChatMessage::create(['chat_session_id' => $chat->id, 'role' => 'user', 'content' => 'My gate code is 1234']);
    DB::table('notifications')->insert(['id' => (string) Str::uuid(), 'type' => 'X', 'notifiable_type' => User::class, 'notifiable_id' => $this->login->id, 'data' => '{}', 'created_at' => now(), 'updated_at' => now()]);
    $lead = Lead::create(['name' => 'Robert Anderson', 'email' => 'robert@anderson.test', 'message' => 'Quote please']);
    $lead->forceFill(['customer_id' => $this->customer->id])->save();
    $request = ServiceRequest::create(['customer_id' => $this->customer->id, 'pool_id' => $this->pool->id, 'type' => 'add', 'message' => 'Call me on 912-555-0100']);
    $request->forceFill(['reply' => 'Sure, Robert'])->save();
    $campaign = DB::table('mail_campaigns')->insertGetId(['tenant_id' => $this->tenant->id, 'created_by' => $this->admin->id, 'subject' => 'Spring', 'body' => 'Hi', 'audience' => 'customers', 'created_at' => now(), 'updated_at' => now()]);
    DB::table('campaign_recipients')->insert(['mail_campaign_id' => $campaign, 'email' => 'robert@anderson.test', 'name' => 'Robert Anderson', 'status' => 'sent', 'created_at' => now(), 'updated_at' => now()]);

    $this->customer->delete(); // archived first
});

test('removing personal details keeps the books and erases what identifies them', function () {
    $owedBefore = app(BillingService::class)->outstandingForCustomer($this->customer);

    $this->actingAs($this->admin)->post("/customers/{$this->customer->id}/anonymize")
        ->assertSessionHas('success', fn (string $m): bool => str_contains($m, "Former customer #{$this->customer->id}"));

    $c = Customer::withTrashed()->findOrFail($this->customer->id);
    expect($c->displayName())->toBe("Former customer #{$c->id}")
        ->and([$c->email, $c->phone, $c->address_line1, $c->city, $c->notes, $c->getAttribute('admin_notes'), $c->getAttribute('security_code'), $c->getAttribute('photo_path'), $c->user_id, $c->getAttribute('stripe_customer_id')])
        ->each->toBeNull()
        ->and($c->getAttribute('anonymized_at'))->not->toBeNull()
        ->and($c->trashed())->toBeTrue();

    // Pools, addresses, visits and photos.
    expect($this->pool->fresh()->name)->toBe('Pool')
        ->and($this->pool->fresh()->notes)->toBeNull()
        ->and($this->location->fresh()->only(['address_line1', 'city', 'gate_code', 'access_notes', 'lat']))->each->toBeNull()
        ->and($this->visit->fresh()->notes)->toBeNull()
        ->and($this->visit->fresh()->completed_lat)->toBeNull()
        ->and(VisitPhoto::query()->exists())->toBeFalse();
    Storage::disk('public')->assertMissing('visit-photos/1/a.jpg');
    Storage::disk('public')->assertMissing('customers/robert.jpg');

    // The books still add up — only the pool's name left the charge.
    expect(Charge::query()->sole()->description)->toBe('Weekly Pool Service — Pool')
        ->and(Invoice::query()->whereKey($this->invoice?->id)->exists())->toBeTrue()
        ->and(Payment::query()->count())->toBe(1)
        ->and(app(BillingService::class)->outstandingForCustomer($c))->toBe($owedBefore)
        ->and(ServiceVisit::query()->whereKey($this->visit->id)->exists())->toBeTrue();

    // Everything else that named them.
    expect(PaymentMethod::query()->exists())->toBeFalse()
        ->and(Lead::query()->exists())->toBeFalse()
        ->and(ServiceRequest::query()->sole()->only(['message', 'reply']))->each->toBeNull()
        ->and(DB::table('campaign_recipients')->value('email'))->toBe("former-customer-{$c->id}@deleted.invalid")
        ->and(DB::table('campaign_recipients')->value('name'))->toBeNull();

    // Their portal login: AI conversations, notifications and the account itself.
    $login = User::withTrashed()->findOrFail($this->login->id);
    expect(ChatSession::query()->exists())->toBeFalse()
        ->and(ChatMessage::query()->exists())->toBeFalse()
        ->and(DB::table('notifications')->where('notifiable_id', $login->id)->exists())->toBeFalse()
        ->and($login->trashed())->toBeTrue()
        ->and($login->email)->toEndWith('@deleted.invalid')
        ->and(Hash::check('password', $login->password))->toBeFalse()
        ->and(AuditLog::query()->where('action', 'customer.anonymized')->where('model_id', $c->id)->exists())->toBeTrue();
});

test('work orders on their pools keep the record but lose what was written, bell alerts included', function () {
    $order = WorkOrder::factory()->for($this->tenant)->create([
        'pool_id' => $this->pool->id, 'service_visit_id' => $this->visit->id, 'title' => 'Robert wants a new pump', 'details' => 'Call Robert first',
    ]);
    $order->forceFill(['status' => 'done', 'completed_by' => $this->admin->id, 'completed_at' => now()])->save();
    $order->comments()->create(['body' => 'Robert paid cash']);
    $elsewhere = WorkOrder::factory()->for($this->tenant)->create(['title' => 'Order chlorine tabs']);

    $alert = fn (string $type, string $url): string => tap((string) Str::uuid(), fn (string $id) => DB::table('notifications')->insert([
        'id' => $id, 'type' => $type, 'notifiable_type' => User::class, 'notifiable_id' => $this->admin->id,
        'data' => json_encode(['title' => 'About Robert', 'url' => $url]), 'created_at' => now(), 'updated_at' => now(),
    ]));
    $theirs = [
        $alert(WorkOrderRaised::class, '/work-orders?selected='.$order->id),
        $alert(ServiceRequestSubmitted::class, '/inbox?selected='.ServiceRequest::query()->value('id')),
        $alert(LeadSubmitted::class, '/inbox?tab=leads&selected='.Lead::query()->value('id')),
        $alert(AutopayDeclined::class, '/balances?selected='.$this->customer->id),
    ];
    $kept = $alert(WorkOrderRaised::class, '/work-orders?selected='.$elsewhere->id);

    $this->actingAs($this->admin)->post("/customers/{$this->customer->id}/anonymize")->assertSessionHas('success');

    expect($order->fresh()?->only(['title', 'details', 'status', 'completed_by']))
        ->toBe(['title' => 'Work order (details removed)', 'details' => null, 'status' => 'done', 'completed_by' => $this->admin->id])
        ->and(WorkOrderComment::query()->exists())->toBeFalse()
        ->and($elsewhere->fresh()?->title)->toBe('Order chlorine tabs')
        ->and(DB::table('notifications')->whereIn('id', $theirs)->exists())->toBeFalse()
        ->and(DB::table('notifications')->where('id', $kept)->exists())->toBeTrue();
});

test('the archived panel shows it was done, and offers no restore', function () {
    $this->actingAs($this->admin)->post("/customers/{$this->customer->id}/anonymize");

    $this->actingAs($this->admin)->get("/people?type=archived&selected={$this->customer->id}&selected_type=customer")
        ->assertInertia(fn (Assert $page) => $page->where('selected.anonymized_on', $this->tenant->today()->toDateString()));
});

test('a customer who isn\'t archived can\'t have their details removed', function () {
    $this->customer->restore();

    $this->actingAs($this->admin)->post("/customers/{$this->customer->id}/anonymize")->assertSessionHas('error');

    expect($this->customer->fresh()->email)->toBe('robert@anderson.test');
});

test('agents can\'t remove a customer\'s details', function () {
    $agent = User::factory()->agent()->for($this->tenant)->create();

    $this->actingAs($agent)->post("/customers/{$this->customer->id}/anonymize")->assertForbidden();

    expect(Customer::withTrashed()->find($this->customer->id)?->email)->toBe('robert@anderson.test');
});

test('another company\'s customer can\'t be touched', function () {
    $otherAdmin = User::factory()->for(Tenant::factory())->create();

    $this->actingAs($otherAdmin)->post("/customers/{$this->customer->id}/anonymize")->assertNotFound();

    app()->instance('tenant_id', $this->tenant->id); // the request bound the other company
    expect(Customer::withTrashed()->find($this->customer->id)?->email)->toBe('robert@anderson.test');
});
