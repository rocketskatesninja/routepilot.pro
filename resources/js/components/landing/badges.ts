/**
 * Badges a company can show under its hero headline, in the order they appear.
 * Keys mirror LandingConfig::HERO_BADGES. They're claims the company makes about
 * itself, so it picks which (or none). A site starts with the four every site
 * used to show: licensed, CPO certified, 5-star rated and same-day service.
 */
export const HERO_BADGES = [
    { key: 'licensed', label: 'Licensed & insured' },
    { key: 'cpo', label: 'CPO certified' },
    { key: 'five_star', label: '5-star rated' },
    { key: 'same_day', label: 'Same-day service' },
    { key: 'free_estimates', label: 'Free estimates' },
    { key: 'no_contracts', label: 'No long-term contracts' },
    { key: 'satisfaction', label: 'Satisfaction guaranteed' },
    { key: 'locally_owned', label: 'Locally owned' },
    { key: 'family_owned', label: 'Family owned' },
    { key: 'veteran_owned', label: 'Veteran owned' },
    { key: 'photo_reports', label: 'Photo report after every visit' },
    { key: 'pay_online', label: 'Pay your bill online' },
    { key: 'autopay', label: 'Autopay available' },
    { key: 'background_checked', label: 'Background-checked techs' },
    { key: 'eco_friendly', label: 'Eco-friendly products' },
    { key: 'salt_systems', label: 'Salt system specialists' },
    { key: 'emergency', label: 'Emergency service' },
    { key: 'spanish', label: 'Se habla español' },
] as const;

/** More than this crowds the hero. Mirrors LandingConfig::MAX_BADGES. */
export const MAX_BADGES = 6;

/** The picked badges' labels, in catalog order. */
export function badgeLabels(picked: unknown): string[] {
    const keys = Array.isArray(picked) ? picked : [];
    return HERO_BADGES.filter((b) => keys.includes(b.key)).map((b) => b.label);
}
