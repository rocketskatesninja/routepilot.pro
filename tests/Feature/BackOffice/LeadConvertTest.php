<?php

declare(strict_types=1);

use App\Models\Customer;
use App\Models\Lead;
use App\Models\ServiceRequest;
use App\Models\ServiceType;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Carbon;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    Carbon::setTestNow('2026-10-15 14:00:00');
    $this->tenant = Tenant::factory()->create(['slug' => 'acme-pools']);
    app()->instance('tenant_id', $this->tenant->id);
    $this->admin = User::factory()->for($this->tenant)->create();
    $this->service = ServiceType::factory()->for($this->tenant)->create(['name' => 'Weekly Pool Service', 'requestable' => true]);
});

/** @param  array<string, mixed>  $attrs */
function websiteLead(array $attrs = []): Lead
{
    $lead = Lead::create(array_merge([
        'name' => 'Pat Homeowner',
        'email' => 'pat@example.test',
        'phone' => '555-0100',
        'source' => 'booking',
        'message' => 'Booking request: Weekly Pool Service on 2026-10-22 (Anytime).',
        'details' => ['service_name' => 'Weekly Pool Service', 'preferred_date' => '2026-10-22', 'time_window' => 'anytime'],
    ], $attrs));

    return $lead;
}

test('a website lead records which service was picked', function () {
    $this->postJson('/public/acme-pools/leads', ['name' => 'Pat', 'email' => 'pat@x.test', 'source' => 'quote', 'service_type_id' => $this->service->id])
        ->assertOk();

    expect(Lead::query()->sole()->getAttribute('service_type_id'))->toBe($this->service->id);
});

test('a website lead can only pick a service the company offers', function () {
    $internal = ServiceType::factory()->for($this->tenant)->create(['requestable' => false]);
    $foreign = ServiceType::factory()->for(Tenant::factory())->create(['requestable' => true]);

    foreach ([$internal, $foreign] as $service) {
        $this->postJson('/public/acme-pools/leads', ['name' => 'Pat', 'email' => 'pat@x.test', 'service_type_id' => $service->id])
            ->assertStatus(422);
    }
});

test('the inbox lists website leads and opens one ready to convert', function () {
    $lead = websiteLead(['service_type_id' => $this->service->id]);

    $this->actingAs($this->admin)
        ->get("/inbox?tab=leads&selected={$lead->id}")
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('inbox/Index')
            ->where('tab', 'leads')
            ->has('rows.data', 1)
            ->where('counts.leads', 1)
            ->where('selected.service.name', 'Weekly Pool Service')
            ->where('selected.convert.first_name', 'Pat')
            ->where('selected.convert.last_name', 'Homeowner'));
});

test('converting a lead creates the customer and pool, and their service request lands in the inbox', function () {
    $lead = websiteLead(['service_type_id' => $this->service->id]);

    $response = $this->actingAs($this->admin)->post("/leads/{$lead->id}/convert", [
        'first_name' => 'Pat',
        'last_name' => 'Homeowner',
        'email' => 'pat@example.test',
        'phone' => '555-0100',
        'address_line1' => '12 Palm Way',
        'city' => 'Tampa',
        'state' => 'FL',
        'zip' => '33601',
        'pool_name' => 'Backyard pool',
        'pool_type' => 'inground',
        'pool_volume' => 15000,
    ])->assertSessionHasNoErrors();

    $customer = Customer::query()->where('email', 'pat@example.test')->sole();
    $pool = $customer->pools()->sole();
    $request = ServiceRequest::query()->where('customer_id', $customer->id)->sole();

    expect($pool->getAttribute('name'))->toBe('Backyard pool')
        ->and($lead->fresh()?->status)->toBe('converted')
        ->and($lead->fresh()?->getAttribute('customer_id'))->toBe($customer->id)
        ->and($request->status)->toBe('pending')
        ->and($request->getAttribute('service_type_id'))->toBe($this->service->id)
        ->and($request->getAttribute('pool_id'))->toBe($pool->id)
        ->and($request->preferred_date?->toDateString())->toBe('2026-10-22');
    $response->assertRedirect("/inbox?selected={$request->id}");
});

test('converting a lead that picked no service just creates the customer', function () {
    $lead = websiteLead(['source' => 'contact', 'details' => null]);

    $this->actingAs($this->admin)
        ->post("/leads/{$lead->id}/convert", ['first_name' => 'Pat', 'pool_name' => 'Pool'])
        ->assertSessionHasNoErrors();

    expect(Customer::query()->where('first_name', 'Pat')->count())->toBe(1)
        ->and(ServiceRequest::query()->count())->toBe(0)
        ->and($lead->fresh()?->status)->toBe('converted');
});

test('a lead can only be converted once', function () {
    $lead = websiteLead();

    $this->actingAs($this->admin)->post("/leads/{$lead->id}/convert", ['first_name' => 'Pat', 'pool_name' => 'Pool'])->assertSessionHasNoErrors();
    $this->actingAs($this->admin)->post("/leads/{$lead->id}/convert", ['first_name' => 'Pat', 'pool_name' => 'Pool'])->assertSessionHas('error');

    expect(Customer::query()->count())->toBe(1);
});

test('agents cannot convert leads', function () {
    $agent = User::factory()->agent()->for($this->tenant)->create();
    $lead = websiteLead();

    $this->actingAs($agent)->post("/leads/{$lead->id}/convert", ['first_name' => 'Pat', 'pool_name' => 'Pool'])->assertForbidden();
});
