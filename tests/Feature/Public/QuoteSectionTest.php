<?php

declare(strict_types=1);

use App\Models\Lead;
use App\Models\ServiceType;
use App\Models\Tenant;
use Inertia\Testing\AssertableInertia as Assert;

test('a quote lead is captured with structured details', function () {
    $tenant = Tenant::factory()->create(['slug' => 'acme']);

    $this->postJson('/public/acme/leads', [
        'name' => 'Jane Homeowner',
        'email' => 'jane@example.test',
        'source' => 'quote',
        'message' => 'Instant-quote request: Weekly maintenance — estimated $120.00.',
        'details' => [
            'service_name' => 'Weekly maintenance',
            'estimate' => '$120.00',
            'pool_type' => 'inground',
            'volume_gallons' => '20000',
        ],
    ])->assertOk()->assertJson(['ok' => true]);

    app()->instance('tenant_id', $tenant->id);
    $lead = Lead::query()->where('source', 'quote')->firstOrFail();

    expect($lead->name)->toBe('Jane Homeowner')
        ->and($lead->details['service_name'])->toBe('Weekly maintenance')
        ->and($lead->details['estimate'])->toBe('$120.00');
});

test('the landing exposes active services for the quote calculator', function () {
    $tenant = Tenant::factory()->create(['slug' => 'acme']);
    app()->instance('tenant_id', $tenant->id);
    ServiceType::factory()->create(['name' => 'Weekly maintenance', 'price' => 99.00, 'is_active' => true]);
    ServiceType::factory()->create(['name' => 'Retired plan', 'price' => 50.00, 'is_active' => false]);
    app()->forgetInstance('tenant_id');

    $this->get('/t/acme')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('public/Landing')
            ->where('live.services', function ($services) {
                $names = collect($services)->pluck('name');

                return $names->contains('Weekly maintenance') && ! $names->contains('Retired plan');
            }));
});
