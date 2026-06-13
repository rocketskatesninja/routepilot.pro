<script setup lang="ts">
import { Cloud, CloudDrizzle, CloudFog, CloudLightning, CloudRain, CloudSnow, CloudSun, Droplets, Sun, Wind, type LucideIcon } from 'lucide-vue-next';

interface Current {
    temp: number;
    feels: number;
    humidity: number;
    wind: number;
    code: number;
}
interface Day {
    date: string;
    dow: string;
    high: number;
    low: number;
    code: number;
    precip: number;
}

defineProps<{ data: { current: Current; days: Day[] } | null }>();

// Map WMO weather codes to a label + lucide icon.
function describe(code: number): { label: string; icon: LucideIcon } {
    if (code <= 1) return { label: code === 0 ? 'Clear' : 'Mostly clear', icon: Sun };
    if (code === 2) return { label: 'Partly cloudy', icon: CloudSun };
    if (code === 3) return { label: 'Overcast', icon: Cloud };
    if ([45, 48].includes(code)) return { label: 'Fog', icon: CloudFog };
    if ([51, 53, 55, 56, 57].includes(code)) return { label: 'Drizzle', icon: CloudDrizzle };
    if ([61, 63, 65, 66, 67, 80, 81, 82].includes(code)) return { label: 'Rain', icon: CloudRain };
    if ([71, 73, 75, 77, 85, 86].includes(code)) return { label: 'Snow', icon: CloudSnow };
    if ([95, 96, 99].includes(code)) return { label: 'Storm', icon: CloudLightning };
    return { label: 'Clear', icon: Sun };
}
</script>

<template>
    <div v-if="data" class="flex h-full flex-col gap-3">
        <div class="flex items-center gap-3">
            <component :is="describe(data.current.code).icon" class="size-10 shrink-0 text-primary" />
            <div class="min-w-0">
                <div class="text-2xl font-semibold tabular-nums">{{ data.current.temp }}°</div>
                <div class="truncate text-xs text-muted-foreground">{{ describe(data.current.code).label }} · feels {{ data.current.feels }}°</div>
            </div>
            <div class="ml-auto space-y-0.5 text-right text-xs text-muted-foreground">
                <div class="flex items-center justify-end gap-1"><Droplets class="size-3" /> {{ data.current.humidity }}%</div>
                <div class="flex items-center justify-end gap-1"><Wind class="size-3" /> {{ data.current.wind }} mph</div>
            </div>
        </div>
        <div class="mt-auto grid grid-cols-5 gap-1">
            <div v-for="d in data.days.slice(0, 5)" :key="d.date" class="flex flex-col items-center gap-0.5 rounded-md py-1 text-center">
                <div class="text-xs text-muted-foreground">{{ d.dow }}</div>
                <component :is="describe(d.code).icon" class="size-5 text-muted-foreground" />
                <div class="text-xs tabular-nums"><span class="font-medium">{{ d.high }}°</span> <span class="text-muted-foreground">{{ d.low }}°</span></div>
            </div>
        </div>
    </div>
    <div v-else class="flex h-full flex-col items-center justify-center gap-2 px-6 text-center text-sm text-muted-foreground">
        <CloudSun class="size-6 opacity-50" />
        <p>Set a business address in Company settings to show local weather.</p>
    </div>
</template>
