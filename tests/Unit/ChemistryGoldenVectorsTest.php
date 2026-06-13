<?php

declare(strict_types=1);

use App\Services\ChemistryService;

/**
 * Golden vectors for the chemistry engine's pure math. These values are
 * the parity contract: the legacy engine produced them, this port must
 * produce them, and the field PWA's offline JS port must match them
 * exactly. Change these ONLY for a deliberate, documented formula change.
 */
covers(ChemistryService::class);

beforeEach(function () {
    $this->chem = new ChemistryService;
});

test('LSI golden vector: typical balanced-ish pool', function () {
    // temp 80°F, pH 7.4, TA 100, CH 250, salt/TDS 1000
    $lsi = $this->chem->calculateLSI([
        'temperature' => 80, 'ph' => 7.4, 'alkalinity' => 100,
        'calcium_hardness' => 250, 'salt' => 1000,
    ]);

    expect($lsi)->toBe(-0.16);
});

test('LSI golden vector: warm salt pool trending scale-forming', function () {
    // temp 90°F, pH 7.8, TA 120, CH 400, salt 3200
    $lsi = $this->chem->calculateLSI([
        'temperature' => 90, 'ph' => 7.8, 'alkalinity' => 120,
        'calcium_hardness' => 400, 'salt' => 3200,
    ]);

    expect($lsi)->toBe(0.58);
});

test('LSI defaults vector: an empty reading uses documented fallbacks', function () {
    expect($this->chem->calculateLSI([]))->toBe(-0.16);
});

test('LSI status boundaries are exclusive at ±0.3', function () {
    expect($this->chem->getLSIStatus(-0.31)['status'])->toBe('corrosive')
        ->and($this->chem->getLSIStatus(-0.3)['status'])->toBe('balanced')
        ->and($this->chem->getLSIStatus(0.0)['status'])->toBe('balanced')
        ->and($this->chem->getLSIStatus(0.3)['status'])->toBe('balanced')
        ->and($this->chem->getLSIStatus(0.31)['status'])->toBe('scaling');
});

test('reading analysis classifies low / normal / high against default ranges', function () {
    $analysis = $this->chem->analyzeReading([
        'free_chlorine' => 0.5,   // below 1.0 → low
        'ph' => 7.8,              // above 7.6 → high
        'alkalinity' => 100,      // inside 80–120 → normal
    ]);

    expect($analysis['free_chlorine']['status'])->toBe('low')
        ->and($analysis['ph']['status'])->toBe('high')
        ->and($analysis['alkalinity']['status'])->toBe('normal')
        ->and($analysis)->not->toHaveKey('salt'); // absent params are skipped
});

test('combined chlorine is derived (total − free) and analyzed against its range', function () {
    $analysis = $this->chem->analyzeReading([
        'free_chlorine' => 1.0,
        'total_chlorine' => 3.0, // combined = 2.0 ppm → over the 0.5 max → high
    ]);

    expect($analysis)->toHaveKey('combined_chlorine')
        ->and($analysis['combined_chlorine'])->toMatchArray(['value' => 2.0, 'status' => 'high'])
        ->and($analysis['free_chlorine']['status'])->toBe('normal');
});

test('custom ranges override classification', function () {
    $ranges = ChemistryService::DEFAULT_RANGES;
    $ranges['ph']['max'] = 8.0;

    $analysis = $this->chem->analyzeReading(['ph' => 7.8], $ranges);

    expect($analysis['ph']['status'])->toBe('normal');
});

test('weather alerts: high wind is info, freeze risk is warning', function () {
    $alerts = $this->chem->getWeatherAlerts(['daily' => [
        ['wind_speed_10m_max' => 30, 'temperature_2m_min' => 30],
    ]]);

    expect($alerts)->toHaveCount(2)
        ->and($alerts[0]['severity'])->toBe('info')
        ->and($alerts[1]['severity'])->toBe('warning');
});
