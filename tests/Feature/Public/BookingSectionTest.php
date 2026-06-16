<?php

declare(strict_types=1);

use App\Models\Lead;
use App\Models\Tenant;

test('a booking request is captured as a lead with date + window details', function () {
    $tenant = Tenant::factory()->create(['slug' => 'acme']);

    $this->postJson('/public/acme/leads', [
        'name' => 'Sam Owner',
        'phone' => '555-0100',
        'source' => 'booking',
        'message' => 'Booking request: Weekly on 2026-06-20 (Morning).',
        'details' => [
            'service_name' => 'Weekly',
            'preferred_date' => '2026-06-20',
            'time_window' => 'morning',
        ],
    ])->assertOk()->assertJson(['ok' => true]);

    app()->instance('tenant_id', $tenant->id);
    $lead = Lead::query()->where('source', 'booking')->firstOrFail();

    expect($lead->details['preferred_date'])->toBe('2026-06-20')
        ->and($lead->details['time_window'])->toBe('morning');
});
