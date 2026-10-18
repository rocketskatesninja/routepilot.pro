import { visitQuery } from '@/lib/listQuery';
import { onBeforeUnmount, ref, watch } from 'vue';

/**
 * A debounced search box bound to a server-paginated Inertia list. Owns the
 * query ref + the debounce + the navigation, and clears its timer on unmount.
 * A new search keeps the sort but starts from page 1 with nothing selected.
 * `extra()` supplies any sibling params (e.g. the active tab).
 */
export function useListSearch(initial: string, opts: { extra?: () => Record<string, string | number | undefined>; delay?: number } = {}) {
    const search = ref(initial);
    let timer: ReturnType<typeof setTimeout> | undefined;
    watch(search, (value) => {
        clearTimeout(timer);
        timer = setTimeout(() => {
            visitQuery(
                { search: value || undefined, page: undefined, selected: undefined, selected_type: undefined, ...(opts.extra?.() ?? {}) },
                { replace: true },
            );
        }, opts.delay ?? 300);
    });
    onBeforeUnmount(() => clearTimeout(timer));
    return { search };
}
