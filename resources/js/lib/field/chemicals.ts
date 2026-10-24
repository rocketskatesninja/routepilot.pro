/**
 * Turning a water-test suggestion into a chemical from the company's list.
 * The chemistry engine names chemicals its own way and doses in oz or lbs; each
 * company chemical has one unit (gal or lbs) and is recorded in whole ones.
 */

/**
 * The engine's chemical names → the standard chemical they are
 * (App\Support\StandardChemicals). Actions like "Run chlorine generator" or
 * "Partial Drain & Refill" aren't chemicals, so they're not here.
 */
export const SUGGESTION_KEYS: Record<string, string> = {
    'Granular Chlorine (Cal-Hypo)': 'cal_hypo',
    'Liquid Chlorine': 'liquid_chlorine',
    'Muriatic Acid': 'muriatic_acid',
    'Soda Ash': 'soda_ash',
    'Sodium Bicarbonate (Baking Soda)': 'baking_soda',
    'Calcium Chloride': 'calcium_chloride',
    'Cyanuric Acid (Stabilizer)': 'cyanuric_acid',
    'Pool Salt': 'salt',
};

// How many of the chemical's unit one of the suggestion's unit is. Salt comes in standard 40-lb bags.
const PER: Record<string, Record<string, number>> = {
    gal: { oz: 1 / 128, gal: 1 },
    lbs: { oz: 1 / 16, lbs: 1 },
    bag: { lbs: 1 / 40 },
};

/** A suggested dose in the chemical's own unit, rounded up to a whole one (at least 1); null if the units don't convert. */
export function toStockAmount(amount: number, fromUnit: string, toUnit: string): number | null {
    const per = PER[toUnit]?.[fromUnit];
    if (per === undefined || !(amount > 0)) return null;
    // Round off float noise first, so an exact 2 lbs doesn't become 3.
    return Math.max(1, Math.ceil(Math.round(amount * per * 1e6) / 1e6));
}
