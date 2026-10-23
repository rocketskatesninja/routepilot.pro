import { describe, expect, it } from 'vitest';
import { headerButton, navLinks } from './visibility';

const labels = (onPage: string[]) => navLinks(onPage).map((l) => l.label);

describe('header button and links', () => {
    it('links each section that has one, in page order; the hero, stats, CTA band, team and map have none', () => {
        expect(labels(['hero', 'stats', 'faq', 'services', 'cta', 'team', 'service_area', 'gallery'])).toEqual(['FAQ', 'Services', 'Our work']);
    });

    it('sends the button to the quote form and keeps "Get a quote" out of the links', () => {
        const onPage = ['services', 'quote', 'booking', 'contact'];
        expect(headerButton(onPage)).toEqual({ href: '#quote', label: 'Get a quote' });
        expect(labels(onPage)).toEqual(['Services', 'Book a visit', 'Contact']);
    });

    it('falls back to booking, then to "Contact us", never linking the same place twice', () => {
        expect(headerButton(['booking', 'contact'])).toEqual({ href: '#booking', label: 'Get a quote' });
        expect(labels(['booking', 'contact'])).toEqual(['Contact']);
        expect(headerButton(['faq', 'contact'])).toEqual({ href: '#contact', label: 'Contact us' });
        expect(labels(['faq', 'contact'])).toEqual(['FAQ']);
    });

    it('has no button when there is nowhere to send it', () => {
        expect(headerButton(['services', 'faq'])).toBeNull();
        expect(labels(['services', 'faq'])).toEqual(['Services', 'FAQ']);
    });
});
