<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\ChemicalReading;
use App\Models\Pool;
use App\Services\ChemistryService;

/**
 * A water test the way a homeowner reads it, for server-rendered places like
 * email — the same names, wording and good / low / high rules as the portal's
 * checkWater() (resources/js/lib/portal.ts), against the pool's own targets.
 */
final class WaterReport
{
    /** Each thing the tech tests, named and explained for a homeowner (same order as the portal). */
    private const WATER = [
        'free_chlorine' => ['Chlorine', 'Keeps the water clean and safe to swim in.'],
        'ph' => ['pH', 'How acidic the water is. Affects comfort and protects equipment.'],
        'alkalinity' => ['Alkalinity', 'Keeps the pH steady between visits.'],
        'calcium_hardness' => ['Calcium', 'Protects the pool surface and equipment.'],
        'cyanuric_acid' => ['Stabilizer', 'Shields chlorine from the sun so it lasts longer.'],
        'salt' => ['Salt', 'Feeds the salt system that makes your chlorine.'],
    ];

    /**
     * @return list<array{name: string, why: string, value: float, unit: string, min: float, max: float, status: string, label: string, reads: string, range: string, band_left: float, band_width: float, dot: float, far: bool}>
     */
    public static function checks(?ChemicalReading $reading, Pool $pool): array
    {
        if ($reading === null) {
            return [];
        }
        $ranges = app(ChemistryService::class)->targetsFor($pool);
        $checks = [];

        foreach (self::WATER as $key => [$name, $why]) {
            $raw = $reading->getAttribute($key);
            if ($raw === null || ! isset($ranges[$key])) {
                continue;
            }
            $value = (float) $raw;
            $min = (float) $ranges[$key]['min'];
            $max = (float) $ranges[$key]['max'];
            $unit = (string) $ranges[$key]['unit'];
            $span = max($max - $min, 0.0001);
            $status = $value < $min ? 'low' : ($value > $max ? 'high' : 'good');
            $far = ($status === 'low' && $value < $min - $span / 2) || ($status === 'high' && $value > $max + $span / 2);
            // Where a value sits on the bar: the healthy range in the middle third.
            $pct = fn (float $v): float => round(min(100, max(0, ($v - ($min - $span)) / (3 * $span) * 100)), 1);

            $checks[] = [
                'name' => $name,
                'why' => $why,
                'value' => $value,
                'unit' => $unit,
                'min' => $min,
                'max' => $max,
                'status' => $status,
                'label' => $status === 'good' ? 'Good' : ucfirst(($far ? '' : 'a little ').$status),
                'reads' => self::number($value).($unit !== '' ? ' '.$unit : ''),
                'range' => self::number($min).'–'.self::number($max).($unit !== '' ? ' '.$unit : ''),
                'band_left' => $pct($min),
                'band_width' => round($pct($max) - $pct($min), 1),
                'dot' => $pct($value),
                'far' => $far,
            ];
        }

        return $checks;
    }

    /**
     * One calm sentence: "Everything was in the healthy range." Customers also
     * hear that their technician takes care of anything off.
     *
     * @param  list<array{name: string, status: string, far?: bool}>  $checks
     */
    public static function summary(array $checks, bool $forCustomer = true): string
    {
        $off = array_values(array_filter($checks, fn (array $c): bool => $c['status'] !== 'good'));
        if ($checks === []) {
            return '';
        }
        if ($off === []) {
            return 'Everything was in the healthy range.';
        }
        $parts = array_map(fn (array $c): string => ($c['name'] === 'pH' ? 'pH' : strtolower($c['name'])).' was '.(($c['far'] ?? false) ? '' : 'a little ').$c['status'], $off);
        $list = count($parts) > 1 ? implode(', ', array_slice($parts, 0, -1)).' and '.end($parts) : $parts[0];

        return ucfirst($list).'.'.($forCustomer ? ' Your technician adjusts this as part of each visit.' : '');
    }

    /** "3.1" / "7" — no trailing zeros. */
    private static function number(float $n): string
    {
        return rtrim(rtrim(number_format($n, 2, '.', ''), '0'), '.');
    }
}
