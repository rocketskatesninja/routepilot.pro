import type { LiveData, SectionConfig } from './types';

/** The numbers a company can show in its stats strip. Keys mirror LandingConfig::METRICS. */
export const STAT_METRICS = [
    { key: 'pools_serviced', label: 'Pools serviced' },
    { key: 'visits_completed', label: 'Visits completed' },
    { key: 'years_active', label: 'Years in business' },
    { key: 'happy_customers', label: 'Happy customers' },
    { key: 'water_tests', label: 'Water tests' },
    { key: 'gallons_maintained', label: 'Gallons maintained' },
    { key: 'technicians', label: 'Expert technicians' },
] as const;

const LABELS: Record<string, string> = Object.fromEntries(STAT_METRICS.map((m) => [m.key, m.label]));

export interface StatItem {
    key: string;
    label: string;
    value: number;
    /** Counts read "120+"; years in business doesn't. */
    plus: boolean;
}

/** The numbers a company typed in place of the live counts, by stat. */
export function statOverrides(content: SectionConfig): Record<string, number> {
    const o = content.overrides;
    return o !== null && typeof o === 'object' && !Array.isArray(o) ? (o as Record<string, number>) : {};
}

/**
 * What the stats strip shows, in the company's order: their own number where
 * they typed one, else the live count from their records. A stat at 0 stays
 * hidden (no "0+" on a new site), and nothing selected shows nothing.
 */
export function statItems(content: SectionConfig, live: LiveData): StatItem[] {
    const keys = Array.isArray(content.metrics) ? (content.metrics as string[]) : [];
    const overrides = statOverrides(content);

    return keys.flatMap((key) => {
        const label = LABELS[key];
        const value = overrides[key] ?? live.stats?.[key] ?? 0;
        return label && value > 0 ? [{ key, label, value, plus: key !== 'years_active' }] : [];
    });
}
