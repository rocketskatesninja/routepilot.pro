<script setup lang="ts">
import { GridItem, GridLayout } from 'grid-layout-plus';
import { computed, onMounted, ref, watch } from 'vue';
import DashboardWidgetCard from './DashboardWidgetCard.vue';
import WidgetRenderer from './WidgetRenderer.vue';

interface LayoutItem {
    i: string;
    x: number;
    y: number;
    w: number;
    h: number;
}
interface CatalogMeta {
    label: string;
    icon: string;
    w: number;
    h: number;
    minW: number;
    minH: number;
}

const props = defineProps<{
    layout: LayoutItem[];
    editing: boolean;
    catalog: Record<string, CatalogMeta>;
    widgets: Record<string, unknown>;
    // [horizontal, vertical] gap. On a phone the layout is a single full-width
    // column, so the horizontal margin is dropped (the page padding is the gutter).
    margin?: [number, number];
}>();
const emit = defineEmits<{ 'update:layout': [LayoutItem[]]; remove: [string] }>();

// The interactive grid touches the DOM, so it only renders after mount — SSR
// and first paint get the static fallback stack below.
const mounted = ref(false);
onMounted(() => (mounted.value = true));

// grid-layout-plus mutates its layout array in place while dragging, so it gets
// a local working copy; the result is surfaced on drag/resize end.
const work = ref<LayoutItem[]>(props.layout.map((i) => ({ ...i })));
watch(
    () => props.layout,
    (v) => {
        work.value = v.map((i) => ({ ...i }));
    },
);

const fallback: CatalogMeta = { label: '', icon: 'LayoutGrid', w: 6, h: 4, minW: 2, minH: 2 };
const meta = (key: string): CatalogMeta => props.catalog[key] ?? { ...fallback, label: key };

// Stack order follows the layout's reading order (top row first, then left→right).
const ordered = computed(() => [...work.value].sort((a, b) => a.y - b.y || a.x - b.x));

const commit = () =>
    emit(
        'update:layout',
        work.value.map((i) => ({ ...i })),
    );
</script>

<template>
    <div>
        <!-- Both desktop and phone use the same 12-col drag grid — on a phone the
             layout is full-width widgets, so it reads as a stack you can still
             drag to reorder and drag the corner to resize. -->
        <GridLayout
            v-if="mounted"
            v-model:layout="work"
            :col-num="12"
            :row-height="60"
            :margin="props.margin ?? [12, 12]"
            :is-draggable="editing"
            :is-resizable="editing"
            :responsive="false"
            :use-css-transforms="true"
        >
            <GridItem
                v-for="item in work"
                :key="item.i"
                :x="item.x"
                :y="item.y"
                :w="item.w"
                :h="item.h"
                :i="item.i"
                :min-w="meta(item.i).minW"
                :min-h="meta(item.i).minH"
                drag-allow-from=".widget-drag-handle"
                :class="editing ? 'rounded-xl ring-1 ring-primary/40' : ''"
                @moved="commit"
                @resized="commit"
            >
                <DashboardWidgetCard :title="meta(item.i).label" :icon="meta(item.i).icon" :editing="editing" @remove="emit('remove', item.i)">
                    <WidgetRenderer :widget-key="item.i" :data="widgets[item.i]" />
                </DashboardWidgetCard>
            </GridItem>
        </GridLayout>

        <!-- SSR / pre-mount only: a static single-column stack until the interactive
             grid hydrates, so first paint has no layout shift and no clipping. -->
        <div v-else class="space-y-3">
            <div v-for="item in ordered" :key="item.i" class="h-64">
                <DashboardWidgetCard :title="meta(item.i).label" :icon="meta(item.i).icon" :editing="editing" @remove="emit('remove', item.i)">
                    <WidgetRenderer :widget-key="item.i" :data="widgets[item.i]" />
                </DashboardWidgetCard>
            </div>
        </div>
    </div>
</template>

<style>
/* grid-layout-plus visual tuning to match the ops theme. */
.vgl-item:not(.vgl-item--placeholder) {
    background: transparent;
    border: none;
}
.vgl-item--placeholder {
    background: hsl(var(--primary) / 0.15);
    border-radius: 0.75rem;
}
.vgl-item__resizer {
    z-index: 10;
}
/* A bigger, easier-to-grab resize target on touch screens (phones/tablets). */
@media (pointer: coarse) {
    .vgl-item__resizer {
        width: 32px;
        height: 32px;
    }
}
</style>
