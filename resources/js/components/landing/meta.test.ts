import { describe, expect, it } from 'vitest';
import { sectionHeading } from './meta';

describe('sectionHeading', () => {
    it('uses the company’s own heading, else the default', () => {
        expect(sectionHeading({ key: 'services', enabled: true, heading: 'Our work' })).toBe('Our work');
        expect(sectionHeading({ key: 'services', enabled: true, heading: '  ' })).toBe('What we do');
        expect(sectionHeading({ key: 'faq', enabled: true, heading: null })).toBe('Common questions');
    });

    it('reads the call-to-action band’s headline, and is blank for sections with no heading', () => {
        expect(sectionHeading({ key: 'cta', enabled: true, headline: 'Ready?' })).toBe('Ready?');
        expect(sectionHeading({ key: 'hero', enabled: true, headline: 'Crystal-clear water' })).toBe('');
    });
});
