<?php

declare(strict_types=1);

use App\Models\Pool;
use App\Services\ChemistryService;

/**
 * Characterization tests for generateRecommendations(): they pin the exact
 * dosing / trend / weather / drain-refill / sort behavior so the method can be
 * decomposed without changing its output. These are a behavior contract —
 * change them ONLY for a deliberate, documented behavior change.
 */
covers(ChemistryService::class);

beforeEach(function () {
    $this->chem = new ChemistryService;
    $this->pool = new Pool;
    $this->pool->volume_gallons = 10000; // factor 1.0
    $this->pool->sanitizer_type = 'chlorine';
});

/**
 * @param  array<string, float|int|null>  $reading
 * @param  array{has_history: bool, parameters: array<string, mixed>}  $trends
 * @return list<array<string, mixed>>
 */
function recs(ChemistryService $chem, Pool $pool, array $reading, array $trends = ['has_history' => false, 'parameters' => []], ?array $weather = null): array
{
    return $chem->generateRecommendations($reading, $pool, $chem->analyzeReading($reading), $trends, $weather);
}

test('low free chlorine yields the base chlorine dosage at high urgency', function () {
    expect(recs($this->chem, $this->pool, ['free_chlorine' => 0.5]))->toEqual([[
        'parameter' => 'Free Chlorine',
        'chemical' => 'Granular Chlorine (Cal-Hypo)',
        'amount' => 2.0,
        'unit' => 'oz',
        'urgency' => 'high',
        'notes' => [],
        'original_amount' => 2.0,
        'adjustments' => [],
        'was_adjusted' => false,
    ]]);
});

test('high calcium becomes a drain-refill that suppresses other dosing', function () {
    expect(recs($this->chem, $this->pool, ['calcium_hardness' => 500, 'ph' => 7.8]))->toEqual([[
        'parameter' => 'Calcium Hardness',
        'chemical' => 'Partial Drain & Refill',
        'amount' => 0.0,
        'unit' => '',
        'urgency' => 'high',
        'action' => 'drain_refill',
        'notes' => [
            'Calcium hardness is too high to treat chemically. Partial drain and refill recommended.',
            'Re-test water chemistry after the refill before adding any chemicals.',
            'Other chemistry adjustments are skipped — they may not be needed after the refill.',
        ],
        'original_amount' => 0.0,
        'adjustments' => [],
        'was_adjusted' => false,
    ]]);
});

test('a chronic, stable trend raises urgency and adds 15%', function () {
    $trends = ['has_history' => true, 'parameters' => [
        'alkalinity' => ['is_chronic' => true, 'out_of_range_count' => 3, 'readings_count' => 4, 'direction' => 'stable'],
    ]];

    expect(recs($this->chem, $this->pool, ['alkalinity' => 60], $trends))->toEqual([[
        'parameter' => 'Total Alkalinity',
        'chemical' => 'Sodium Bicarbonate (Baking Soda)',
        'amount' => 1.7,
        'unit' => 'lbs',
        'urgency' => 'high',
        'notes' => ['Total Alkalinity has been out of range in 3 of last 4 visits.'],
        'original_amount' => 1.5,
        'adjustments' => ['+15% — previous treatment did not improve level'],
        'was_adjusted' => true,
    ]]);
});

test('recommendations are sorted highest-urgency first', function () {
    // free_chlorine low (high) · ph high (medium) · calcium low (low)
    $result = recs($this->chem, $this->pool, ['free_chlorine' => 0.5, 'ph' => 7.9, 'calcium_hardness' => 150]);

    expect(array_column($result, 'urgency'))->toBe(['high', 'medium', 'low'])
        ->and(array_column($result, 'parameter'))->toBe(['Free Chlorine', 'pH', 'Calcium Hardness']);
});

test('heavy-rain weather multiplies the chlorine dose and flags the adjustment', function () {
    $weather = ['daily' => [['precipitation_probability_max' => 90, 'temperature_2m_max' => 80, 'uv_index_max' => 5]]];
    $result = recs($this->chem, $this->pool, ['free_chlorine' => 0.5, 'cyanuric_acid' => 50], weather: $weather);

    expect($result)->toHaveCount(1)
        ->and($result[0]['original_amount'])->toBe(2.0)
        ->and($result[0]['amount'])->toBe(2.8)
        ->and($result[0]['was_adjusted'])->toBeTrue()
        ->and($result[0]['adjustments'])->toHaveCount(1);
});
