<?php

declare(strict_types=1);

use App\Models\Lead;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Testing\TestResponse;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->tenant = Tenant::factory()->create(['slug' => 'acme-pools']);
    $this->admin = User::factory()->for($this->tenant)->create();
});

test('the public form captures a lead and notifies admins', function () {
    $this->postJson('/public/acme-pools/leads', ['name' => 'Pat Homeowner', 'email' => 'pat@x.test', 'source' => 'quote', 'message' => 'Weekly service quote?'])
        ->assertOk();

    expect(Lead::query()->withoutGlobalScopes()->where('tenant_id', $this->tenant->id)->where('name', 'Pat Homeowner')->exists())->toBeTrue();
    expect($this->admin->notifications()->count())->toBe(1);
});

test('the lead form requires a name', function () {
    $this->postJson('/public/acme-pools/leads', ['email' => 'x@x.test'])->assertStatus(422);
});

test('an admin sees the leads inbox and updates status', function () {
    app()->instance('tenant_id', $this->tenant->id);
    $lead = Lead::create(['name' => 'Jo', 'email' => 'jo@x.test', 'source' => 'contact']);

    $this->actingAs($this->admin)
        ->get('/insights')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('reports/Insights')->has('leads', 1));

    $this->actingAs($this->admin)->patch("/leads/{$lead->id}", ['status' => 'contacted'])->assertRedirect();
    expect($lead->fresh()?->status)->toBe('contacted');
});

test('agents cannot see the leads inbox', function () {
    $agent = User::factory()->agent()->for($this->tenant)->create();

    $this->actingAs($agent)->get('/insights')->assertForbidden();
});

/** Send the acme-pools website form from an IP (the test client's default is 127.0.0.1). */
function postWebsiteLead(array $fields, string $ip = '203.0.113.7'): TestResponse
{
    return test()->withServerVariables(['REMOTE_ADDR' => $ip])
        ->postJson('/public/acme-pools/leads', ['name' => 'Pat', 'source' => 'contact', ...$fields]);
}

function companyLeads(Tenant $tenant): Builder
{
    return Lead::query()->withoutGlobalScopes()->where('tenant_id', $tenant->id);
}

test('a website lead keeps the IP it was sent from, out of sight of the company', function () {
    postWebsiteLead(['email' => 'pat@x.test'])->assertOk();

    $lead = companyLeads($this->tenant)->firstOrFail();
    expect($lead->ip_address)->toBe('203.0.113.7')
        ->and($lead->toArray())->not->toHaveKey('ip_address');
});

test('a sender with three unanswered messages has to wait for a reply', function () {
    foreach (range(1, 3) as $i) {
        postWebsiteLead(['email' => 'pat@x.test', 'message' => "try {$i}"])->assertOk();
    }

    postWebsiteLead(['email' => 'pat@x.test'])
        ->assertStatus(429)
        ->assertJsonPath('ok', false)
        ->assertJsonPath('error', fn (string $e): bool => str_contains($e, 'wait for our reply'));

    expect(companyLeads($this->tenant)->count())->toBe(3)
        ->and($this->admin->notifications()->count())->toBe(3);
});

test('new email addresses from the same IP still count as the same sender', function () {
    foreach (range(1, 3) as $i) {
        postWebsiteLead(['email' => "rl{$i}@example.com"])->assertOk();
    }

    postWebsiteLead(['email' => 'rl4@example.com'])->assertStatus(429);
});

test('the same email from a new IP still counts, whatever its case', function () {
    postWebsiteLead(['email' => 'Pat@X.test'], '198.51.100.1')->assertOk();
    postWebsiteLead(['email' => 'pat@x.test'], '198.51.100.2')->assertOk();
    postWebsiteLead(['email' => 'PAT@x.test'], '198.51.100.3')->assertOk();

    postWebsiteLead(['email' => 'pat@x.TEST'], '198.51.100.4')->assertStatus(429);
});

test('the same phone from a new IP still counts, however it is written', function () {
    postWebsiteLead(['phone' => '(912) 555-0104'], '198.51.100.1')->assertOk();
    postWebsiteLead(['phone' => '912.555.0104'], '198.51.100.2')->assertOk();
    postWebsiteLead(['phone' => '9125550104'], '198.51.100.3')->assertOk();

    postWebsiteLead(['phone' => '912-555-0104'], '198.51.100.4')->assertStatus(429);
});

test('once the company handles one of their messages, they can send another', function () {
    foreach (range(1, 3) as $i) {
        postWebsiteLead(['email' => 'pat@x.test'])->assertOk();
    }
    companyLeads($this->tenant)->first()?->forceFill(['status' => 'contacted'])->save();

    postWebsiteLead(['email' => 'pat@x.test'])->assertOk();
    postWebsiteLead(['email' => 'pat@x.test'])->assertStatus(429);
});

test('the cap is per sender and per company', function () {
    foreach (range(1, 3) as $i) {
        postWebsiteLead(['email' => 'pat@x.test'])->assertOk();
    }

    // Someone else, elsewhere, writing to the same company.
    postWebsiteLead(['email' => 'sam@x.test'], '198.51.100.9')->assertOk();

    // The capped sender writing to a different company.
    Tenant::factory()->create(['slug' => 'other-pools']);
    test()->withServerVariables(['REMOTE_ADDR' => '203.0.113.7'])
        ->postJson('/public/other-pools/leads', ['name' => 'Pat', 'email' => 'pat@x.test'])
        ->assertOk();
});
