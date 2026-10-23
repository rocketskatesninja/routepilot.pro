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
