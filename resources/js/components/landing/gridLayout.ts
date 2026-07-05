// Balanced, centered item rows for the landing sections.
//
// `flex flex-wrap justify-center` centers any incomplete row so a trailing item never
// left-aligns (Idea C); `balancedCols` picks the column count that evens the rows — e.g.
// 4 items become 2×2 instead of 3 + 1 (Idea B). The class strings are full literals
// (including the gap-aware `basis` math) so Tailwind's JIT — which scans .ts files — emits
// them; the item `basis` and the container `gap` must stay paired.

/**
 * Fewest rows, evened out and capped at `max`.
 *   max 3:  1→1  2→2  3→3  4→2  5→3  6→3  7→3  8→3  9→3
 *   max 4:  4→4  5→3  6→3  7→4  8→4
 */
export function balancedCols(count: number, max: number): number {
    if (count <= 1) return 1;
    const rows = Math.ceil(count / max);
    return Math.min(max, Math.ceil(count / rows));
}

// Content cards — pair with `gap-6` (1.5rem): mobile 1 → sm 2 → lg balanced (max 3).
export const contentRow = 'flex flex-wrap justify-center gap-6';
const CONTENT_LG: Record<number, string> = {
    1: 'lg:basis-full',
    2: 'lg:basis-[calc(50%-0.75rem)]',
    3: 'lg:basis-[calc(33.333%-1rem)]',
};
export function contentItem(count: number): string {
    return `grow-0 basis-full sm:basis-[calc(50%-0.75rem)] ${CONTENT_LG[balancedCols(count, 3)]}`;
}

// Photo gallery — pair with `gap-3` (0.75rem): mobile 2 → sm 3 → lg balanced (max 4).
export const galleryRow = 'flex flex-wrap justify-center gap-3';
const GALLERY_LG: Record<number, string> = {
    1: 'lg:basis-[calc(50%-0.375rem)]',
    2: 'lg:basis-[calc(50%-0.375rem)]',
    3: 'lg:basis-[calc(33.333%-0.5rem)]',
    4: 'lg:basis-[calc(25%-0.5625rem)]',
};
export function galleryItem(count: number): string {
    return `grow-0 basis-[calc(50%-0.375rem)] sm:basis-[calc(33.333%-0.5rem)] ${GALLERY_LG[balancedCols(count, 4)]}`;
}
