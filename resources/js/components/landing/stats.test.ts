import { describe, expect, it } from 'vitest';
import { badgeLabels, HERO_BADGES } from './badges';
import { statItems } from './stats';
import type { LiveData, SectionConfig } from './types';
import { hasContent } from './visibility';

const live: LiveData = { stats: { pools_serviced: 9, visits_completed: 37, years_active: 1, water_tests: 0 } };
const stats = (fields: Record<string, unknown>): SectionConfig => ({ key: 'stats', enabled: true, ...fields });

describe('statItems', () => {
    it('shows the picked stats in the company’s order, with live counts', () => {
        expect(statItems(stats({ metrics: ['visits_completed', 'pools_serviced'] }), live)).toEqual([
            { key: 'visits_completed', label: 'Visits completed', value: 37, plus: true },
            { key: 'pools_serviced', label: 'Pools serviced', value: 9, plus: true },
        ]);
    });

    it('shows nothing when every stat is turned off (no fallback to defaults)', () => {
        expect(statItems(stats({ metrics: [] }), live)).toEqual([]);
        expect(hasContent(stats({ metrics: [] }), live)).toBe(false);
    });

    it('uses the company’s own number over the live count', () => {
        const [years] = statItems(stats({ metrics: ['years_active'], overrides: { years_active: 15 } }), live);
        expect(years).toEqual({ key: 'years_active', label: 'Years in business', value: 15, plus: false });
    });

    it('hides a stat at 0, but shows it once the company types a number', () => {
        expect(statItems(stats({ metrics: ['water_tests'] }), live)).toEqual([]);
        expect(statItems(stats({ metrics: ['water_tests'] }), { stats: {} })).toEqual([]);
        expect(statItems(stats({ metrics: ['water_tests'], overrides: { water_tests: 1200 } }), live)[0].value).toBe(1200);
    });

    it('ignores unknown stats and the empty overrides list PHP sends', () => {
        expect(statItems(stats({ metrics: ['bogus', 'pools_serviced'], overrides: [] }), live).map((i) => i.key)).toEqual(['pools_serviced']);
    });
});

describe('badgeLabels', () => {
    it('lists picked badges in catalog order and drops unknown ones', () => {
        expect(badgeLabels(['same_day', 'licensed', 'nope'])).toEqual(['Licensed & insured', 'Same-day service']);
    });

    it('shows none when all are turned off or the field is missing', () => {
        expect(badgeLabels([])).toEqual([]);
        expect(badgeLabels(undefined)).toEqual([]);
    });

    it('has unique keys', () => {
        expect(new Set(HERO_BADGES.map((b) => b.key)).size).toBe(HERO_BADGES.length);
    });
});
