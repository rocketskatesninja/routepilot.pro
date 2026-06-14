<script setup lang="ts">
import { router, usePage } from '@inertiajs/vue3';
import { ArrowDown, ArrowUp, ChevronsUpDown } from 'lucide-vue-next';
import { computed } from 'vue';

/**
 * A clickable table header that sorts a server-paginated list. Clicking sets
 * ?sort=<key>&dir=<asc|desc> (flipping direction when already active) while
 * preserving every other query param; the caret reflects the server's active
 * sort. Drop in place of a <th>; class/colspan fall through to the root <th>.
 */
const props = defineProps<{
    sortKey: string;
    active: { key: string; dir: string } | null;
    align?: 'left' | 'right';
}>();

const isActive = computed(() => props.active?.key === props.sortKey);
const dir = computed(() => (isActive.value ? (props.active?.dir ?? null) : null));
const icon = computed(() => (dir.value === 'asc' ? ArrowUp : dir.value === 'desc' ? ArrowDown : ChevronsUpDown));

function toggle() {
    const next = isActive.value && dir.value === 'asc' ? 'desc' : 'asc';
    const url = new URL(usePage().url, 'http://localhost');
    url.searchParams.set('sort', props.sortKey);
    url.searchParams.set('dir', next);
    url.searchParams.delete('page'); // a new order invalidates the current page
    router.get(url.pathname + url.search, {}, { preserveState: true, preserveScroll: true, replace: true });
}
</script>

<template>
    <th class="px-4 py-2 font-medium">
        <button
            type="button"
            class="inline-flex items-center gap-1 transition-colors hover:text-foreground"
            :class="align === 'right' ? 'flex-row-reverse' : ''"
            @click="toggle"
        >
            <slot />
            <component :is="icon" class="size-3.5 shrink-0" :class="isActive ? 'text-foreground' : 'opacity-40'" />
        </button>
    </th>
</template>
