import { afterEach, describe, expect, it } from 'vitest';
import { formatAmount, isEmail, localDay, unitFor } from './utils';

describe('unitFor', () => {
    it('agrees with the amount, counted tablets too', () => {
        expect([unitFor(1, 'puck'), unitFor(3, 'puck'), unitFor(1, 'tab'), unitFor(2, 'tabs')]).toEqual(['puck', 'pucks', 'tab', 'tabs']);
        expect([formatAmount(1, 'bag'), formatAmount(2.5, 'lbs'), formatAmount(3, 'gal')]).toEqual(['1 bag', '2.50 lbs', '3 gal']);
    });
});

describe('isEmail', () => {
    it('takes ordinary addresses', () => {
        for (const ok of ['jane@example.com', 'jane.doe+pool@mail.example.co.uk', "o'brien@example.org"]) expect(isEmail(ok)).toBe(true);
    });

    it('refuses what the server would', () => {
        for (const bad of [
            '',
            'jane',
            'jane@',
            '@example.com',
            'jane@example',
            'jane doe@example.com',
            'jane..doe@example.com',
            '.jane@example.com',
            'jane@@example.com',
            'jane@-example.com',
        ])
            expect(isEmail(bad)).toBe(false);
    });
});

describe('unitFor, parts', () => {
    it('counts parts in units', () => {
        expect([unitFor(1, 'unit'), unitFor(3, 'unit'), formatAmount(2, 'unit')]).toEqual(['unit', 'units', '2 units']);
    });
});

describe('localDay', () => {
    const original = process.env.TZ;
    afterEach(() => {
        process.env.TZ = original;
    });

    it('is the day on this device’s calendar, not the UTC day', () => {
        process.env.TZ = 'America/New_York';
        // 10:15pm in New York on Tuesday the 10th is already the 11th in UTC.
        expect(localDay(new Date('2026-11-11T03:15:00Z'))).toBe('2026-11-10');
    });

    it('turns over at the device’s own midnight', () => {
        process.env.TZ = 'America/Los_Angeles';
        expect(localDay(new Date('2026-11-11T07:59:00Z'))).toBe('2026-11-10'); // 11:59pm Pacific
        expect(localDay(new Date('2026-11-11T08:00:00Z'))).toBe('2026-11-11'); // midnight Pacific
    });
});
