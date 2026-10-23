import {
    Droplet,
    Filter,
    FlaskConical,
    House,
    Leaf,
    LifeBuoy,
    Palmtree,
    Shell,
    ShieldCheck,
    Sparkles,
    Sun,
    Thermometer,
    Umbrella,
    Waves,
    Wrench,
} from 'lucide-vue-next';
import type { Component } from 'vue';

/**
 * The icons a company can pick on its site: for each service in "What we do",
 * and for the mark beside its name in the header. Keys mirror
 * LandingConfig::ICONS (they're stored in the saved config).
 */
export const LANDING_ICONS: { key: string; label: string; icon: Component }[] = [
    { key: 'droplet', label: 'Droplet', icon: Droplet },
    { key: 'waves', label: 'Waves', icon: Waves },
    { key: 'sun', label: 'Sun', icon: Sun },
    { key: 'sparkles', label: 'Sparkles', icon: Sparkles },
    { key: 'shield', label: 'Shield', icon: ShieldCheck },
    { key: 'wrench', label: 'Wrench', icon: Wrench },
    { key: 'flask', label: 'Chemistry', icon: FlaskConical },
    { key: 'thermometer', label: 'Heater', icon: Thermometer },
    { key: 'filter', label: 'Filter', icon: Filter },
    { key: 'lifebuoy', label: 'Life ring', icon: LifeBuoy },
    { key: 'palmtree', label: 'Palm tree', icon: Palmtree },
    { key: 'umbrella', label: 'Umbrella', icon: Umbrella },
    { key: 'shell', label: 'Shell', icon: Shell },
    { key: 'leaf', label: 'Leaf', icon: Leaf },
    { key: 'house', label: 'Home', icon: House },
];

/** The icon for a key, or null for an unknown one. */
export function landingIcon(key: unknown): Component | null {
    return LANDING_ICONS.find((i) => i.key === key)?.icon ?? null;
}
