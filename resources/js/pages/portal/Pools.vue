<script setup lang="ts">
import HealthRing from '@/components/charts/HealthRing.vue';
import Sparkline from '@/components/charts/Sparkline.vue';
import EntityAvatar from '@/components/EntityAvatar.vue';
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem } from '@/types';
import { Head } from '@inertiajs/vue3';
import { CalendarClock, Flame, Waves } from 'lucide-vue-next';

interface Reading {
    free_chlorine: number | null;
    ph: number | null;
    alkalinity: number | null;
    calcium_hardness: number | null;
    cyanuric_acid: number | null;
    salt: number | null;
}
interface PoolCard {
    id: number;
    name: string;
    photo: string | null;
    sanitizer: string | null;
    volume: number | null;
    has_heater: boolean;
    last_serviced: string | null;
    health: { label: string; color: 'green' | 'amber' | 'red'; description: string } | null;
    reading: Reading | null;
    trend: { chlorine: number[]; ph: number[]; dates: string[] };
}

const props = defineProps<{
    pools: PoolCard[];
    nextVisit: { pool: string | null; date: string | null; window: string | null } | null;
}>();

const breadcrumbs: BreadcrumbItem[] = [{ title: 'My Pools', href: '/my-pools' }];

const ppm = (v: number | null) => (v != null ? `${v} ppm` : '—');
</script>

<template>
    <Head title="My Pools" />

    <AppLayout :breadcrumbs="breadcrumbs" :meta="`${props.pools.length} ${props.pools.length === 1 ? 'pool' : 'pools'}`">
        <div class="mx-auto w-full max-w-4xl space-y-4 p-4">
            <!-- Next visit -->
            <div v-if="nextVisit" class="border-primary/25 bg-primary/10 flex items-center gap-3 rounded-xl border p-4">
                <CalendarClock class="text-primary size-5 shrink-0" />
                <div class="text-sm">
                    <p class="text-foreground font-medium">Next visit{{ nextVisit.pool ? ` — ${nextVisit.pool}` : '' }}</p>
                    <p class="text-muted-foreground">
                        {{ nextVisit.date }}<template v-if="nextVisit.window"> · arriving {{ nextVisit.window }}</template>
                    </p>
                </div>
            </div>

            <!-- Pool cards -->
            <div v-if="pools.length" class="grid gap-4 sm:grid-cols-2">
                <div v-for="p in pools" :key="p.id" class="border-border bg-background overflow-hidden rounded-xl border shadow-sm">
                    <div class="border-border flex items-center gap-3 border-b p-4">
                        <EntityAvatar :src="p.photo" type="pool" :name="p.name" size="md" />
                        <div class="min-w-0 flex-1">
                            <p class="truncate font-semibold">{{ p.name }}</p>
                            <p class="text-muted-foreground truncate text-xs capitalize">
                                {{ p.sanitizer }}<template v-if="p.volume"> · {{ p.volume.toLocaleString() }} gal</template>
                            </p>
                        </div>
                    </div>
                    <div class="p-4">
                        <div v-if="p.reading" class="flex items-start gap-4">
                            <HealthRing
                                v-if="p.health"
                                :tone="p.health.color"
                                :label="p.health.label"
                                class="shrink-0"
                                :title="p.health.description"
                            />
                            <div class="min-w-0 flex-1 space-y-3">
                                <div class="grid grid-cols-2 gap-3">
                                    <div>
                                        <p class="text-sm font-semibold">
                                            {{ p.reading.free_chlorine ?? '—' }}<span class="text-muted-foreground text-xs font-normal"> ppm</span>
                                        </p>
                                        <p class="text-muted-foreground text-[11px] leading-tight">Free chlorine</p>
                                        <Sparkline
                                            v-if="p.trend.chlorine.length > 1"
                                            :values="p.trend.chlorine"
                                            :band="[1, 3]"
                                            color="hsl(var(--chart-1))"
                                            class="mt-1"
                                        />
                                    </div>
                                    <div>
                                        <p class="text-sm font-semibold">{{ p.reading.ph ?? '—' }}</p>
                                        <p class="text-muted-foreground text-[11px] leading-tight">pH</p>
                                        <Sparkline
                                            v-if="p.trend.ph.length > 1"
                                            :values="p.trend.ph"
                                            :band="[7.2, 7.8]"
                                            color="hsl(var(--chart-2))"
                                            class="mt-1"
                                        />
                                    </div>
                                </div>
                                <p v-if="p.trend.dates.length > 1" class="text-muted-foreground text-[10px]">
                                    {{ p.trend.dates[0] }} – {{ p.trend.dates[p.trend.dates.length - 1] }}
                                </p>
                                <dl class="text-muted-foreground grid grid-cols-2 gap-x-4 gap-y-1 text-xs">
                                    <div class="flex justify-between gap-2">
                                        <dt>Alkalinity</dt>
                                        <dd>{{ ppm(p.reading.alkalinity) }}</dd>
                                    </div>
                                    <div class="flex justify-between gap-2">
                                        <dt>Calcium</dt>
                                        <dd>{{ ppm(p.reading.calcium_hardness) }}</dd>
                                    </div>
                                    <div class="flex justify-between gap-2">
                                        <dt>Cyanuric</dt>
                                        <dd>{{ ppm(p.reading.cyanuric_acid) }}</dd>
                                    </div>
                                    <div class="flex justify-between gap-2">
                                        <dt>Salt</dt>
                                        <dd>{{ ppm(p.reading.salt) }}</dd>
                                    </div>
                                </dl>
                            </div>
                        </div>
                        <p v-else class="text-muted-foreground text-sm">No water chemistry recorded yet.</p>
                        <div class="text-muted-foreground mt-3 flex items-center gap-1.5 text-xs">
                            <template v-if="p.last_serviced"><Waves class="size-3.5" /> Last serviced {{ p.last_serviced }}</template>
                            <span v-if="p.has_heater" class="ml-auto inline-flex items-center gap-1"
                                ><Flame class="size-3.5 text-orange-500" /> Heated</span
                            >
                        </div>
                    </div>
                </div>
            </div>
            <div v-else class="border-border text-muted-foreground rounded-xl border border-dashed p-10 text-center text-sm">
                No pools on your account yet.
            </div>
        </div>
    </AppLayout>
</template>
