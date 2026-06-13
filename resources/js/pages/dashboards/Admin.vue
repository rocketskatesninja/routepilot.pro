<script setup lang="ts">
import DashboardGrid from '@/components/dashboard/DashboardGrid.vue';
import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem } from '@/types';
import { Head, router } from '@inertiajs/vue3';
import { Check, Pencil, Plus } from 'lucide-vue-next';
import { onBeforeUnmount, ref, watch } from 'vue';

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

const breadcrumbs: BreadcrumbItem[] = [{ title: 'Dashboard', href: '/dashboard' }];

// The page owns the customization state so the controls can live in the header.
const editing = ref(false);
const pickerOpen = ref(false);
const layout = ref<LayoutItem[]>(props.layout.map((i) => ({ ...i })));
const available = ref<AvailableWidget[]>(props.available.map((a) => ({ ...a })));

// Adopt a server-sent layout (after our own save round-trips) without clobbering
// an in-progress edit.
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

const onLayoutUpdate = (next: LayoutItem[]) => {
    layout.value = next;
    persist();
};

const toggleEdit = () => {
    if (editing.value) {
        pickerOpen.value = false;
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
    layout.value = [...layout.value, { i: w.key, x: 0, y: maxY, w: w.w || 6, h: w.h || 4 }];
    available.value = available.value.filter((a) => a.key !== w.key);
    pickerOpen.value = false;
    persist();
};
</script>

<template>
    <Head title="Dashboard" />
    <AppLayout :breadcrumbs="breadcrumbs">
        <template #actions>
            <p v-if="editing" class="mr-1 hidden text-sm text-muted-foreground lg:block">
                Drag a widget's handle to move it · drag a corner to resize
            </p>
            <div v-if="editing" class="relative">
                <Button size="sm" variant="outline" :disabled="!available.length" @click="pickerOpen = !pickerOpen">
                    <Plus class="mr-1 size-4" /> Add widget
                </Button>
                <div
                    v-if="pickerOpen && available.length"
                    class="absolute right-0 z-30 mt-1 w-56 overflow-hidden rounded-lg border border-border bg-popover p-1 text-popover-foreground shadow-md"
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
        </template>

        <div class="flex h-full flex-1 flex-col gap-4 p-4">
            <DashboardGrid
                :layout="layout"
                :editing="editing"
                :catalog="catalog"
                :widgets="widgets"
                @update:layout="onLayoutUpdate"
                @remove="removeWidget"
            />
        </div>
    </AppLayout>
</template>
