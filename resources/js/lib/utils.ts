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
