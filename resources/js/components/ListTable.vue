<script setup lang="ts" generic="T">
import Pagination from '@/components/Pagination.vue';
import { useFitRows } from '@/composables/useFitRows';
import type { Paginated } from '@/types/pagination';

/**
 * The shared back-office list shell: the bordered table that fits the viewport
 * (owns the row-fit measurement), a clickable selectable row per item, an
 * empty-state row, and the pager. Pages supply only the header cells (#head),
 * the per-row cells (#row="{ item }"), and the empty message (#empty).
 */
const props = defineProps<{
    meta: Paginated<T>;
    columns: number;
    rowKey: (item: T) => string | number;
    selectedKey?: string | number | null;
}>();

defineEmits<{ select: [T] }>();

const { listRef } = useFitRows(
    () => props.meta.per_page,
    () => props.meta.total,
);
</script>

<template>
    <div class="flex min-h-0 flex-col gap-3">
        <div ref="listRef" class="overflow-hidden rounded-xl border border-border">
            <table class="w-full text-sm">
                <thead class="bg-muted/50 text-left text-muted-foreground">
                    <tr>
                        <slot name="head" />
                    </tr>
                </thead>
                <tbody>
                    <tr
                        v-for="item in meta.data"
                        :key="rowKey(item)"
                        class="cursor-pointer border-t border-border transition-colors hover:bg-muted/40"
                        :class="{ 'bg-muted/60': selectedKey != null && rowKey(item) === selectedKey }"
                        @click="$emit('select', item)"
                    >
                        <slot name="row" :item="item" />
                    </tr>
                    <tr v-if="meta.data.length === 0">
                        <td :colspan="columns" class="px-4 py-10 text-center text-muted-foreground">
                            <slot name="empty" />
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
        <Pagination :meta="meta" />
    </div>
</template>
