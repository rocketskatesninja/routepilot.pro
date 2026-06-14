import { router } from '@inertiajs/vue3';
import { onBeforeUnmount, onMounted, ref } from 'vue';

/**
 * Size a server-paginated list so its rows fill the viewport without a
 * scrollbar. It measures the real row + header heights of the referenced table
 * container, works out how many rows fit in the space below it, and — when that
 * differs from the current page size — re-requests the list with `?perPage=N`.
 * The chosen size is also cached in a cookie so the next request seeds close to
 * the right count (no reflow on search / tab changes). Re-measures on resize and
 * after every Inertia visit.
 */
export function useFitRows(currentPerPage: () => number, total: () => number) {
    const listRef = ref<HTMLElement | null>(null);
    let raf = 0;

    const clamp = (n: number): number => Math.max(5, Math.min(40, n));

    function measure() {
        const node = listRef.value;
        if (!node || typeof window === 'undefined') {
            return;
        }
        const row = node.querySelector('tbody tr') as HTMLElement | null;
        const thead = node.querySelector('thead') as HTMLElement | null;
        if (!row) {
            return;
        }
        const rowH = row.getBoundingClientRect().height;
        if (rowH <= 0) {
            return;
        }
        const theadH = thead?.getBoundingClientRect().height ?? 0;
        const top = node.getBoundingClientRect().top;
        // Reserve room for the pagination bar + the page's bottom padding + a
        // little slack so a boundary case never produces a 1-row overflow.
        const avail = window.innerHeight - top - theadH - 76;
        const fit = clamp(Math.floor(avail / rowH));

        // Seed future requests so query-replacing navigations don't reflow.
        document.cookie = `rp_per_page=${fit};path=/;max-age=31536000;samesite=lax`;

        const cur = currentPerPage();
        if (Math.abs(fit - cur) <= 1) {
            return; // deadzone — avoids oscillation
        }
        if (fit > cur && total() <= cur) {
            return; // already showing everything; no need to grow
        }
        const url = new URL(window.location.href);
        url.searchParams.set('perPage', String(fit));
        router.get(url.pathname + url.search, {}, { preserveState: true, preserveScroll: true, replace: true });
    }

    function schedule() {
        cancelAnimationFrame(raf);
        // Two frames: let the new layout settle before measuring.
        raf = requestAnimationFrame(() => requestAnimationFrame(measure));
    }

    let offFinish: (() => void) | undefined;
    onMounted(() => {
        schedule();
        window.addEventListener('resize', schedule);
        offFinish = router.on('finish', schedule);
    });
    onBeforeUnmount(() => {
        cancelAnimationFrame(raf);
        window.removeEventListener('resize', schedule);
        offFinish?.();
    });

    return { listRef };
}
