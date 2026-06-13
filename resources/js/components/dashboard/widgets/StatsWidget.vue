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
    <!-- The column count responds to the WIDGET's width (container query), not the
         viewport, so a narrow/mobile widget shows fewer columns instead of cramming. -->
    <div class="stat-container h-full">
        <div class="stat-grid grid h-full auto-rows-fr gap-2">
            <div
                v-for="t in tiles"
                :key="t.label"
                class="flex h-full flex-col items-center rounded-lg border border-border bg-background/40 px-2 py-2 text-center"
                style="container-type: size"
            >
                <div class="text-xs leading-tight text-muted-foreground">{{ t.label }}</div>
                <!-- The number scales with the tile, so it grows as the widget grows. -->
                <div
                    class="flex flex-1 items-center font-semibold leading-none tabular-nums"
                    :class="t.accent"
                    style="font-size: clamp(1.25rem, min(26cqh, 24cqw), 3.25rem)"
                >
                    {{ t.value }}
                </div>
            </div>
        </div>
    </div>
</template>

<style scoped>
.stat-container {
    container-type: inline-size;
}
.stat-grid {
    grid-template-columns: repeat(2, minmax(0, 1fr));
}
@container (min-width: 30rem) {
    .stat-grid {
        grid-template-columns: repeat(3, minmax(0, 1fr));
    }
}
@container (min-width: 46rem) {
    .stat-grid {
        grid-template-columns: repeat(6, minmax(0, 1fr));
    }
}
</style>
