<script setup lang="ts">
import {
    Bell,
    CalendarRange,
    CloudSun,
    DollarSign,
    FileText,
    GripVertical,
    Inbox,
    LayoutGrid,
    ListChecks,
    Map,
    X,
    type LucideIcon,
} from 'lucide-vue-next';
import { computed } from 'vue';

const props = defineProps<{ title: string; icon: string; editing?: boolean }>();
defineEmits<{ remove: [] }>();

// Catalog icon names (from App\Dashboard\DashboardWidgets) → lucide components.
const ICONS: Record<string, LucideIcon> = { LayoutGrid, Map, Inbox, FileText, CalendarRange, ListChecks, CloudSun, DollarSign, Bell };
const iconComponent = computed(() => ICONS[props.icon] ?? LayoutGrid);
</script>

<template>
    <div class="flex h-full flex-col overflow-hidden rounded-xl border border-border bg-card text-card-foreground shadow-sm">
        <div class="flex items-center justify-between gap-2 border-b border-border px-3 py-2">
            <div class="flex min-w-0 items-center gap-2">
                <component :is="iconComponent" class="size-4 shrink-0 text-muted-foreground" />
                <h2 class="truncate text-sm font-medium">{{ title }}</h2>
            </div>
            <div v-if="editing" class="flex shrink-0 items-center gap-1">
                <button
                    type="button"
                    class="rounded p-1 text-muted-foreground hover:bg-muted hover:text-foreground"
                    title="Remove widget"
                    @click="$emit('remove')"
                >
                    <X class="size-4" />
                </button>
                <span
                    class="widget-drag-handle cursor-move rounded p-1 text-muted-foreground hover:bg-muted hover:text-foreground"
                    title="Drag to move"
                >
                    <GripVertical class="size-4" />
                </span>
            </div>
        </div>
        <div class="min-h-0 flex-1 overflow-hidden p-2">
            <slot />
        </div>
    </div>
</template>
