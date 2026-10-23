import { describe, expect, it } from 'vitest';
import { pageTitle } from './pageTitle';

describe('pageTitle', () => {
    it('adds the app name to RoutePilot’s own pages, not to a company’s site', () => {
        expect(pageTitle('People', 'people/Index', 'RoutePilot')).toBe('People - RoutePilot');
        expect(pageTitle('Glynn Pool Care', 'public/Landing', 'RoutePilot')).toBe('Glynn Pool Care');
    });
});
