<script setup lang="ts">
import EmptyState from '@/components/EmptyState.vue';
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
const availW = ref(Infinity);
let ro: ResizeObserver | null = null;
onMounted(() => {
    if (!root.value || typeof ResizeObserver === 'undefined') {
        return;
    }
    ro = new ResizeObserver((entries) => {
        availH.value = entries[0].contentRect.height;
        availW.value = entries[0].contentRect.width;
    });
    ro.observe(root.value);
});
onBeforeUnmount(() => ro?.disconnect());

const showDaily = computed(() => availH.value >= 135);
const showHourly = computed(() => availH.value >= 215);

// The hourly strip GROWS to fill the space: its icons + text scale with the band
// height (≈ widget minus the current + daily rows), and bigger cells mean fewer
// hours fit, so the list shortens as the widget expands.
const hourlyBand = computed(() => Math.max(0, availH.value - 150));
const hourIcon = computed(() => Math.round(Math.max(20, Math.min(56, hourlyBand.value * 0.42))));
const hourTemp = computed(() => Math.round(Math.max(12, Math.min(26, hourlyBand.value * 0.2))));
const hourLabel = computed(() => Math.round(Math.max(11, Math.min(18, hourlyBand.value * 0.14))));
const hourSmall = computed(() => Math.max(9, hourLabel.value - 2));
const hourCount = computed(() => Math.max(3, Math.min(8, Math.floor(availW.value / Math.max(42, hourIcon.value * 1.4)))));

// The daily forecast drops trailing days on a narrow widget (last day first).
const dayCount = computed(() => Math.max(3, Math.min(5, Math.floor(availW.value / 52))));
</script>

<template>
    <div ref="root" class="h-full">
        <div v-if="data" class="flex h-full flex-col gap-2">
            <!-- Current conditions (always shown) -->
            <div class="flex shrink-0 items-center gap-3">
                <component :is="weatherDescribe(data.current.code).icon" class="size-10 shrink-0 text-primary" />
                <div class="min-w-0">
                    <div class="text-2xl font-semibold tabular-nums">{{ data.current.temp }}°</div>
                    <div class="truncate text-xs text-muted-foreground">
                        {{ weatherDescribe(data.current.code).label }} · feels {{ data.current.feels }}°
                    </div>
                </div>
                <div class="ml-auto space-y-0.5 text-right text-xs text-muted-foreground">
                    <div class="flex items-center justify-end gap-1"><Droplets class="size-3" /> {{ data.current.humidity }}%</div>
                    <div class="flex items-center justify-end gap-1"><Wind class="size-3" /> {{ data.current.wind }} mph</div>
                </div>
            </div>

            <!-- Hourly strip: expands to fill the space between current + daily;
                 its cells spread vertically so there's no blank gap. First to hide when short. -->
            <div v-show="showHourly && data.hours.length" class="flex min-h-0 flex-1">
                <div class="flex h-full w-full items-stretch gap-1">
                    <div
                        v-for="h in data.hours.slice(0, hourCount)"
                        :key="h.hour"
                        class="flex min-w-0 flex-1 flex-col items-center justify-evenly text-center leading-none"
                    >
                        <div class="truncate text-muted-foreground" :style="{ fontSize: hourLabel + 'px' }">{{ h.hour }}</div>
                        <component :is="weatherDescribe(h.code).icon" :size="hourIcon" class="text-muted-foreground" />
                        <div class="font-medium tabular-nums" :style="{ fontSize: hourTemp + 'px' }">{{ h.temp }}°</div>
                        <div
                            class="tabular-nums"
                            :style="{ fontSize: hourSmall + 'px' }"
                            :class="h.precip >= 20 ? 'text-sky-500' : 'text-transparent'"
                        >
                            {{ h.precip }}%
                        </div>
                    </div>
                </div>
            </div>

            <!-- Daily forecast: compact at the bottom (fills only when the hourly is
                 hidden); drops trailing days on a narrow widget. -->
            <div v-show="showDaily" class="flex items-center" :class="showHourly ? 'shrink-0' : 'min-h-0 flex-1'">
                <div class="grid w-full gap-1" :style="{ gridTemplateColumns: `repeat(${dayCount}, minmax(0, 1fr))` }">
                    <div
                        v-for="d in data.days.slice(0, dayCount)"
                        :key="d.date"
                        class="flex flex-col items-center gap-0.5 rounded-md py-1 text-center"
                    >
                        <div class="text-xs text-muted-foreground">{{ d.dow }}</div>
                        <component :is="weatherDescribe(d.code).icon" class="size-5 text-muted-foreground" />
                        <div class="text-xs tabular-nums">
                            <span class="font-medium">{{ d.high }}°</span> <span class="text-muted-foreground">{{ d.low }}°</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <EmptyState v-else class="px-6">
            <template #icon><CloudSun class="size-6 opacity-50" /></template>
            <p>Set a business address in Company settings to show local weather.</p>
        </EmptyState>
    </div>
</template>
