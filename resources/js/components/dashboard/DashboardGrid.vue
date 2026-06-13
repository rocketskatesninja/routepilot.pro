<script setup lang="ts">
import { Button } from '@/components/ui/button';
import { router } from '@inertiajs/vue3';
import { GridItem, GridLayout } from 'grid-layout-plus';
import { Check, Pencil, Plus } from 'lucide-vue-next';
import { onBeforeUnmount, onMounted, ref, watch } from 'vue';
import DashboardWidgetCard from './DashboardWidgetCard.vue';
import WidgetRenderer from './WidgetRenderer.vue';

interface LayoutItem {
    i: string;
    x: number;
    y: number;
    w: number;
    h: number;
}
interface AvailableWidget {
    key: string;
    label: string;
    icon: string;
    w: number;
    h: number;
    minW: number;
    minH: number;
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
    available: AvailableWidget[];
    catalog: Record<string, CatalogMeta>;
    widgets: Record<string, unknown>;
}>();

// The interactive grid touches the DOM, so it only renders after mount — SSR
// and first paint get the static fallback stack below.
const mounted = ref(false);
onMounted(() => (mounted.value = true));

const editing = ref(false);
const layout = ref<LayoutItem[]>(props.layout.map((i) => ({ ...i })));
const available = ref<AvailableWidget[]>(props.available.map((a) => ({ ...a })));
const showPicker = ref(false);

// Adopt a server-sent layout (e.g. after our own save round-trips) without
// clobbering an in-progress edit.
watch(
    () => props.layout,
    (v) => {
        if (!editing.value) layout.value = v.map((i) => ({ ...i }));
    },
);
watch(
    () => props.available,
    (v) => {
        if (!editing.value) available.value = v.map((a) => ({ ...a }));
    },
);

const fallback: CatalogMeta = { label: '', icon: 'LayoutGrid', w: 6, h: 4, minW: 2, minH: 2 };
const meta = (key: string): CatalogMeta => props.catalog[key] ?? { ...fallback, label: key };

let saveTimer: ReturnType<typeof setTimeout> | null = null;
const persist = () => {
    if (saveTimer) clearTimeout(saveTimer);
    saveTimer = setTimeout(() => {
        router.post('/dashboard/layout', { layout: layout.value }, { preserveScroll: true, preserveState: true });
    }, 400);
};
onBeforeUnmount(() => {
    if (saveTimer) clearTimeout(saveTimer);
});

const toggleEdit = () => {
    if (editing.value) {
        showPicker.value = false;
        persist();
    }
    editing.value = !editing.value;
};

const removeWidget = (key: string) => {
    layout.value = layout.value.filter((i) => i.i !== key);
    const m = props.catalog[key];
    if (m && !available.value.some((a) => a.key === key)) {
        available.value.push({ key, label: m.label, icon: m.icon, w: m.w, h: m.h, minW: m.minW, minH: m.minH });
    }
    persist();
};

const addWidget = (w: AvailableWidget) => {
    const maxY = layout.value.reduce((acc, i) => Math.max(acc, i.y + i.h), 0);
    layout.value.push({ i: w.key, x: 0, y: maxY, w: w.w || 6, h: w.h || 4 });
    available.value = available.value.filter((a) => a.key !== w.key);
    showPicker.value = false;
    persist();
};
</script>

<template>
    <div>
        <div class="mb-3 flex items-center justify-between gap-2">
            <p v-if="editing" class="hidden text-sm text-muted-foreground sm:block">
                Drag the handle to move, drag a corner to resize — changes save automatically.
            </p>
            <span v-else></span>
            <div class="flex items-center gap-2">
                <div v-if="editing" class="relative">
                    <Button size="sm" variant="outline" :disabled="!available.length" @click="showPicker = !showPicker">
                        <Plus class="mr-1 size-4" /> Add widget
                    </Button>
                    <div
                        v-if="showPicker && available.length"
                        class="absolute right-0 z-20 mt-1 w-56 overflow-hidden rounded-lg border border-border bg-popover p-1 text-popover-foreground shadow-md"
                    >
                        <button
                            v-for="w in available"
                            :key="w.key"
                            type="button"
                            class="flex w-full items-center gap-2 rounded px-2 py-1.5 text-left text-sm hover:bg-muted"
                            @click="addWidget(w)"
                        >
                            {{ w.label }}
                        </button>
                    </div>
                </div>
                <Button size="sm" :variant="editing ? 'default' : 'outline'" @click="toggleEdit">
                    <component :is="editing ? Check : Pencil" class="mr-1 size-4" />
                    {{ editing ? 'Done' : 'Customize' }}
                </Button>
            </div>
        </div>

        <GridLayout
            v-if="mounted"
            v-model:layout="layout"
            :col-num="12"
            :row-height="60"
            :margin="[12, 12]"
            :is-draggable="editing"
            :is-resizable="editing"
            :responsive="false"
            :use-css-transforms="true"
        >
            <GridItem
                v-for="item in layout"
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
                @moved="persist"
                @resized="persist"
            >
                <DashboardWidgetCard
                    :title="meta(item.i).label"
                    :icon="meta(item.i).icon"
                    :editing="editing"
                    @remove="removeWidget(item.i)"
                >
                    <WidgetRenderer :widget-key="item.i" :data="widgets[item.i]" />
                </DashboardWidgetCard>
            </GridItem>
        </GridLayout>

        <!-- SSR / pre-mount fallback: a plain stack so the dashboard renders without JS. -->
        <div v-else class="grid gap-3 lg:grid-cols-2">
            <div
                v-for="item in layout"
                :key="item.i"
                class="min-h-[180px]"
                :class="meta(item.i).w >= 12 ? 'lg:col-span-2' : ''"
            >
                <DashboardWidgetCard :title="meta(item.i).label" :icon="meta(item.i).icon">
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
</style>
