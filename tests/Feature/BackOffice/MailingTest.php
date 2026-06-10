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

test('a campaign queues an email per eligible recipient and records it', function () {
    foreach (['a', 'b', 'c'] as $n) {
        Customer::factory()->for($this->tenant)->create(['email' => "{$n}@x.test"]);
    }
    Customer::factory()->for($this->tenant)->create(['email' => 'opted@x.test', 'email_opt_out' => true]);

    $this->actingAs($this->admin)
        ->post('/mail', ['audience' => 'customers', 'subject' => 'Spring tips', 'body' => 'Hello!'])
        ->assertRedirect();

    Mail::assertQueued(CampaignMail::class, 3); // opted-out excluded

    $campaign = MailCampaign::query()->where('subject', 'Spring tips')->first();
    expect($campaign?->recipient_count)->toBe(3);
    expect($campaign?->audience)->toBe('customers');
});

test('the agents audience targets agents', function () {
    User::factory()->agent()->for($this->tenant)->count(2)->create();

    $this->actingAs($this->admin)
        ->post('/mail', ['audience' => 'agents', 'subject' => 'Team', 'body' => 'Meeting'])
        ->assertRedirect();

    Mail::assertQueued(CampaignMail::class, 2);
});

test('the composer shows audiences with live counts', function () {
    Customer::factory()->for($this->tenant)->create(['email' => 'x@x.test']);

    $this->actingAs($this->admin)
        ->get('/mail')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('mail/Index')->has('audiences', 4));
});

test('the one-click unsubscribe link sets the opt-out flag', function () {
    $customer = Customer::factory()->for($this->tenant)->create(['email' => 'leave@x.test']);
    $url = URL::signedRoute('unsubscribe', ['customer' => $customer->id]);

    $this->get($url)->assertOk();

    expect($customer->fresh()?->email_opt_out)->toBeTrue();
});

test('agents cannot use the mailing system', function () {
    $agent = User::factory()->agent()->for($this->tenant)->create();

    $this->actingAs($agent)->get('/mail')->assertForbidden();
    $this->actingAs($agent)->post('/mail', ['audience' => 'customers', 'subject' => 'x', 'body' => 'y'])->assertForbidden();
});
