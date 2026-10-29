<script setup lang="ts">
import {
    Banknote,
    Bell,
    Building2,
    CalendarDays,
    CalendarRange,
    CloudSun,
    DollarSign,
    FileText,
    FlaskConical,
    GripVertical,
    Inbox,
    LayoutGrid,
    ListChecks,
    Map,
    TriangleAlert,
    Waves,
    Wrench,
    X,
    type LucideIcon,
} from 'lucide-vue-next';
import { computed } from 'vue';

const props = defineProps<{ title: string; icon: string; editing?: boolean }>();
defineEmits<{ remove: [] }>();

// Catalog icon names (from App\Dashboard\DashboardWidgets) → lucide components.
const ICONS: Record<string, LucideIcon> = {
    LayoutGrid,
    Map,
    Inbox,
    FileText,
    CalendarRange,
    ListChecks,
    CloudSun,
    DollarSign,
    Bell,
    Waves,
    CalendarDays,
    Banknote,
    Building2,
    Wrench,
    FlaskConical,
    TriangleAlert,
};
const iconComponent = computed(() => ICONS[props.icon] ?? LayoutGrid);
</script>

<template>
    <div class="border-border bg-card text-card-foreground flex h-full flex-col overflow-hidden rounded-xl border shadow-sm">
        <div class="border-border flex items-center justify-between gap-2 border-b px-3 py-2">
            <div class="flex min-w-0 items-center gap-2">
                <component :is="iconComponent" class="text-muted-foreground size-4 shrink-0" />
                <h2 class="truncate text-sm font-medium">{{ title }}</h2>
            </div>
            <div v-if="editing" class="flex shrink-0 items-center gap-1">
                <button
                    type="button"
                    class="widget-tool text-muted-foreground hover:bg-muted hover:text-foreground rounded p-1"
                    title="Remove widget"
                    aria-label="Remove widget"
                    @click="$emit('remove')"
                >
                    <X class="size-4" />
                </button>
                <span
                    class="widget-tool widget-drag-handle text-muted-foreground hover:bg-muted hover:text-foreground cursor-move rounded p-1"
                    title="Drag to move"
                    aria-label="Drag to move"
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
