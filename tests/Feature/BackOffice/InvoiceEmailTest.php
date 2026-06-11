<?php

declare(strict_types=1);

use App\Actions\GenerateInvoice;
use App\Mail\InvoiceMail;
use App\Models\Customer;
use App\Models\Pool;
use App\Models\ServiceSubscription;
use App\Models\ServiceType;
use App\Models\ServiceVisit;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\URL;

beforeEach(function () {
    config(['services.stripe.secret' => 'sk_test_x']);
    $this->tenant = Tenant::factory()->create();
    app()->instance('tenant_id', $this->tenant->id);
    $this->admin = User::factory()->for($this->tenant)->create();
    $this->customer = Customer::factory()->for($this->tenant)->create(['email' => 'home@x.test']);

    $agent = User::factory()->agent()->for($this->tenant)->create();
    $this->pool = Pool::factory()->for($this->tenant)->for($this->customer)->create();
    $type = ServiceType::factory()->for($this->tenant)->create(['price' => 50]);
    ServiceSubscription::factory()->for($this->tenant)->for($this->pool)->for($type)->create(['status' => 'active', 'assigned_agent_id' => $agent->id]);
    ServiceVisit::factory()->for($this->tenant)->for($this->pool)->create(['status' => 'completed', 'paid_at' => null, 'completed_at' => now(), 'agent_id' => $agent->id]);
});

test('an admin emails an invoice with a pay link', function () {
    Mail::fake();
    $invoice = app(GenerateInvoice::class)->handle($this->customer);

    $this->actingAs($this->admin)->post("/invoices/{$invoice?->id}/email")->assertRedirect();

    Mail::assertQueued(InvoiceMail::class, fn (InvoiceMail $m): bool => $m->invoice->id === $invoice?->id);
    expect($invoice?->fresh()?->status)->toBe('sent');
});

test('a signed pay link starts Stripe Checkout', function () {
    Http::fake(['api.stripe.com/*' => Http::response(['url' => 'https://checkout.stripe.test/p1'], 200)]);

    $url = URL::signedRoute('pay.link', ['customer' => $this->customer->id]);
    $this->get($url)->assertRedirect('https://checkout.stripe.test/p1');
});

test('an unsigned pay link is rejected', function () {
    $this->get("/pay/{$this->customer->id}")->assertForbidden();
});

test('paying an already-clear balance lands on the thank-you page', function () {
    ServiceVisit::query()->whereIn('pool_id', $this->customer->pools()->pluck('id'))->update(['paid_at' => now()]);

    $url = URL::signedRoute('pay.link', ['customer' => $this->customer->id]);
    $this->get($url)->assertRedirect('/pay/thanks');
});
