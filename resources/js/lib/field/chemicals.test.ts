import { describe, expect, it } from 'vitest';
import { fullAnalysis, type Reading } from '../chemistry';
import { formatAmount } from '../utils';
import { SUGGESTION_KEYS, toStockAmount } from './chemicals';

describe('toStockAmount', () => {
    it('rounds a suggested dose up to whole units of the chemical', () => {
        expect(toStockAmount(9.6, 'oz', 'gal')).toBe(1);
        expect(toStockAmount(200, 'oz', 'gal')).toBe(2);
        expect(toStockAmount(9, 'oz', 'lbs')).toBe(1);
        expect(toStockAmount(32, 'oz', 'lbs')).toBe(2); // exactly 2, not 3
        expect(toStockAmount(1.5, 'lbs', 'lbs')).toBe(2);
        expect(toStockAmount(40, 'lbs', 'lbs')).toBe(40);
        expect(toStockAmount(48, 'lbs', 'bag')).toBe(2); // salt: 40-lb bags, rounded up
        expect(toStockAmount(40, 'lbs', 'bag')).toBe(1);
    });

    it("gives nothing for units that don't convert, or no dose", () => {
        expect(toStockAmount(2, 'lbs', 'gal')).toBeNull();
        expect(toStockAmount(2.4, 'hours extra runtime', 'lbs')).toBeNull();
        expect(toStockAmount(0, 'oz', 'gal')).toBeNull();
    });
});

describe('SUGGESTION_KEYS', () => {
    // Everything the engine can suggest, from one out-of-range reading at a time.
    const suggestions = (['chlorine', 'salt'] as const).flatMap((sanitizer) =>
        (
            [
                { free_chlorine: 0.2 },
                { free_chlorine: 1.5, total_chlorine: 2.5 }, // chloramines: shock
                { ph: 8.2 },
                { ph: 6.8 },
                { alkalinity: 40 },
                { alkalinity: 200 },
                { calcium_hardness: 80 },
                { cyanuric_acid: 5 },
                { salt: 1500 },
            ] as Reading[]
        ).flatMap((reading) => fullAnalysis(reading, { volume_gallons: 20000, sanitizer }).recommendations),
    );
    const actions = ['Run chlorine generator', 'Partial Drain & Refill'];
    const chemicals = suggestions.filter((r) => !actions.includes(r.chemical));
    const unitOf = (key: string) => (key === 'muriatic_acid' || key === 'liquid_chlorine' ? 'gal' : key === 'salt' ? 'bag' : 'lbs');

    it('knows every chemical the engine suggests', () => {
        expect(new Set(chemicals.map((r) => r.chemical)).size).toBeGreaterThanOrEqual(6);
        for (const rec of chemicals) expect(SUGGESTION_KEYS[rec.chemical], rec.chemical).toBeDefined();
    });

    it('suggests each in a unit that converts to its standard unit', () => {
        for (const rec of chemicals) expect(toStockAmount(rec.amount, rec.unit, unitOf(SUGGESTION_KEYS[rec.chemical])), rec.chemical).not.toBeNull();
    });

    it('leaves actions out, so they never land among the chemicals', () => {
        for (const action of actions) expect(SUGGESTION_KEYS[action]).toBeUndefined();
    });
});

describe('formatAmount', () => {
    it('makes the unit agree with the amount, as StandardChemicals::amount does', () => {
        expect(formatAmount(1, 'bag')).toBe('1 bag');
        expect(formatAmount(2, 'bag')).toBe('2 bags');
        expect(formatAmount(1, 'lbs')).toBe('1 lb');
        expect(formatAmount(3, 'lbs')).toBe('3 lbs');
        expect(formatAmount(2, 'gal')).toBe('2 gal');
        expect(formatAmount(9.6, 'oz')).toBe('9.60 oz');
    });
});
