<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\ChemicalReading;
use App\Models\Pool;

/**
 * Chemistry engine — LSI water balance, per-parameter analysis against
 * target ranges, 12-visit trend detection, and volume-scaled dosing
 * recommendations with trend + weather adjustments.
 *
 * Ported from the legacy app (golden-vector parity tests in
 * tests/Unit/ChemistryGoldenVectorsTest.php guard the math). This PHP
 * implementation is authoritative; the field PWA's offline JS port must
 * match these vectors exactly.
 *
 * New-build changes vs legacy:
 *  - Per-pool target overrides via pools.custom_target_ranges (merged
 *    over DEFAULT_RANGES by targetsFor()).
 *  - Dosing reads pools.volume_gallons (legacy read a nonexistent
 *    `volume` attribute, silently always dosing for 10k gallons).
 *  - Trend history maps the `temperature` parameter to the
 *    water_temperature column (legacy plucked a nonexistent column).
 */
class ChemistryService
{
    /**
     * Global ideal ranges. Per-pool overrides merge on top (see targetsFor()).
     *
     * @var array<string, array{min: float, max: float, unit: string, label: string}>
     */
    public const DEFAULT_RANGES = [
        'free_chlorine' => ['min' => 1.0, 'max' => 3.0, 'unit' => 'ppm', 'label' => 'Free Chlorine'],
        'combined_chlorine' => ['min' => 0.0, 'max' => 0.5, 'unit' => 'ppm', 'label' => 'Combined Chlorine'],
        'ph' => ['min' => 7.2, 'max' => 7.6, 'unit' => '', 'label' => 'pH'],
        'alkalinity' => ['min' => 80.0, 'max' => 120.0, 'unit' => 'ppm', 'label' => 'Total Alkalinity'],
        'calcium_hardness' => ['min' => 200.0, 'max' => 400.0, 'unit' => 'ppm', 'label' => 'Calcium Hardness'],
        'cyanuric_acid' => ['min' => 30.0, 'max' => 80.0, 'unit' => 'ppm', 'label' => 'Cyanuric Acid (CYA)'],
        'salt' => ['min' => 2700.0, 'max' => 3400.0, 'unit' => 'ppm', 'label' => 'Salt'],
        'temperature' => ['min' => 78.0, 'max' => 84.0, 'unit' => 'F', 'label' => 'Temperature'],
    ];

    /** Reading-array parameter → chemical_readings column (where they differ). */
    private const COLUMN_MAP = ['temperature' => 'water_temperature'];

    /**
     * Effective target ranges for a pool: DEFAULT_RANGES with any
     * per-pool custom min/max overrides merged in.
     *
     * @return array<string, array{min: float, max: float, unit: string, label: string}>
     */
    public function targetsFor(Pool $pool): array
    {
        $ranges = self::DEFAULT_RANGES;

        foreach ($pool->custom_target_ranges ?? [] as $param => $override) {
            if (! isset($ranges[$param])) {
                continue;
            }
            // JSON round-trips a whole-number target (8.0) back as int — coerce to float.
            $ranges[$param]['min'] = isset($override['min']) ? (float) $override['min'] : $ranges[$param]['min'];
            $ranges[$param]['max'] = isset($override['max']) ? (float) $override['max'] : $ranges[$param]['max'];
        }

        return $ranges;
    }

    /**
     * Langelier Saturation Index for a reading.
     *
     * @param  array<string, float|int|null>  $reading
     */
    public function calculateLSI(array $reading): float
    {
        $temp = (float) ($reading['temperature'] ?? 80);
        $ph = (float) ($reading['ph'] ?? 7.4);
        $alkalinity = (float) ($reading['alkalinity'] ?? 100);
        $calcium = (float) ($reading['calcium_hardness'] ?? 250);
        $tds = (float) ($reading['salt'] ?? 1000); // salt doubles as TDS proxy

        $tempC = ($temp - 32) * 5 / 9;
        $a = (log10($tds) - 1) / 10;
        $b = -13.12 * log10($tempC + 273) + 34.55;
        $c = log10(max($calcium, 1)) - 0.4;
        $d = log10(max($alkalinity, 1));

        $phs = (9.3 + $a + $b) - ($c + $d);

        return round($ph - $phs, 2);
    }

    /**
     * Classify an LSI value as corrosive / balanced / scaling.
     *
     * @return array{status: string, color: string, label: string, description: string}
     */
    public function getLSIStatus(float $lsi): array
    {
        if ($lsi < -0.3) {
            return ['status' => 'corrosive', 'color' => 'red', 'label' => 'Corrosive', 'description' => 'Water is aggressive and may damage surfaces/equipment.'];
        }
        if ($lsi > 0.3) {
            return ['status' => 'scaling', 'color' => 'amber', 'label' => 'Scale-forming', 'description' => 'Water may deposit calcium scale on surfaces.'];
        }

        return ['status' => 'balanced', 'color' => 'green', 'label' => 'Balanced', 'description' => 'Water chemistry is in ideal balance.'];
    }

    /**
     * Analyze a reading against target ranges (low / normal / high per parameter).
     *
     * @param  array<string, float|int|null>  $reading
     * @param  array<string, array{min: float, max: float, unit: string, label: string}>|null  $ranges
     * @return array<string, array{value: float|int, status: string, label: string, unit: string, min: float, max: float}>
     */
    public function analyzeReading(array $reading, ?array $ranges = null): array
    {
        $ranges ??= self::DEFAULT_RANGES;
        $reading = $this->withCombinedChlorine($reading);
        $analysis = [];

        foreach ($ranges as $param => $range) {
            $value = $reading[$param] ?? null;
            if ($value === null) {
                continue;
            }

            $status = 'normal';
            if ($value < $range['min']) {
                $status = 'low';
            }
            if ($value > $range['max']) {
                $status = 'high';
            }

            $analysis[$param] = [
                'value' => $value,
                'status' => $status,
                'label' => $range['label'],
                'unit' => $range['unit'],
                'min' => $range['min'],
                'max' => $range['max'],
            ];
        }

        return $analysis;
    }

    /**
     * Combined chlorine (chloramines) is derived, not stored: total − free.
     * Inject it so the combined_chlorine target range is actually evaluated.
     *
     * @param  array<string, float|int|null>  $reading
     * @return array<string, float|int|null>
     */
    private function withCombinedChlorine(array $reading): array
    {
        $total = $reading['total_chlorine'] ?? null;
        $free = $reading['free_chlorine'] ?? null;
        if ($total !== null && $free !== null && ! isset($reading['combined_chlorine'])) {
            $reading['combined_chlorine'] = max(0.0, (float) $total - (float) $free);
        }

        return $reading;
    }

    /**
     * Trend analysis over the pool's last 12 readings: direction,
     * average, and chronic out-of-range detection (>= 3 of last 12).
     *
     * @param  array<string, float|int|null>  $currentReading
     * @param  array<string, array{min: float, max: float, unit: string, label: string}>|null  $ranges
     * @return array{has_history: bool, parameters: array<string, array{direction: string, average: float, readings_count: int, out_of_range_count: int, is_chronic: bool}>}
     */
    public function analyzeTrends(int $poolId, array $currentReading, ?array $ranges = null): array
    {
        $ranges ??= self::DEFAULT_RANGES;
        $currentReading = $this->withCombinedChlorine($currentReading);

        $history = ChemicalReading::query()
            ->whereHas('serviceVisit', fn ($q) => $q->where('pool_id', $poolId))
            ->orderByDesc('created_at')
            ->limit(12)
            ->get();

        if ($history->isEmpty()) {
            return ['has_history' => false, 'parameters' => []];
        }

        $trends = [];

        foreach ($ranges as $param => $range) {
            $currentValue = $currentReading[$param] ?? null;
            if ($currentValue === null) {
                continue;
            }

            // combined_chlorine is derived (total − free), so compute it per
            // historical reading rather than plucking a non-existent column.
            $historicalValues = $param === 'combined_chlorine'
                ? $history->map(function (ChemicalReading $r): ?float {
                    $total = $r->getAttribute('total_chlorine');
                    $free = $r->getAttribute('free_chlorine');

                    return $total !== null && $free !== null ? max(0.0, (float) $total - (float) $free) : null;
                })->filter(fn ($v) => $v !== null)->values()
                : $history->pluck(self::COLUMN_MAP[$param] ?? $param)->filter(fn ($v) => $v !== null)->values();
            if ($historicalValues->isEmpty()) {
                continue;
            }

            $avg = (float) $historicalValues->avg();
            $outOfRangeCount = $historicalValues->filter(fn ($v) => $v < $range['min'] || $v > $range['max'])->count();

            $direction = match (true) {
                $currentValue > $avg * 1.05 => 'rising',
                $currentValue < $avg * 0.95 => 'falling',
                default => 'stable',
            };

            $trends[$param] = [
                'direction' => $direction,
                'average' => round($avg, 1),
                'readings_count' => $historicalValues->count(),
                'out_of_range_count' => $outOfRangeCount,
                'is_chronic' => $outOfRangeCount >= 3,
            ];
        }

        return ['has_history' => true, 'parameters' => $trends];
    }

    /**
     * Volume-scaled dosing recommendations for out-of-range parameters,
     * adjusted for trends (chronic / treatment-didn't-help) and weather
     * (rain dilution, heat/UV burn-off). A required drain/refill
     * suppresses all chemical dosing.
     *
     * @param  array<string, float|int|null>  $reading
     * @param  array<string, array{value: float|int, status: string, label: string, unit: string, min: float, max: float}>  $analysis
     * @param  array{has_history: bool, parameters: array<string, mixed>}  $trends
     * @param  array{daily?: list<array<string, float|int|null>>}|null  $weather
     * @return list<array<string, mixed>>
     */
    public function generateRecommendations(
        array $reading,
        Pool $pool,
        array $analysis,
        array $trends,
        ?array $weather = null,
    ): array {
        $recommendations = [];
        $volumeGallons = $pool->volume_gallons ?? 10000;
        $sanitizer = $pool->sanitizer_type ?? 'chlorine';

        foreach ($analysis as $param => $data) {
            if ($data['status'] === 'normal') {
                continue;
            }

            $rec = $this->getBaseDosage($param, $data, $volumeGallons, $sanitizer);

            // High calcium / CYA can't be reduced chemically — surface a
            // drain/refill recommendation instead of silently skipping.
            if (! $rec) {
                if (($param === 'calcium_hardness' && $data['value'] > $data['max'])
                    || ($param === 'cyanuric_acid' && $data['value'] > $data['max'])) {
                    $rec = [
                        'parameter' => $data['label'],
                        'chemical' => 'Partial Drain & Refill',
                        'amount' => 0.0,
                        'unit' => '',
                        'urgency' => 'high',
                        'action' => 'drain_refill',
                        'notes' => [],
                    ];
                } else {
                    continue;
                }
            }

            $originalAmount = $rec['amount'];
            $adjustments = [];

            $trendData = $trends['parameters'][$param] ?? null;
            if ($trendData) {
                if ($trendData['is_chronic']) {
                    $rec['urgency'] = 'high';
                    $rec['notes'][] = "{$data['label']} has been out of range in {$trendData['out_of_range_count']} of last {$trendData['readings_count']} visits.";
                }
                // Same treatment applied last time didn't move the level — bump 15%.
                if ($trendData['direction'] === 'stable' && $data['status'] !== 'normal') {
                    $rec['amount'] *= 1.15;
                    $adjustments[] = '+15% — previous treatment did not improve level';
                }
            }

            if ($weather) {
                foreach ($this->getWeatherAdjustments($param, $data, $weather, $reading) as $adj) {
                    $rec['amount'] *= (1 + $adj['factor']);
                    $adjustments[] = $adj['reason'];
                }
            }

            if ($param === 'calcium_hardness' && $data['value'] > $data['max']) {
                $rec['action'] = 'drain_refill';
                $rec['notes'][] = 'Calcium hardness is too high to treat chemically. Partial drain and refill recommended.';
                $rec['notes'][] = 'Re-test water chemistry after the refill before adding any chemicals.';
            }
            if ($param === 'cyanuric_acid' && $data['value'] > $data['max']) {
                $rec['action'] = 'drain_refill';
                $rec['notes'][] = 'CYA is too high to reduce chemically. Partial drain and refill recommended.';
                $rec['notes'][] = 'Re-test water chemistry after the refill before adding any chemicals.';
            }

            $rec['original_amount'] = $originalAmount;
            $rec['amount'] = round($rec['amount'], 1);
            $rec['adjustments'] = $adjustments;
            $rec['was_adjusted'] = $adjustments !== [];

            $recommendations[] = $rec;
        }

        // A drain/refill resets chemistry — dosing beforehand wastes product.
        $hasDrainRefill = collect($recommendations)->contains(fn ($r) => ($r['action'] ?? null) === 'drain_refill');
        if ($hasDrainRefill) {
            $recommendations = array_values(array_filter(
                $recommendations,
                fn ($r) => ($r['action'] ?? null) === 'drain_refill',
            ));
            foreach ($recommendations as &$rec) {
                $rec['notes'][] = 'Other chemistry adjustments are skipped — they may not be needed after the refill.';
            }
            unset($rec);
        }

        // Highest urgency first. (The legacy comparator had its operands
        // reversed and actually sorted low-urgency first, contradicting its
        // own "Sort by urgency" intent — fixed here.)
        $rank = ['high' => 0, 'medium' => 1, 'normal' => 2];
        usort($recommendations, fn ($a, $b) => ($rank[$a['urgency'] ?? 'normal'] ?? 2) <=> ($rank[$b['urgency'] ?? 'normal'] ?? 2));

        return $recommendations;
    }

    /**
     * Weather-driven dosage multipliers for a low parameter.
     *
     * @param  array{value: float|int, status: string, label: string, unit: string, min: float, max: float}  $data
     * @param  array{daily?: list<array<string, float|int|null>>}  $weather
     * @param  array<string, float|int|null>  $reading
     * @return list<array{factor: float, reason: string}>
     */
    protected function getWeatherAdjustments(string $param, array $data, array $weather, array $reading): array
    {
        $adjustments = [];
        $daily = collect($weather['daily'] ?? []);
        $maxRainProb = $daily->max('precipitation_probability_max') ?? 0;
        $maxTemp = $daily->max('temperature_2m_max') ?? 80;
        $maxUV = $daily->max('uv_index_max') ?? 5;

        if ($param === 'free_chlorine' && $data['status'] === 'low') {
            if ($maxRainProb > 50) {
                $factor = $maxRainProb > 80 ? 0.40 : 0.20;
                $adjustments[] = ['factor' => $factor, 'reason' => "+{$this->pct($factor)} — rain expected, will dilute chlorine"];
            }
            if ($maxTemp > 90 || $maxUV > 7) {
                $factor = $maxTemp > 100 ? 0.35 : 0.20;
                $adjustments[] = ['factor' => $factor, 'reason' => "+{$this->pct($factor)} — high heat/UV burns chlorine faster"];
            }
            $cya = $reading['cyanuric_acid'] ?? 40;
            if ($cya < 30 && $maxUV > 5) {
                $adjustments[] = ['factor' => 0.15, 'reason' => '+15% — low CYA with sun exposure (no UV protection)'];
            }
        }

        if ($param === 'alkalinity' && $data['status'] === 'low' && $maxRainProb > 50) {
            $factor = $maxRainProb > 80 ? 0.25 : 0.15;
            $adjustments[] = ['factor' => $factor, 'reason' => "+{$this->pct($factor)} — rain will further dilute alkalinity"];
        }

        return $adjustments;
    }

    /**
     * Operational weather alerts (not dosage-related).
     *
     * @param  array{daily?: list<array<string, float|int|null>>}  $weather
     * @return list<array{severity: string, message: string}>
     */
    public function getWeatherAlerts(array $weather): array
    {
        $alerts = [];
        $daily = collect($weather['daily'] ?? []);
        $maxWind = $daily->max('wind_speed_10m_max') ?? 0;
        $minTemp = $daily->min('temperature_2m_min') ?? 50;

        if ($maxWind > 25) {
            $alerts[] = ['severity' => 'info', 'message' => 'High winds expected — anticipate extra debris in pools.'];
        }
        if ($minTemp <= 32) {
            $alerts[] = ['severity' => 'warning', 'message' => 'Freeze risk — advise running pumps continuously to prevent pipe damage.'];
        }

        return $alerts;
    }

    /**
     * Base dosage for an out-of-range parameter, normalized to a
     * 10,000-gallon pool and scaled by actual volume.
     *
     * @param  array{value: float|int, status: string, label: string, unit: string, min: float, max: float}  $data
     * @return array<string, mixed>|null
     */
    protected function getBaseDosage(string $param, array $data, int $volumeGallons, string $sanitizer): ?array
    {
        $factor = $volumeGallons / 10000;

        return match ($param) {
            'free_chlorine' => $data['status'] === 'low' ? [
                'parameter' => $data['label'],
                'chemical' => $sanitizer === 'salt' ? 'Run chlorine generator' : 'Granular Chlorine (Cal-Hypo)',
                'amount' => round(2.0 * $factor, 1),
                'unit' => $sanitizer === 'salt' ? 'hours extra runtime' : 'oz',
                'urgency' => 'high',
                'notes' => [],
            ] : null,
            'ph' => [
                'parameter' => $data['label'],
                'chemical' => $data['status'] === 'high' ? 'Muriatic Acid' : 'Soda Ash',
                'amount' => round(($data['status'] === 'high' ? 8.0 : 6.0) * $factor, 1),
                'unit' => 'oz',
                'urgency' => 'medium',
                'notes' => [],
            ],
            'alkalinity' => $data['status'] === 'low' ? [
                'parameter' => $data['label'],
                'chemical' => 'Sodium Bicarbonate (Baking Soda)',
                'amount' => round(1.5 * $factor, 1),
                'unit' => 'lbs',
                'urgency' => 'medium',
                'notes' => [],
            ] : [
                'parameter' => $data['label'],
                'chemical' => 'Muriatic Acid',
                'amount' => round(12.0 * $factor, 1),
                'unit' => 'oz',
                'urgency' => 'medium',
                'notes' => ['Add in small increments with pump running. Retest after 4 hours.'],
            ],
            'calcium_hardness' => $data['status'] === 'low' ? [
                'parameter' => $data['label'],
                'chemical' => 'Calcium Chloride',
                'amount' => round(1.25 * $factor, 1),
                'unit' => 'lbs',
                'urgency' => 'low',
                'notes' => ['Dissolve in bucket of water before adding to pool.'],
            ] : null, // high calcium → drain/refill path
            'cyanuric_acid' => $data['status'] === 'low' ? [
                'parameter' => $data['label'],
                'chemical' => 'Cyanuric Acid (Stabilizer)',
                'amount' => round(1.0 * $factor, 1),
                'unit' => 'lbs',
                'urgency' => 'medium',
                'notes' => ['Add through skimmer with pump running. Takes 3-5 days to dissolve fully.'],
            ] : null, // high CYA → drain/refill path
            'salt' => $data['status'] === 'low' ? [
                'parameter' => $data['label'],
                'chemical' => 'Pool Salt',
                'amount' => round(40.0 * $factor, 1),
                'unit' => 'lbs',
                'urgency' => 'low',
                'notes' => ['Broadcast around pool perimeter. Takes 24 hours to circulate fully.'],
            ] : null,
            default => null,
        };
    }

    /**
     * Full analysis for a reading at a pool: LSI, per-parameter status
     * against the pool's effective targets, trends, recommendations,
     * and any weather alerts.
     *
     * @param  array<string, float|int|null>  $reading
     * @param  array{daily?: list<array<string, float|int|null>>}|null  $weather
     * @return array<string, mixed>
     */
    public function fullAnalysis(array $reading, Pool $pool, ?array $weather = null): array
    {
        $ranges = $this->targetsFor($pool);

        $lsi = $this->calculateLSI($reading);
        $lsiStatus = $this->getLSIStatus($lsi);
        $paramAnalysis = $this->analyzeReading($reading, $ranges);
        $trends = $this->analyzeTrends($pool->id, $reading, $ranges);
        $recommendations = $this->generateRecommendations($reading, $pool, $paramAnalysis, $trends, $weather);
        $weatherAlerts = $weather ? $this->getWeatherAlerts($weather) : [];

        return [
            'lsi' => ['value' => $lsi, ...$lsiStatus],
            'parameters' => $paramAnalysis,
            'trends' => $trends,
            'recommendations' => $recommendations,
            'weather_alerts' => $weatherAlerts,
        ];
    }

    protected function pct(float $factor): string
    {
        return round($factor * 100).'%';
    }
}
