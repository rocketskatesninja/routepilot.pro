<script setup lang="ts">
import { visitQuery } from '@/lib/listQuery';
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
    // A new order invalidates the current page.
    visitQuery({ sort: props.sortKey, dir: next, page: undefined }, { replace: true });
}
</script>

<template>
    <th class="px-4 py-2 font-medium">
        <button
            type="button"
            class="hover:text-foreground inline-flex items-center gap-1 transition-colors"
            :class="align === 'right' ? 'flex-row-reverse' : ''"
            @click="toggle"
        >
            <slot />
            <component :is="icon" class="size-3.5 shrink-0" :class="isActive ? 'text-foreground' : 'opacity-40'" />
        </button>
    </th>
</template>
