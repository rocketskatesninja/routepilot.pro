<script setup lang="ts">
import { computed } from 'vue';

interface Stats {
    today_stops: number;
    completed_today: number;
    remaining_today: number;
    agents: number;
    customers: number;
    pools: number;
}

const props = defineProps<{ data: Stats }>();

const tiles = computed(() => [
    { label: 'Stops today', value: props.data.today_stops, accent: '' },
    { label: 'Completed', value: props.data.completed_today, accent: 'text-emerald-600 dark:text-emerald-400' },
    { label: 'Remaining', value: props.data.remaining_today, accent: '' },
    { label: 'Agents', value: props.data.agents, accent: '' },
    { label: 'Customers', value: props.data.customers, accent: '' },
    { label: 'Pools', value: props.data.pools, accent: '' },
]);
</script>

<template>
    <div class="grid h-full auto-rows-fr grid-cols-2 gap-2 sm:grid-cols-3 lg:grid-cols-6">
        <div
            v-for="t in tiles"
            :key="t.label"
            class="flex h-full flex-col items-center rounded-lg border border-border bg-background/40 px-3 py-2 text-center"
            style="container-type: size"
        >
            <div class="text-xs text-muted-foreground">{{ t.label }}</div>
            <!-- The number scales with the tile (container-query units), so it grows as the widget grows. -->
            <div
                class="flex flex-1 items-center font-semibold leading-none tabular-nums"
                :class="t.accent"
                style="font-size: clamp(1.25rem, min(26cqh, 24cqw), 3.25rem)"
            >
                {{ t.value }}
            </div>
        </div>
    </div>
</template>
