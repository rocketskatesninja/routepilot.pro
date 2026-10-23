import { SECTION_META } from './meta';
import { statItems } from './stats';
import type { LiveData, SectionConfig } from './types';

/**
 * Whether an enabled section has anything to show. An empty one (no FAQ
 * questions yet, no featured photos) stays off the public page and out of its
 * nav; the editor's preview still shows it, with a hint to fill it in.
 */
export function hasContent(s: SectionConfig, live: LiveData): boolean {
    const items = Array.isArray(s.items) ? s.items.length : 0;
    switch (s.key) {
        case 'stats':
            return statItems(s, live).length > 0;
        case 'services':
        case 'testimonials':
        case 'faq':
            return items > 0;
        case 'gallery':
            return (live.gallery?.length ?? 0) > 0;
        case 'team':
            return (live.team?.length ?? 0) > 0;
        case 'service_area':
            return typeof live.serviceArea?.lat === 'number' || !!live.serviceArea?.formattedAddress;
        default:
            return true;
    }
}

/** Where "get a quote" points: the quote form if it's on the page, else booking, else contact. */
export function quoteAnchor(onPage: string[]): string {
    return onPage.includes('quote') ? '#quote' : onPage.includes('booking') ? '#booking' : '#contact';
}

export interface SiteLink {
    href: string;
    label: string;
}

/** The header's button: "Get a quote" to the quote (else booking) form, else "Contact us", else none. */
export function headerButton(onPage: string[]): SiteLink | null {
    const href = quoteAnchor(onPage);
    if (href !== '#contact') return { href, label: 'Get a quote' };
    return onPage.includes('contact') ? { href, label: 'Contact us' } : null;
}

/** The header and footer links: each section that has one, in page order, minus where the button already goes. */
export function navLinks(onPage: string[]): SiteLink[] {
    const button = headerButton(onPage)?.href;
    return onPage.flatMap((key) => {
        const label = SECTION_META[key]?.nav;
        return label && `#${key}` !== button ? [{ href: `#${key}`, label }] : [];
    });
}
