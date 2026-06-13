<script setup lang="ts">
import { weatherDescribe } from '@/composables/useWeatherIcon';
import { CloudSun, Droplets, Wind } from 'lucide-vue-next';
import { computed, onBeforeUnmount, onMounted, ref } from 'vue';

interface Current {
    temp: number;
    feels: number;
    humidity: number;
    wind: number;
    code: number;
}
interface Hour {
    hour: string;
    temp: number;
    code: number;
    precip: number;
}
interface Day {
    date: string;
    dow: string;
    high: number;
    low: number;
    code: number;
    precip: number;
}

defineProps<{ data: { current: Current; hours: Hour[]; days: Day[] } | null }>();

// Show the most-detail-first; as the widget gets shorter the hourly strip drops
// out before the daily forecast does.
const root = ref<HTMLElement | null>(null);
const availH = ref(Infinity);
let ro: ResizeObserver | null = null;
onMounted(() => {
    if (!root.value || typeof ResizeObserver === 'undefined') {
        return;
    }
    ro = new ResizeObserver((entries) => {
        availH.value = entries[0].contentRect.height;
    });
    ro.observe(root.value);
});
onBeforeUnmount(() => ro?.disconnect());

const showDaily = computed(() => availH.value >= 135);
const showHourly = computed(() => availH.value >= 215);
</script>

<template>
    <div ref="root" class="h-full">
        <div v-if="data" class="flex h-full flex-col gap-3">
            <!-- Current conditions (always shown) -->
            <div class="flex items-center gap-3">
                <component :is="weatherDescribe(data.current.code).icon" class="size-10 shrink-0 text-primary" />
                <div class="min-w-0">
                    <div class="text-2xl font-semibold tabular-nums">{{ data.current.temp }}°</div>
                    <div class="truncate text-xs text-muted-foreground">{{ weatherDescribe(data.current.code).label }} · feels {{ data.current.feels }}°</div>
                </div>
                <div class="ml-auto space-y-0.5 text-right text-xs text-muted-foreground">
                    <div class="flex items-center justify-end gap-1"><Droplets class="size-3" /> {{ data.current.humidity }}%</div>
                    <div class="flex items-center justify-end gap-1"><Wind class="size-3" /> {{ data.current.wind }} mph</div>
                </div>
            </div>

            <!-- Hourly strip (first to hide when short) -->
            <div v-show="showHourly && data.hours.length" class="flex gap-3 overflow-x-auto pb-1">
                <div v-for="h in data.hours" :key="h.hour" class="flex shrink-0 flex-col items-center gap-0.5 text-center">
                    <div class="text-xs text-muted-foreground">{{ h.hour }}</div>
                    <component :is="weatherDescribe(h.code).icon" class="size-5 text-muted-foreground" />
                    <div class="text-xs font-medium tabular-nums">{{ h.temp }}°</div>
                    <div class="text-[10px] tabular-nums" :class="h.precip >= 20 ? 'text-sky-500' : 'text-transparent'">{{ h.precip }}%</div>
                </div>
            </div>

            <!-- 5-day forecast -->
            <div v-show="showDaily" class="mt-auto grid grid-cols-5 gap-1">
                <div v-for="d in data.days.slice(0, 5)" :key="d.date" class="flex flex-col items-center gap-0.5 rounded-md py-1 text-center">
                    <div class="text-xs text-muted-foreground">{{ d.dow }}</div>
                    <component :is="weatherDescribe(d.code).icon" class="size-5 text-muted-foreground" />
                    <div class="text-xs tabular-nums"><span class="font-medium">{{ d.high }}°</span> <span class="text-muted-foreground">{{ d.low }}°</span></div>
                </div>
            </div>
        </div>

        <div v-else class="flex h-full flex-col items-center justify-center gap-2 px-6 text-center text-sm text-muted-foreground">
            <CloudSun class="size-6 opacity-50" />
            <p>Set a business address in Company settings to show local weather.</p>
        </div>
    </div>
</template>
