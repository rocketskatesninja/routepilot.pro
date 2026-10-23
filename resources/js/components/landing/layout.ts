// Shared class strings for a company's site. Breakpoints are container queries
// (@3xl = 48rem, @5xl = 64rem) against the `.tenant-site` root, so the editor's
// narrower live preview lays out the way a site that wide would. Full literals,
// so Tailwind (which scans .ts files) emits them.

/** The frame every section's content sits in: 1280px wide, with gutters. */
export const frame = 'mx-auto w-full max-w-7xl px-5 @3xl:px-8';

/** A text input, select or textarea on the site's forms. */
export const field =
    'border-input bg-background w-full rounded-lg border px-4 py-3 text-base outline-none focus-visible:border-primary focus-visible:ring-3 focus-visible:ring-primary/25';

/** The site's main button: brand fill, the company's call to action. */
export const button =
    'bg-primary text-primary-foreground inline-flex items-center justify-center rounded-xl px-7 py-3.5 font-semibold shadow-sm transition-[filter] hover:brightness-110 focus-visible:ring-3 focus-visible:ring-primary/40 focus-visible:outline-none disabled:opacity-60';

/**
 * Columns that even out the rows for `count` items, at most `max` across:
 * 4 items at max 3 are 2 + 2, not 3 + 1.
 */
export function balancedCols(count: number, max: number): number {
    if (count <= 1) return 1;
    return Math.min(max, Math.ceil(count / Math.ceil(count / max)));
}

// Wide-container column classes by count (literals for Tailwind).
const WIDE_COLS: Record<number, string> = {
    1: '@3xl:grid-cols-1',
    2: '@3xl:grid-cols-2',
    3: '@3xl:grid-cols-3',
    4: '@3xl:grid-cols-4',
};

/** A grid that's one column on a phone, two from small, and balanced (≤ max) when wide. */
export function balancedGrid(count: number, max: 3 | 4): string {
    return count > 1 ? `grid grid-cols-1 @xl:grid-cols-2 ${WIDE_COLS[balancedCols(count, max)]}` : 'grid grid-cols-1';
}
