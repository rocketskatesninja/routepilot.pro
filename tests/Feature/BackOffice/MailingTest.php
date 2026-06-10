<?php

declare(strict_types=1);

use App\Mail\CampaignMail;
use App\Models\Customer;
use App\Models\MailCampaign;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\URL;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    Mail::fake();
    $this->tenant = Tenant::factory()->create();
    app()->instance('tenant_id', $this->tenant->id);
    $this->admin = User::factory()->for($this->tenant)->create();
});

test('a tenant admin emails their customers (opt-outs excluded)', function () {
    foreach (['a', 'b', 'c'] as $n) {
        Customer::factory()->for($this->tenant)->create(['email' => "{$n}@x.test"]);
    }
    Customer::factory()->for($this->tenant)->create(['email' => 'opted@x.test', 'email_opt_out' => true]);

    $this->actingAs($this->admin)
        ->post('/people/email', ['audience' => 'customers', 'subject' => 'Spring tips', 'body' => 'Hello!'])
        ->assertRedirect();

    Mail::assertQueued(CampaignMail::class, 3);
    expect(MailCampaign::query()->where('subject', 'Spring tips')->where('audience', 'customers')->exists())->toBeTrue();
});

test('a tenant admin emails their agents', function () {
    User::factory()->agent()->for($this->tenant)->count(2)->create();

    $this->actingAs($this->admin)
        ->post('/people/email', ['audience' => 'agents', 'subject' => 'Team', 'body' => 'Meeting'])
        ->assertRedirect();

    Mail::assertQueued(CampaignMail::class, 2);
});

test('a tenant admin cannot target other tenants', function () {
    $this->actingAs($this->admin)
        ->post('/people/email', ['audience' => 'tenants', 'subject' => 'x', 'body' => 'y'])
        ->assertInvalid('audience');
});

test('the People screen offers email audiences to a tenant admin', function () {
    Customer::factory()->for($this->tenant)->create(['email' => 'x@x.test']);

    $this->actingAs($this->admin)
        ->get('/people')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('people/Index')->where('canEmail', true)->has('audiences', 2));
});

test('a tenant admin can email a hand-picked selection', function () {
    $c1 = Customer::factory()->for($this->tenant)->create(['email' => 'c1@x.test']);
    Customer::factory()->for($this->tenant)->create(['email' => 'c2@x.test']);
    $c3 = Customer::factory()->for($this->tenant)->create(['email' => 'c3@x.test']);

    $this->actingAs($this->admin)
        ->post('/people/email', ['audience' => 'selected', 'recipients' => ["customer:{$c1->id}", "customer:{$c3->id}"], 'subject' => 'Hi', 'body' => 'Yo'])
        ->assertRedirect();

    Mail::assertQueued(CampaignMail::class, 2);
});

test('a selection cannot reach another tenant\'s customer', function () {
    $foreign = Customer::factory()->for(Tenant::factory())->create(['email' => 'foreign@x.test']);

    $this->actingAs($this->admin)
        ->post('/people/email', ['audience' => 'selected', 'recipients' => ["customer:{$foreign->id}"], 'subject' => 'Hi', 'body' => 'Yo'])
        ->assertRedirect();

    Mail::assertNothingQueued();
});

test('a selected send requires a recipient list', function () {
    $this->actingAs($this->admin)
        ->post('/people/email', ['audience' => 'selected', 'subject' => 'Hi', 'body' => 'Yo'])
        ->assertInvalid('recipients');
});

test('agents cannot send campaigns', function () {
    $agent = User::factory()->agent()->for($this->tenant)->create();

    $this->actingAs($agent)->post('/people/email', ['audience' => 'customers', 'subject' => 'x', 'body' => 'y'])->assertForbidden();
});

test('one-click unsubscribe opts a customer out', function () {
    $customer = Customer::factory()->for($this->tenant)->create(['email' => 'leave@x.test']);
    $url = URL::signedRoute('unsubscribe', ['customer' => $customer->id]);

    $this->get($url)->assertOk();

    expect($customer->fresh()?->email_opt_out)->toBeTrue();
});
