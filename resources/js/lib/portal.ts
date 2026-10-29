/**
 * Plain-language helpers for the customer portal — dates, schedules, services
 * and water tests the way a homeowner would say them. Shared by every portal
 * page so they all read the same.
 */
import { targetsFor } from '@/lib/chemistry';
import { FlaskConical, Waves, Wrench } from 'lucide-vue-next';

const parse = (ymd: string) => {
    const [y, m, d] = ymd.split('-').map(Number);
    return new Date(y, m - 1, d);
};

export const cap = (s: string) => s.charAt(0).toUpperCase() + s.slice(1);

const ordinal = (n: number) => {
    const t = n % 100;
    return `${n}${t >= 11 && t <= 13 ? 'th' : (({ 1: 'st', 2: 'nd', 3: 'rd' } as Record<number, string>)[n % 10] ?? 'th')}`;
};

/** "Wednesday, October 21". */
export const longDay = (ymd: string) => parse(ymd).toLocaleDateString('en-US', { weekday: 'long', month: 'long', day: 'numeric' });

/** "October 2026". */
export const monthYear = (ymd: string) => parse(ymd).toLocaleDateString('en-US', { month: 'long', year: 'numeric' });

const dayAfter = (ymd: string) => {
    const d = parse(ymd);
    d.setDate(d.getDate() + 1);
    return d.toLocaleDateString('en-CA');
};

/** "today" / "tomorrow" / "on Wednesday, October 21" — relative to the company's today. */
export const when = (ymd: string, today: string) => (ymd === today ? 'today' : ymd === dayAfter(today) ? 'tomorrow' : `on ${longDay(ymd)}`);

/** A plan's schedule as a homeowner says it. */
export interface ScheduleLike {
    frequency: string;
    preferred_day: string | null;
    day_of_month: number | null;
    starts_on: string | null;
}

export function howOften(p: ScheduleLike): string {
    const day = p.preferred_day ? cap(p.preferred_day) : null;
    if (p.frequency === 'weekly') return day ? `Every ${day}` : 'Every week';
    if (p.frequency === 'biweekly') return day ? `Every other ${day}` : 'Every two weeks';
    if (p.frequency === 'monthly') return p.day_of_month ? `Once a month, on the ${ordinal(p.day_of_month)}` : 'Once a month';
    return p.starts_on ? `One visit, ${longDay(p.starts_on)}` : 'One visit';
}

export const soldAs = (f: string) =>
    ({ weekly: 'Every week', biweekly: 'Every two weeks', monthly: 'Once a month', one_time: 'One-time job' })[f] ?? '';

/** "$35" / "$35.50". */
export const price = (n: number) => `$${Number.isInteger(n) ? n : n.toFixed(2)}`;

/** A simple picture for a service: a one-off job, a water-testing visit, or a regular clean. */
export const iconFor = (modules: string[], frequency: string) =>
    frequency === 'one_time' ? Wrench : !modules.includes('tasks') && modules.includes('chemistry') ? FlaskConical : Waves;

/**
 * What the tech does on a visit, in a homeowner's words (from the service's visit
 * sections), leaving out what its checklist already says, so nothing shows twice.
 */
export function extras(modules: string[], tasks: string[] = []): string[] {
    const said = (re: RegExp) => tasks.some((t) => re.test(t));
    const out: string[] = [];
    if (modules.includes('chemistry') && !said(/\b(test|balanc)/i)) out.push('Water tested and balanced');
    if (modules.includes('treatments') && !said(/chemical/i)) out.push('Chemicals added as needed');
    if (modules.includes('photos') && !said(/photo/i)) out.push('Photos of your pool after each visit');
    return out;
}

/** "Robert", "Robert and Ana", "A, B and C". */
export const andList = (items: string[]) => (items.length > 1 ? `${items.slice(0, -1).join(', ')} and ${items[items.length - 1]}` : (items[0] ?? ''));

// --- pools ---

const POOL_KINDS: Record<string, string> = {
    inground: 'in-ground',
    above_ground: 'above-ground',
    indoor: 'indoor',
    spa: 'spa',
    infinity: 'infinity',
    other: '',
};
const SANITIZERS: Record<string, string> = {
    salt: 'salt',
    chlorine: 'chlorine',
    bromine: 'bromine',
    biguanide: 'biguanide',
    ozone: 'ozone',
    uv: 'UV',
};
const FEATURES: Record<string, string> = {
    heater: 'a heater',
    cover: 'a cover',
    automation: 'automation',
    cleaner: 'a pool cleaner',
    water_feature: 'a water feature',
    auto_fill: 'an auto-fill',
};

/** "A 12,000-gallon in-ground salt pool, with a heater and a cover." */
export function describePool(p: { volume: number | null; type: string; sanitizer: string | null; features: string[] }): string {
    const size = p.volume ? `${p.volume.toLocaleString('en-US')}-gallon` : '';
    const kind = POOL_KINDS[p.type] ?? '';
    const water = p.sanitizer ? (SANITIZERS[p.sanitizer] ?? '') : '';
    const words = [size, kind, water, p.type === 'spa' ? '' : 'pool'].filter(Boolean).join(' ');
    const article = /^[aeiou8]/i.test(words) || /^1[18],/.test(words) ? 'An' : 'A';
    const extras = p.features.map((f) => FEATURES[f]).filter(Boolean);
    return `${article} ${words || 'pool'}${extras.length ? `, with ${andList(extras)}` : ''}.`;
}

// --- water tests ---

export type WaterReading = Partial<Record<'free_chlorine' | 'ph' | 'alkalinity' | 'calcium_hardness' | 'cyanuric_acid' | 'salt', number | null>>;
export type WaterTargets = Record<string, { min?: number; max?: number }> | null;

/** Each thing the tech tests, named and explained for a homeowner. */
const WATER = [
    { key: 'free_chlorine', name: 'Chlorine', why: 'Keeps the water clean and safe to swim in.' },
    { key: 'ph', name: 'pH', why: 'How acidic the water is. Affects comfort and protects equipment.' },
    { key: 'alkalinity', name: 'Alkalinity', why: 'Keeps the pH steady between visits.' },
    { key: 'calcium_hardness', name: 'Calcium', why: 'Protects the pool surface and equipment.' },
    { key: 'cyanuric_acid', name: 'Stabilizer', why: 'Shields chlorine from the sun so it lasts longer.' },
    { key: 'salt', name: 'Salt', why: 'Feeds the salt system that makes your chlorine.' },
] as const;

export interface WaterCheck {
    key: string;
    name: string;
    why: string;
    value: number;
    unit: string;
    min: number;
    max: number;
    status: 'good' | 'low' | 'high';
    /** Well outside the range, not just a little. */
    far: boolean;
    label: string;
}

/** A water test checked against the pool's own targets (the same ones the tech works to). */
export function checkWater(reading: WaterReading | null, targets: WaterTargets = null): WaterCheck[] {
    if (!reading) return [];
    const ranges = targetsFor({ custom_target_ranges: targets });
    return WATER.filter((w) => reading[w.key] !== null && reading[w.key] !== undefined && ranges[w.key]).map((w) => {
        const value = Number(reading[w.key]);
        const { min, max, unit } = ranges[w.key];
        const span = Math.max(max - min, 0.0001);
        const status = value < min ? 'low' : value > max ? 'high' : 'good';
        const far = status === 'low' ? value < min - span / 2 : status === 'high' ? value > max + span / 2 : false;
        const label = status === 'good' ? 'Good' : `${far ? '' : 'A little '}${status}`;
        return { key: w.key, name: w.name, why: w.why, value, unit, min, max, status, far, label: cap(label) };
    });
}

/**
 * One calm sentence about a water test: "Everything was in the healthy range."
 * Customers also hear that their technician takes care of anything off; staff don't need telling.
 */
export function waterSummary(checks: WaterCheck[], forCustomer = true): string {
    if (!checks.length) return '';
    const off = checks.filter((c) => c.status !== 'good');
    if (!off.length) return 'Everything was in the healthy range.';
    const parts = off.map((c) => `${c.name === 'pH' ? 'pH' : c.name.toLowerCase()} was ${c.far ? '' : 'a little '}${c.status}`);
    return `${cap(andList(parts))}.${forCustomer ? ' Your technician adjusts this as part of each visit.' : ''}`;
}

/** "3.1 ppm" / "7.2". */
export const reads = (c: Pick<WaterCheck, 'value' | 'unit'>) => `${c.value}${c.unit ? ` ${c.unit}` : ''}`;
