<script setup lang="ts">
import DashboardGrid from '@/components/dashboard/DashboardGrid.vue';
import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem } from '@/types';
import { Head, router } from '@inertiajs/vue3';
import { Check, LayoutGrid, Monitor, Pencil, Plus, Smartphone } from 'lucide-vue-next';
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue';

interface LayoutItem {
    i: string;
    x: number;
    y: number;
    w: number;
    h: number;
}
interface PaletteWidget {
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
type Mode = 'desktop' | 'mobile';

const props = defineProps<{
    layouts: Record<Mode, LayoutItem[]>;
    palette: PaletteWidget[];
    catalog: Record<string, CatalogMeta>;
    widgets: Record<string, unknown>;
}>();

const breadcrumbs: BreadcrumbItem[] = [{ title: 'Dashboard', href: '/dashboard' }];

// The page owns the customization state so the controls can live in the header.
const editing = ref(false);
const pickerOpen = ref(false);

// Which layout we show/edit: defaults to the actual viewport, but in edit mode
// the user can switch to design the other one.
const actualMode = ref<Mode>('desktop');
const deviceMode = ref<Mode>('desktop');

const layouts = ref<Record<Mode, LayoutItem[]>>({
    desktop: props.layouts.desktop.map((i) => ({ ...i })),
    mobile: props.layouts.mobile.map((i) => ({ ...i })),
});

// Adopt server-sent layouts (after our own saves round-trip) unless mid-edit.
watch(
    () => props.layouts,
    (v) => {
        if (!editing.value) {
            layouts.value = { desktop: v.desktop.map((i) => ({ ...i })), mobile: v.mobile.map((i) => ({ ...i })) };
        }
    },
);

const activeLayout = computed(() => layouts.value[deviceMode.value]);
const mobileView = computed(() => deviceMode.value === 'mobile');
const addable = computed(() => props.palette.filter((p) => !activeLayout.value.some((i) => i.i === p.key)));

let saveTimer: ReturnType<typeof setTimeout> | null = null;
const persist = () => {
    const mode = deviceMode.value;
    if (saveTimer) clearTimeout(saveTimer);
    saveTimer = setTimeout(() => {
        router.post('/dashboard/layout', { mode, layout: layouts.value[mode] }, { preserveScroll: true, preserveState: true });
    }, 400);
};

const onLayoutUpdate = (next: LayoutItem[]) => {
    layouts.value[deviceMode.value] = next;
    persist();
};

const toggleEdit = () => {
    if (editing.value) {
        pickerOpen.value = false;
        deviceMode.value = actualMode.value; // back to the real viewport's layout
        persist();
    }
    editing.value = !editing.value;
};

const setMode = (m: Mode) => {
    if (m === deviceMode.value) {
        return;
    }
    pickerOpen.value = false;
    deviceMode.value = m;
};

const removeWidget = (key: string) => {
    layouts.value[deviceMode.value] = activeLayout.value.filter((i) => i.i !== key);
    persist();
};

const addWidget = (w: PaletteWidget) => {
    const maxY = activeLayout.value.reduce((acc, i) => Math.max(acc, i.y + i.h), 0);
    const width = mobileView.value ? 12 : w.w || 6;
    layouts.value[deviceMode.value] = [...activeLayout.value, { i: w.key, x: 0, y: maxY, w: width, h: w.h || 4 }];
    pickerOpen.value = false;
    persist();
};

// Track the actual viewport so the dashboard shows the matching layout.
let mql: MediaQueryList | null = null;
const syncActualMode = () => {
    actualMode.value = mql?.matches ? 'mobile' : 'desktop';
    if (!editing.value) {
        deviceMode.value = actualMode.value;
    }
};
onMounted(() => {
    if (typeof window === 'undefined' || !window.matchMedia) {
        return;
    }
    mql = window.matchMedia('(max-width: 767px)');
    syncActualMode();
    mql.addEventListener('change', syncActualMode);
});
onBeforeUnmount(() => {
    mql?.removeEventListener('change', syncActualMode);
    if (saveTimer) {
        clearTimeout(saveTimer);
    }
});
</script>

<template>
    <Head title="Dashboard" />
    <AppLayout :breadcrumbs="breadcrumbs">
        <template #actions>
            <p v-if="editing" class="mr-1 hidden text-sm text-muted-foreground lg:block">
                Editing the {{ deviceMode }} layout · drag to move, corner to resize
            </p>

            <!-- Choose which layout to edit (desktop only; on a phone you edit the mobile layout directly) -->
            <div v-if="editing" class="hidden items-center gap-0.5 rounded-md border border-border p-0.5 sm:flex">
                <button
                    type="button"
                    class="rounded p-1.5 transition-colors"
                    :class="deviceMode === 'desktop' ? 'bg-muted text-foreground' : 'text-muted-foreground hover:text-foreground'"
                    title="Desktop layout"
                    @click="setMode('desktop')"
                >
                    <Monitor class="size-4" />
                </button>
                <button
                    type="button"
                    class="rounded p-1.5 transition-colors"
                    :class="deviceMode === 'mobile' ? 'bg-muted text-foreground' : 'text-muted-foreground hover:text-foreground'"
                    title="Mobile layout"
                    @click="setMode('mobile')"
                >
                    <Smartphone class="size-4" />
                </button>
            </div>

            <div v-if="editing" class="relative">
                <Button size="sm" variant="outline" :disabled="!addable.length" @click="pickerOpen = !pickerOpen">
                    <Plus class="size-4 sm:mr-1" /> <span class="hidden sm:inline">Add widget</span>
                </Button>
                <div
                    v-if="pickerOpen && addable.length"
                    class="fixed left-1/2 top-16 z-30 w-[calc(100vw-2rem)] max-w-xs -translate-x-1/2 overflow-hidden rounded-lg border border-border bg-popover p-1 text-popover-foreground shadow-md sm:absolute sm:left-auto sm:right-0 sm:top-auto sm:mt-1 sm:w-56 sm:translate-x-0"
                >
                    <button
                        v-for="w in addable"
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
        </template>

        <div class="flex h-full flex-1 flex-col gap-4 p-4">
            <!-- Mobile layouts are designed/shown in a phone-width frame. -->
            <div :class="mobileView ? 'mx-auto w-full max-w-[26rem]' : 'w-full'">
                <div
                    v-if="mobileView && editing"
                    class="mb-2 rounded-md border border-dashed border-border px-3 py-1.5 text-center text-xs text-muted-foreground"
                >
                    Mobile layout
                </div>

                <div
                    v-if="!activeLayout.length && !editing"
                    class="flex flex-col items-center justify-center gap-3 rounded-xl border border-dashed border-border py-16 text-center text-muted-foreground"
                >
                    <LayoutGrid class="size-8 opacity-50" />
                    <p class="text-sm">Your dashboard is empty.</p>
                    <Button size="sm" variant="outline" @click="toggleEdit"><Pencil class="mr-1 size-4" /> Customize</Button>
                </div>
                <DashboardGrid
                    v-else
                    :key="deviceMode"
                    :layout="activeLayout"
                    :editing="editing"
                    :catalog="catalog"
                    :widgets="widgets"
                    @update:layout="onLayoutUpdate"
                    @remove="removeWidget"
                />
            </div>
        </div>
    </AppLayout>
</template>
