import { describe, expect, it } from 'vitest';
import { formatAmount, isEmail, unitFor } from './utils';

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
