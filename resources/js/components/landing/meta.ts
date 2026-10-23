import { AlignCenter, AlignLeft, AlignRight, Facebook, Instagram, Linkedin, Twitter, Youtube } from 'lucide-vue-next';
import type { Component } from 'vue';
import type { SectionConfig } from './types';

/** A choice in an editor control (or a page list): what's stored, what's shown. */
export interface Option {
    value: string;
    label: string;
    icon?: Component;
}

/**
 * Each section's name in the editor, and the heading it shows when the company
 * leaves its own blank — the same text as LandingConfig::defaults() (a unit test
 * keeps them in step). The hero and stats have no heading.
 */
export const SECTION_META: Record<string, { name: string; heading?: string }> = {
    hero: { name: 'Hero' },
    stats: { name: 'Stats band' },
    services: { name: 'Services', heading: 'What we do' },
    quote: { name: 'Instant quote', heading: 'Get an instant estimate' },
    gallery: { name: 'Photo gallery', heading: 'Recent work' },
    team: { name: 'Meet the team', heading: 'Meet the team' },
    service_area: { name: 'Service area', heading: 'Where we serve' },
    booking: { name: 'Online booking', heading: 'Request your first visit' },
    testimonials: { name: 'Testimonials', heading: 'Loved by homeowners' },
    faq: { name: 'FAQ', heading: 'Common questions' },
    cta: { name: 'Call to action', heading: 'Ready for a worry-free pool?' },
    contact: { name: 'Contact form', heading: 'Get in touch' },
};

/** The heading a section shows: the company's own, else its default. (The CTA band calls it a headline.) */
export function sectionHeading(s: SectionConfig): string {
    const own = s.key === 'cta' ? s.headline : s.heading;
    return (typeof own === 'string' && own.trim()) || SECTION_META[s.key]?.heading || '';
}

/** Hero background photos (public/assets/images/hero-presets). Mirrors LandingConfig::HERO_PRESETS. */
export const HERO_PRESETS = [
    'backyard',
    'cityscape',
    'infinity',
    'islands',
    'night',
    'patio',
    'resort',
    'skyline',
    'sunset',
    'tiles',
    'underwater',
    'water',
];

/** Hero effect switches. Mirrors LandingConfig::HERO_EFFECTS. */
export const HERO_EFFECTS: Option[] = [
    { value: 'ken_burns', label: 'Ken Burns zoom' },
    { value: 'cta_glow', label: 'Button glow' },
    { value: 'scroll_cue', label: 'Scroll-down arrow' },
    { value: 'dark_overlay', label: 'Dark overlay' },
    { value: 'dot_matrix', label: 'Dot matrix' },
    { value: 'vignette', label: 'Vignette' },
];

export const HERO_BACKGROUNDS: Option[] = [
    { value: 'preset', label: 'Preset' },
    { value: 'image', label: 'Upload' },
    { value: 'gradient', label: 'Gradient' },
];

/** How the hero text and the stats strip line up. */
export const ALIGN_OPTIONS: Option[] = [
    { value: 'left', label: 'Left', icon: AlignLeft },
    { value: 'center', label: 'Center', icon: AlignCenter },
    { value: 'right', label: 'Right', icon: AlignRight },
];

/** Footer social links, in the order they show. Mirrors LandingConfig::SOCIAL_PLATFORMS. */
export const SOCIAL_LINKS: (Option & { icon: Component })[] = [
    { value: 'facebook', label: 'Facebook', icon: Facebook },
    { value: 'instagram', label: 'Instagram', icon: Instagram },
    { value: 'twitter', label: 'X (Twitter)', icon: Twitter },
    { value: 'linkedin', label: 'LinkedIn', icon: Linkedin },
    { value: 'youtube', label: 'YouTube', icon: Youtube },
];
