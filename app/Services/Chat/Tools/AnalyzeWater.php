<?php

declare(strict_types=1);

namespace App\Services\Chat\Tools;

use App\Models\ChemicalReading;
use App\Models\Pool;
use App\Models\User;
use App\Services\Chat\AssistantTool;
use App\Services\ChemistryService;

/**
 * Everyone: what a water test means and what to add, from the same dose engine
 * the field app uses (ChemistryService, scaled to the pool's volume and its own
 * targets). Works on a pool the person can see, or on numbers they give.
 */
class AnalyzeWater extends AssistantTool
{
    /** The readings the engine understands, and what the model may call them. */
    private const READINGS = [
        'free_chlorine' => 'Free chlorine (ppm)',
        'total_chlorine' => 'Total chlorine (ppm)',
        'ph' => 'pH',
        'alkalinity' => 'Total alkalinity (ppm)',
        'calcium_hardness' => 'Calcium hardness (ppm)',
        'cyanuric_acid' => 'Cyanuric acid / stabilizer (ppm)',
        'salt' => 'Salt (ppm)',
        'temperature' => 'Water temperature (°F)',
    ];

    public function name(): string
    {
        return 'analyze_water';
    }

    public function description(): string
    {
        return 'Work out what a water test means and exactly what to add, with doses scaled to the pool. Use it for any '
            .'"what should I add?" question. Give a pool (its latest test is used unless you pass readings), or give '
            .'readings plus the volume in gallons.';
    }

    /** @return array<string, mixed> */
    public function parameters(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'pool_id' => ['type' => 'integer', 'description' => 'The pool\'s id, when known (optional).'],
                'customer_name' => ['type' => 'string', 'description' => 'Find the pool by its customer\'s name (optional).'],
                'pool_name' => ['type' => 'string', 'description' => 'The pool\'s name (optional).'],
                'readings' => [
                    'type' => 'object',
                    'description' => 'The test results, if given. Leave out to use the pool\'s latest test.',
                    'properties' => array_map(fn (string $d): array => ['type' => 'number', 'description' => $d], self::READINGS),
                ],
                'volume_gallons' => ['type' => 'integer', 'description' => 'Volume in gallons, when there\'s no pool on file.'],
                'sanitizer' => ['type' => 'string', 'enum' => ['chlorine', 'salt', 'bromine'], 'description' => 'When there\'s no pool on file.'],
            ],
            'required' => [],
        ];
    }

    public function availableTo(User $user): bool
    {
        return true;
    }

    public function execute(array $params, User $user): string
    {
        $readings = $this->readings($params['readings'] ?? null);
        $pool = null;
        $source = '';

        if (isset($params['pool_id']) || trim((string) ($params['customer_name'] ?? '')) !== '' || ($user->role === 'customer' && $readings === [])) {
            $pools = $this->resolvePools($user, $params);
            if (is_string($pools)) {
                return $pools;
            }
            $pool = $this->onePool($pools);
            if (is_string($pool)) {
                return $pool;
            }
        }

        if ($readings === [] && $pool !== null) {
            $latest = ChemicalReading::query()->whereHas('serviceVisit', fn ($q) => $q->where('pool_id', $pool->id))->with('serviceVisit')->latest()->first();
            if ($latest === null) {
                return "There's no water test on file for {$pool->name} yet. Ask for the readings.";
            }
            $readings = $this->readings([...$latest->only(array_keys(self::READINGS)), 'temperature' => $latest->water_temperature]);
            $source = ' (its latest test, '.self::day($latest->serviceVisit?->visited_at).')';
        }
        if ($readings === []) {
            return 'Ask for the test results (at least pH and chlorine), and the pool\'s size in gallons.';
        }

        $chem = app(ChemistryService::class);
        if ($pool !== null) {
            $result = $chem->fullAnalysis($readings, $pool);
            $what = self::link('pool', $pool->id, $pool->name).' ('.($pool->volume_gallons !== null ? number_format($pool->volume_gallons).' gallons' : 'size unknown: doses assume 10,000 gallons').", {$pool->sanitizer_type}){$source}";
        } else {
            $gallons = is_numeric($params['volume_gallons'] ?? null) ? max(100, (int) $params['volume_gallons']) : null;
            $sanitizer = in_array($params['sanitizer'] ?? null, ['chlorine', 'salt', 'bromine'], true) ? $params['sanitizer'] : 'chlorine';
            $pool = (new Pool)->forceFill(['volume_gallons' => $gallons, 'sanitizer_type' => $sanitizer, 'type' => 'pool']);
            $analysis = $chem->analyzeReading($readings, $chem->targetsFor($pool));
            $lsi = $chem->calculateLSI($readings);
            $result = [
                'lsi' => ['value' => $lsi, ...$chem->getLSIStatus($lsi)],
                'parameters' => $analysis,
                'recommendations' => $chem->generateRecommendations($readings, $pool, $analysis, ['has_history' => false, 'parameters' => []]),
            ];
            $what = 'the water you gave ('.($gallons !== null ? number_format($gallons).' gallons' : 'size not given: doses assume 10,000 gallons').", {$sanitizer})";
        }

        return $this->describe($what, $result);
    }

    /**
     * The numbers the engine can use, keyed as it expects.
     *
     * @return array<string, float>
     */
    private function readings(mixed $raw): array
    {
        if (! is_array($raw)) {
            return [];
        }

        $out = [];
        foreach (array_keys(self::READINGS) as $key) {
            if (isset($raw[$key]) && is_numeric($raw[$key])) {
                $out[$key] = (float) $raw[$key];
            }
        }

        return $out;
    }

    /** @param  array<string, mixed>  $result */
    private function describe(string $what, array $result): string
    {
        $params = collect(is_array($result['parameters'] ?? null) ? $result['parameters'] : [])
            ->map(fn (array $p): string => "- {$p['label']} {$p['value']}".($p['unit'] !== '' ? " {$p['unit']}" : '')
                .": {$p['status']} (target {$p['min']}–{$p['max']})")->join("\n");
        $lsi = is_array($result['lsi'] ?? null) ? $result['lsi'] : [];
        $recs = collect(is_array($result['recommendations'] ?? null) ? $result['recommendations'] : [])
            ->map(fn (array $r): string => '- '.(($r['action'] ?? null) === 'drain_refill'
                ? "{$r['parameter']}: partly drain and refill (it can't be lowered with chemicals)"
                : "{$r['chemical']}: {$r['amount']} {$r['unit']} (for {$r['parameter']}, {$r['urgency']} priority)")
                .collect(is_array($r['notes'] ?? null) ? $r['notes'] : [])->map(fn ($n): string => "\n  {$n}")->join(''))
            ->join("\n");

        return "Water check for {$what}:\n{$params}\n"
            .(isset($lsi['value']) ? "LSI {$lsi['value']}: ".($lsi['label'] ?? '').(isset($lsi['description']) ? " ({$lsi['description']})" : '')."\n" : '')
            .($recs !== '' ? "What to add (the same doses the field app gives):\n{$recs}" : 'Nothing to add: everything tested is in range.');
    }
}
