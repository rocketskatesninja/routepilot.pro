import { describe, expect, it } from 'vitest';
import { mapsLink } from './links';

describe('mapsLink', () => {
    it('searches Google Maps for the address, else the map position', () => {
        expect(mapsLink('27 Marsh Hen Lane, St. Simons Island, GA 31522', [31.15, -81.39])).toBe(
            'https://www.google.com/maps/search/?api=1&query=27%20Marsh%20Hen%20Lane%2C%20St.%20Simons%20Island%2C%20GA%2031522',
        );
        expect(mapsLink(null, [31.15, -81.39])).toBe('https://www.google.com/maps/search/?api=1&query=31.15%2C-81.39');
        expect(mapsLink('  ', null)).toBeNull();
    });
});
