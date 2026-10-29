import { clsx, type ClassValue } from 'clsx';
import { twMerge } from 'tailwind-merge';

export function cn(...inputs: ClassValue[]) {
    return twMerge(clsx(inputs));
}

/** Format a number or numeric string as USD currency, e.g. 1234.5 -> "$1,234.50". */
export function formatMoney(value: number | string): string {
    const n = typeof value === 'string' ? Number(value) : value;
    return new Intl.NumberFormat('en-US', { style: 'currency', currency: 'USD' }).format(Number.isFinite(n) ? n : 0);
}

/** A quantity with its unit, whole numbers without decimals and the unit made to agree: "12 gal", "1 bag", "2.50 lbs". */
export function formatAmount(n: number, unit: string): string {
    return `${Number.isInteger(n) ? n : n.toFixed(2)} ${unitFor(n, unit)}`;
}

/** A unit agreeing with its amount: bag/bags, lb/lbs, puck/pucks, tab/tabs, unit/units (parts); others (gal, older typed ones) as given. Matches StandardChemicals::unitFor. */
export function unitFor(n: number, unit: string): string {
    const counted: Record<string, [string, string]> = {
        bag: ['bag', 'bags'],
        bags: ['bag', 'bags'],
        lb: ['lb', 'lbs'],
        lbs: ['lb', 'lbs'],
        puck: ['puck', 'pucks'],
        pucks: ['puck', 'pucks'],
        tab: ['tab', 'tabs'],
        tabs: ['tab', 'tabs'],
        unit: ['unit', 'units'],
        units: ['unit', 'units'],
    };
    const forms = counted[unit];
    return forms ? forms[n === 1 ? 0 : 1] : unit;
}

/** Deep clone a plain (JSON-serializable) value. */
export function clone<T>(value: T): T {
    return JSON.parse(JSON.stringify(value)) as T;
}

/** A plain YYYY-MM-DD date as "Tue, Oct 20" — read as a calendar day, never shifted by time zone. */
export function formatDay(ymd: string | null | undefined): string {
    if (!ymd) return '';
    const [y, m, d] = ymd.split('-').map(Number);
    return new Date(y, m - 1, d).toLocaleDateString('en-US', { weekday: 'short', month: 'short', day: 'numeric' });
}

/**
 * A plain email address the server will take (it checks against the RFC): a name,
 * an @, and a domain with a dot — no spaces, and no dot at the name's ends or twice
 * in a row. Strict on purpose: refusing here beats a visit the server refuses later.
 */
export function isEmail(value: string): boolean {
    const [name, domain, ...rest] = value.split('@');
    if (rest.length || !name || !domain || name.length > 64 || value.length > 255) return false;
    return (
        /^[A-Za-z0-9!#$%&'*+/=?^_`{|}~.-]+$/.test(name) &&
        !/^\.|\.$|\.\./.test(name) &&
        /^[A-Za-z0-9](?:[A-Za-z0-9-]*[A-Za-z0-9])?(?:\.[A-Za-z0-9](?:[A-Za-z0-9-]*[A-Za-z0-9])?)+$/.test(domain)
    );
}
