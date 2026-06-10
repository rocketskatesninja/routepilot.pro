<?php

declare(strict_types=1);

use App\Mail\CampaignMail;
use App\Models\Customer;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Facades\Mail;
use Inertia\Testing\AssertableInertia as Assert;

// No tenant is bound here — a super-admin operates platform-wide.
beforeEach(function () {
    Mail::fake();
    $this->super = User::factory()->create();
    $this->super->forceFill(['role' => 'super_admin', 'tenant_id' => null])->save();
});

test('a super-admin emails every customer across all tenants', function () {
    Customer::factory()->for(Tenant::factory())->create(['email' => 'a@x.test']);
    Customer::factory()->for(Tenant::factory())->create(['email' => 'b@x.test']);

    $this->actingAs($this->super)
        ->post('/people/email', ['audience' => 'customers', 'subject' => 'Platform notice', 'body' => 'Hi all'])
        ->assertRedirect();

    Mail::assertQueued(CampaignMail::class, 2);
});

test('a super-admin emails every tenant admin', function () {
    User::factory()->for(Tenant::factory())->create(['email' => 'admin1@x.test']);
    User::factory()->for(Tenant::factory())->create(['email' => 'admin2@x.test']);

    $this->actingAs($this->super)
        ->post('/people/email', ['audience' => 'tenants', 'subject' => 'For owners', 'body' => 'Update'])
        ->assertRedirect();

    Mail::assertQueued(CampaignMail::class, 2);
});

test('a super-admin emails every agent across all tenants', function () {
    User::factory()->agent()->for(Tenant::factory())->count(2)->create();

    $this->actingAs($this->super)
        ->post('/people/email', ['audience' => 'agents', 'subject' => 'Field memo', 'body' => 'Note'])
        ->assertRedirect();

    Mail::assertQueued(CampaignMail::class, 2);
});

test('a super-admin can email a hand-picked selection across tenants', function () {
    $a = Customer::factory()->for(Tenant::factory())->create(['email' => 'a@x.test']);
    $b = Customer::factory()->for(Tenant::factory())->create(['email' => 'b@x.test']);

    $this->actingAs($this->super)
        ->post('/people/email', ['audience' => 'selected', 'recipients' => ["customer:{$a->id}", "customer:{$b->id}"], 'subject' => 'Hi', 'body' => 'Yo'])
        ->assertRedirect();

    Mail::assertQueued(CampaignMail::class, 2);
});

test('the super People screen offers the three platform audiences', function () {
    $this->actingAs($this->super)
        ->get('/people')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('people/Platform')->has('audiences', 3)->has('counts'));
});
