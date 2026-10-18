import { router, usePage } from '@inertiajs/vue3';

/**
 * Navigate a server-driven list by changing only the given query params and
 * keeping every other one, so selecting a row, closing its panel, paging,
 * searching or re-sorting never resets the rest (sort, page, search, tab).
 * An undefined, null or empty value removes that param.
 */
export function visitQuery(patch: Record<string, string | number | null | undefined>, options: { replace?: boolean } = {}): void {
    const url = new URL(usePage().url, 'http://localhost');
    for (const [key, value] of Object.entries(patch)) {
        if (value === undefined || value === null || value === '') url.searchParams.delete(key);
        else url.searchParams.set(key, String(value));
    }
    router.get(url.pathname + url.search, {}, { preserveState: true, preserveScroll: true, replace: options.replace ?? false });
}
