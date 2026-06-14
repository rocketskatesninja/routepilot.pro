import { router } from '@inertiajs/vue3';
import { onBeforeUnmount, ref, watch } from 'vue';

/**
 * A debounced search box bound to a server-paginated Inertia list. Owns the
 * query ref + the debounce + the canonical navigation, and clears its timer on
 * unmount. `extra()` supplies any sibling params (e.g. the active tab).
 */
export function useListSearch(
    url: string,
    initial: string,
    opts: { extra?: () => Record<string, string | number | undefined>; delay?: number } = {},
) {
    const search = ref(initial);
    let timer: ReturnType<typeof setTimeout> | undefined;
    watch(search, (value) => {
        clearTimeout(timer);
        timer = setTimeout(() => {
            router.get(url, { search: value || undefined, ...(opts.extra?.() ?? {}) }, { preserveState: true, replace: true, preserveScroll: true });
        }, opts.delay ?? 300);
    });
    onBeforeUnmount(() => clearTimeout(timer));
    return { search };
}
