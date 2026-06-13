<script setup lang="ts">
import { weatherDescribe } from '@/composables/useWeatherIcon';

interface Day {
    date: string;
    dow: string;
    day: number;
    total: number;
    completed: number;
    is_today: boolean;
    code: number | null;
}

defineProps<{ data: { days: Day[] } }>();
</script>

<template>
    <div class="flex h-full items-stretch gap-1.5">
        <div
            v-for="d in data.days"
            :key="d.date"
            class="flex flex-1 flex-col items-center justify-center gap-1 rounded-lg border p-2 text-center"
            :class="d.is_today ? 'border-primary/50 bg-primary/5' : 'border-border'"
        >
            <div class="text-xs font-medium uppercase text-muted-foreground">{{ d.dow }}</div>
            <div class="text-lg font-semibold tabular-nums">{{ d.day }}</div>
            <component :is="weatherDescribe(d.code).icon" v-if="d.code !== null" class="size-4 text-muted-foreground" />
            <div
                v-if="d.total"
                class="text-xs tabular-nums"
                :class="d.completed === d.total ? 'text-emerald-600 dark:text-emerald-400' : 'text-muted-foreground'"
            >
                {{ d.completed }}/{{ d.total }}
            </div>
            <div v-else class="text-xs text-muted-foreground/40">—</div>
        </div>
    </div>
</template>
