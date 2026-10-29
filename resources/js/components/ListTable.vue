<script setup lang="ts" generic="T">
import Pagination from '@/components/Pagination.vue';
import { useFitRows } from '@/composables/useFitRows';
import type { Paginated } from '@/types/pagination';
import { computed } from 'vue';

/**
 * The shared back-office list shell: the bordered table that fits the viewport
 * (owns the row-fit measurement), a clickable selectable row per item, an
 * empty-state row, and the pager. Pages supply only the header cells (#head),
 * the per-row cells (#row="{ item }"), and the empty message (#empty).
 * With v-model:checked, each row gets a tick box (and the header a tick-all for
 * the page) so the page can act on several at once.
 */
const props = defineProps<{
    meta: Paginated<T>;
    columns: number;
    rowKey: (item: T) => string | number;
    selectedKey?: string | number | null;
    checked?: (string | number)[];
}>();

const emit = defineEmits<{ select: [T]; 'update:checked': [(string | number)[]] }>();

const ticking = computed(() => props.checked !== undefined);
const pageKeys = computed(() => props.meta.data.map(props.rowKey));
const allTicked = computed(() => pageKeys.value.length > 0 && pageKeys.value.every((k) => props.checked?.includes(k)));
const tick = (key: string | number) =>
    emit('update:checked', props.checked?.includes(key) ? props.checked.filter((k) => k !== key) : [...(props.checked ?? []), key]);
const tickAll = () =>
    emit(
        'update:checked',
        allTicked.value
            ? (props.checked ?? []).filter((k) => !pageKeys.value.includes(k))
            : [...new Set([...(props.checked ?? []), ...pageKeys.value])],
    );

const { listRef } = useFitRows(
    () => props.meta.per_page,
    () => props.meta.total,
);
</script>

<template>
    <div class="flex min-h-0 flex-col gap-3">
        <div ref="listRef" class="border-border overflow-hidden rounded-xl border">
            <table class="w-full text-sm">
                <thead class="bg-muted/50 text-muted-foreground text-left">
                    <tr>
                        <th v-if="ticking" class="w-10 px-3 py-2">
                            <input
                                type="checkbox"
                                class="border-input accent-primary size-4 rounded align-middle"
                                :checked="allTicked"
                                aria-label="Select all on this page"
                                @change="tickAll"
                            />
                        </th>
                        <slot name="head" />
                    </tr>
                </thead>
                <tbody>
                    <tr
                        v-for="item in meta.data"
                        :key="rowKey(item)"
                        class="border-border hover:bg-muted/40 cursor-pointer border-t transition-colors"
                        :class="{ 'bg-muted/60': selectedKey != null && rowKey(item) === selectedKey }"
                        @click="emit('select', item)"
                    >
                        <td v-if="ticking" class="w-10 px-3" @click.stop>
                            <input
                                type="checkbox"
                                class="border-input accent-primary size-4 rounded align-middle"
                                :checked="checked?.includes(rowKey(item))"
                                aria-label="Select"
                                @change="tick(rowKey(item))"
                            />
                        </td>
                        <slot name="row" :item="item" />
                    </tr>
                    <tr v-if="meta.data.length === 0">
                        <td :colspan="columns + (ticking ? 1 : 0)" class="text-muted-foreground px-4 py-10 text-center">
                            <slot name="empty" />
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
        <Pagination :meta="meta" />
    </div>
</template>
