<script setup lang="ts">
import { router, usePage } from '@inertiajs/vue3';
import { ChevronLeft, ChevronRight } from 'lucide-vue-next';
import { computed } from 'vue';

/**
 * Page navigation for a server-paginated list. Reads the paginator meta
 * (current/last page, from/to/total) and navigates with ?page=N, preserving
 * every other query param. Renders nothing when there's a single page.
 */
const props = defineProps<{
    meta: { current_page: number; last_page: number; total: number; from: number | null; to: number | null };
}>();

const page = usePage();

function go(p: number) {
    if (p < 1 || p > props.meta.last_page || p === props.meta.current_page) {
        return;
    }
    const url = new URL(page.url, 'http://localhost');
    url.searchParams.set('page', String(p));
    router.get(url.pathname + url.search, {}, { preserveState: true, preserveScroll: true });
}

// A compact window of page numbers around the current page.
const numbers = computed(() => {
    const { current_page: cur, last_page: last } = props.meta;
    const out: number[] = [];
    for (let i = Math.max(1, cur - 1); i <= Math.min(last, cur + 1); i++) {
        out.push(i);
    }
    return out;
});
</script>

<template>
    <div v-if="meta.last_page > 1" class="flex shrink-0 items-center justify-between gap-2 px-1 text-sm">
        <span class="text-muted-foreground">{{ meta.from ?? 0 }}–{{ meta.to ?? 0 }} of {{ meta.total }}</span>
        <div class="flex items-center gap-1">
            <button
                class="inline-flex size-8 items-center justify-center rounded-md border border-border text-muted-foreground transition-colors hover:bg-muted disabled:opacity-40 disabled:hover:bg-transparent"
                :disabled="meta.current_page <= 1"
                aria-label="Previous page"
                @click="go(meta.current_page - 1)"
            >
                <ChevronLeft class="size-4" />
            </button>

            <button
                v-if="numbers[0] > 1"
                class="inline-flex h-8 min-w-8 items-center justify-center rounded-md border border-border px-2 transition-colors hover:bg-muted"
                @click="go(1)"
            >
                1
            </button>
            <span v-if="numbers[0] > 2" class="px-0.5 text-muted-foreground">…</span>

            <button
                v-for="n in numbers"
                :key="n"
                class="inline-flex h-8 min-w-8 items-center justify-center rounded-md border px-2 transition-colors"
                :class="n === meta.current_page ? 'border-primary bg-primary text-primary-foreground' : 'border-border hover:bg-muted'"
                @click="go(n)"
            >
                {{ n }}
            </button>

            <span v-if="numbers[numbers.length - 1] < meta.last_page - 1" class="px-0.5 text-muted-foreground">…</span>
            <button
                v-if="numbers[numbers.length - 1] < meta.last_page"
                class="inline-flex h-8 min-w-8 items-center justify-center rounded-md border border-border px-2 transition-colors hover:bg-muted"
                @click="go(meta.last_page)"
            >
                {{ meta.last_page }}
            </button>

            <button
                class="inline-flex size-8 items-center justify-center rounded-md border border-border text-muted-foreground transition-colors hover:bg-muted disabled:opacity-40 disabled:hover:bg-transparent"
                :disabled="meta.current_page >= meta.last_page"
                aria-label="Next page"
                @click="go(meta.current_page + 1)"
            >
                <ChevronRight class="size-4" />
            </button>
        </div>
    </div>
</template>
