<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\ChemicalInventory;

/**
 * The chemicals every company starts with, each in its one unit (liquids by the
 * gallon, dry chemicals by the pound, salt by the 40-lb bag, chlorine tablets by
 * the piece: 3-inch pucks and 1-inch tabs). The key ties a
 * company's chemical to the water-test suggestions that call for it, even after
 * the office renames it; resources/js/lib/field/chemicals.ts maps the
 * suggestions to these keys.
 */
class StandardChemicals
{
    /** key => [name, unit] */
    public const LIST = [
        'liquid_chlorine' => ['Liquid Chlorine', 'gal'],
        'muriatic_acid' => ['Muriatic Acid', 'gal'],
        'cal_hypo' => ['Cal Hypo', 'lbs'],
        'soda_ash' => ['Soda Ash', 'lbs'],
        'baking_soda' => ['Baking Soda', 'lbs'],
        'calcium_chloride' => ['Calcium Chloride', 'lbs'],
        'cyanuric_acid' => ['Cyanuric Acid', 'lbs'],
        'salt' => ['Salt', 'bag'],
        'chlorine_pucks' => ['Chlorine Pucks', 'puck'],
        'chlorine_tabs' => ['Chlorine Tabs', 'tab'],
    ];

    /** The units a chemical can be kept in. A bag is a standard 40-lb bag (resources/js/lib/field/chemicals.ts); pucks and tabs are counted. */
    public const UNITS = ['gal', 'lbs', 'bag', 'puck', 'tab'];

    /** An amount with its unit made to agree: "1 bag" / "2 bags", "1 lb" / "3 lbs", "1 puck" / "3 pucks", "2 gal". */
    public static function amount(float $n, string $unit): string
    {
        return trim(rtrim(rtrim(number_format($n, 2, '.', ''), '0'), '.').' '.self::unitFor($n, $unit));
    }

    /** A unit agreeing with its amount: bag/bags, lb/lbs, puck/pucks, tab/tabs; others (gal, older typed ones) as given. */
    public static function unitFor(float $n, string $unit): string
    {
        return match ($unit) {
            'bag', 'bags' => $n == 1 ? 'bag' : 'bags',
            'lb', 'lbs' => $n == 1 ? 'lb' : 'lbs',
            'puck', 'pucks' => $n == 1 ? 'puck' : 'pucks',
            'tab', 'tabs' => $n == 1 ? 'tab' : 'tabs',
            default => $unit,
        };
    }

    /** Give a company any standard chemical it doesn't have yet (no stock or prices). */
    public static function addTo(int $tenantId): void
    {
        $have = ChemicalInventory::query()->withoutGlobalScopes()->where('tenant_id', $tenantId)
            ->whereNotNull('standard_key')->pluck('standard_key')->all();

        foreach (self::LIST as $key => [$name, $unit]) {
            if (in_array($key, $have, true)) {
                continue;
            }
            // tenant_id isn't fillable, and at signup no tenant is bound for BelongsToTenant to fill it.
            (new ChemicalInventory(['chemical_name' => $name, 'unit' => $unit, 'current_stock' => 0, 'is_active' => true]))
                ->forceFill(['tenant_id' => $tenantId, 'standard_key' => $key])
                ->save();
        }
    }
}
