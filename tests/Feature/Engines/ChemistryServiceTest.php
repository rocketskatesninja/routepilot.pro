<?php

declare(strict_types=1);

use App\Models\Customer;
use App\Models\Pool;
use App\Models\ServiceVisit;
use App\Models\Tenant;
use App\Models\User;
use App\Services\ChemistryService;

/**
 * Chemistry engine against the database: volume-scaled dosing, weather
 * and trend adjustments, drain/refill suppression, and per-pool target
 * overrides. Dosage amounts are golden parity values.
 */
beforeEach(function () {
    $this->tenant = Tenant::factory()->create();
    app()->instance('tenant_id', $this->tenant->id);

    $this->agent = User::factory()->create();
    $this->customer = Customer::factory()->for($this->tenant)->create();
    $this->chem = new ChemistryService;
});

function makePool(Tenant $tenant, Customer $customer, array $attrs = []): Pool
{
    return Pool::factory()->for($tenant)->for($customer)->create($attrs);
}

test('dosing scales with pool volume (15k gal golden vector)', function () {
    $pool = makePool($this->tenant, $this->customer, ['volume_gallons' => 15000, 'sanitizer_type' => 'chlorine']);

    $result = $this->chem->fullAnalysis(['free_chlorine' => 0.5, 'ph' => 7.8], $pool);
    $recs = collect($result['recommendations']);

    $chlorine = $recs->firstWhere('parameter', 'Free Chlorine');
    $ph = $recs->firstWhere('parameter', 'pH');

    // 2.0 oz/10k * 1.5 and 8.0 oz/10k * 1.5 — the legacy app always dosed
    // for 10k gallons (read a nonexistent column); the port fixes that.
    expect($chlorine['chemical'])->toBe('Granular Chlorine (Cal-Hypo)')
        ->and($chlorine['amount'])->toBe(3.0)
        ->and($chlorine['unit'])->toBe('oz')
        ->and($chlorine['urgency'])->toBe('high')
        ->and($ph['chemical'])->toBe('Muriatic Acid')
        ->and($ph['amount'])->toBe(12.0)
        // urgency sort puts the high-urgency chlorine rec first
        ->and($result['recommendations'][0]['parameter'])->toBe('Free Chlorine');
});

test('salt pools get generator runtime instead of granular chlorine', function () {
    $pool = makePool($this->tenant, $this->customer, ['volume_gallons' => 10000, 'sanitizer_type' => 'salt']);

    $result = $this->chem->fullAnalysis(['free_chlorine' => 0.5], $pool);

    expect($result['recommendations'][0]['chemical'])->toBe('Run chlorine generator')
        ->and($result['recommendations'][0]['amount'])->toBe(2.0)
        ->and($result['recommendations'][0]['unit'])->toBe('hours extra runtime');
});

test('a required drain/refill suppresses all chemical dosing', function () {
    $pool = makePool($this->tenant, $this->customer, ['volume_gallons' => 10000]);

    // CYA 90 (> 80 max, chemically irreducible) alongside low chlorine.
    $result = $this->chem->fullAnalysis(['cyanuric_acid' => 90, 'free_chlorine' => 0.5], $pool);
    $recs = $result['recommendations'];

    expect($recs)->toHaveCount(1)
        ->and($recs[0]['action'])->toBe('drain_refill')
        ->and($recs[0]['chemical'])->toBe('Partial Drain & Refill')
        ->and($recs[0]['urgency'])->toBe('high')
        ->and(implode(' ', $recs[0]['notes']))->toContain('skipped');
});

test('rain forecast bumps a low-chlorine dose 40% (golden vector)', function () {
    $pool = makePool($this->tenant, $this->customer, ['volume_gallons' => 10000]);
    $weather = ['daily' => [[
        'precipitation_probability_max' => 85,
        'temperature_2m_max' => 75,
        'uv_index_max' => 4,
        'wind_speed_10m_max' => 5,
        'temperature_2m_min' => 60,
    ]]];

    $result = $this->chem->fullAnalysis(['free_chlorine' => 0.5, 'cyanuric_acid' => 40], $pool, $weather);
    $rec = $result['recommendations'][0];

    expect($rec['amount'])->toBe(2.8) // 2.0 * 1.40
        ->and($rec['original_amount'])->toBe(2.0)
        ->and($rec['was_adjusted'])->toBeTrue()
        ->and($rec['adjustments'][0])->toContain('rain');
});

test('a chronic, unresponsive parameter escalates urgency and bumps the dose 15%', function () {
    $pool = makePool($this->tenant, $this->customer, ['volume_gallons' => 10000]);

    // Three prior visits all with pH 7.8 — high, and unmoved by treatment.
    ServiceVisit::factory()->count(3)->for($this->tenant)->for($pool)->create(['agent_id' => $this->agent->id])
        ->each(fn ($visit) => $visit->chemicalReading()->create([
            'tenant_id' => $this->tenant->id,
            'ph' => 7.8, 'free_chlorine' => 2.0, 'alkalinity' => 100, 'calcium_hardness' => 250,
        ]));

    $result = $this->chem->fullAnalysis(['ph' => 7.8], $pool);
    $rec = collect($result['recommendations'])->firstWhere('parameter', 'pH');

    expect($result['trends']['has_history'])->toBeTrue()
        ->and($result['trends']['parameters']['ph']['is_chronic'])->toBeTrue()
        ->and($result['trends']['parameters']['ph']['direction'])->toBe('stable')
        ->and($rec['urgency'])->toBe('high') // escalated from medium
        ->and($rec['amount'])->toBe(9.2)     // 8.0 * 1.15
        ->and($rec['was_adjusted'])->toBeTrue();
});

test('per-pool custom target ranges change the verdict', function () {
    $pool = makePool($this->tenant, $this->customer, [
        'volume_gallons' => 10000,
        'custom_target_ranges' => ['ph' => ['max' => 8.0]],
    ]);

    $result = $this->chem->fullAnalysis(['ph' => 7.8], $pool);

    expect($result['parameters']['ph']['status'])->toBe('normal')
        ->and($result['parameters']['ph']['max'])->toBe(8.0)
        ->and($result['recommendations'])->toBeEmpty();
});
