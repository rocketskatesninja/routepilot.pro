<script setup lang="ts">
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
    health: { label: string; color: string; description: string } | null;
    reading: Reading | null;
}

const props = defineProps<{
    pools: PoolCard[];
    nextVisit: { pool: string | null; date: string | null; window: string | null } | null;
}>();

const breadcrumbs: BreadcrumbItem[] = [{ title: 'My Pools', href: '/my-pools' }];

const healthClass = (color: string): string =>
    ({
        green: 'bg-emerald-500/15 text-emerald-600 dark:text-emerald-400',
        amber: 'bg-amber-500/15 text-amber-600 dark:text-amber-400',
        red: 'bg-rose-500/15 text-rose-600 dark:text-rose-400',
    })[color] ?? 'bg-muted text-muted-foreground';

const metrics = (r: Reading): { label: string; value: string }[] => [
    { label: 'Free chlorine', value: r.free_chlorine != null ? `${r.free_chlorine} ppm` : '—' },
    { label: 'pH', value: r.ph != null ? String(r.ph) : '—' },
    { label: 'Alkalinity', value: r.alkalinity != null ? `${r.alkalinity} ppm` : '—' },
    { label: 'Calcium', value: r.calcium_hardness != null ? `${r.calcium_hardness} ppm` : '—' },
    { label: 'Cyanuric acid', value: r.cyanuric_acid != null ? `${r.cyanuric_acid} ppm` : '—' },
    { label: 'Salt', value: r.salt != null ? `${r.salt} ppm` : '—' },
];
</script>

<template>
    <Head title="My Pools" />

    <AppLayout :breadcrumbs="breadcrumbs" :meta="`${props.pools.length} ${props.pools.length === 1 ? 'pool' : 'pools'}`">
        <div class="mx-auto w-full max-w-4xl space-y-4 p-4">
            <!-- Next visit -->
            <div v-if="nextVisit" class="flex items-center gap-3 rounded-xl border border-primary/25 bg-primary/10 p-4">
                <CalendarClock class="size-5 shrink-0 text-primary" />
                <div class="text-sm">
                    <p class="font-medium text-foreground">Next visit{{ nextVisit.pool ? ` — ${nextVisit.pool}` : '' }}</p>
                    <p class="text-muted-foreground">
                        {{ nextVisit.date }}<template v-if="nextVisit.window"> · arriving {{ nextVisit.window }}</template>
                    </p>
                </div>
            </div>

            <!-- Pool cards -->
            <div v-if="pools.length" class="grid gap-4 sm:grid-cols-2">
                <div v-for="p in pools" :key="p.id" class="overflow-hidden rounded-xl border border-border bg-background shadow-sm">
                    <div class="flex items-center gap-3 border-b border-border p-4">
                        <EntityAvatar :src="p.photo" type="pool" :name="p.name" size="md" />
                        <div class="min-w-0 flex-1">
                            <p class="truncate font-semibold">{{ p.name }}</p>
                            <p class="truncate text-xs capitalize text-muted-foreground">
                                {{ p.sanitizer }}<template v-if="p.volume"> · {{ p.volume.toLocaleString() }} gal</template>
                            </p>
                        </div>
                        <span
                            v-if="p.health"
                            class="shrink-0 rounded-full px-2.5 py-1 text-xs font-medium"
                            :class="healthClass(p.health.color)"
                            :title="p.health.description"
                        >
                            {{ p.health.label }}
                        </span>
                    </div>
                    <div class="p-4">
                        <div v-if="p.reading" class="grid grid-cols-3 gap-3 text-center">
                            <div v-for="m in metrics(p.reading)" :key="m.label">
                                <p class="text-sm font-semibold text-foreground">{{ m.value }}</p>
                                <p class="text-[11px] leading-tight text-muted-foreground">{{ m.label }}</p>
                            </div>
                        </div>
                        <p v-else class="text-sm text-muted-foreground">No water chemistry recorded yet.</p>
                        <div class="mt-3 flex items-center gap-1.5 text-xs text-muted-foreground">
                            <template v-if="p.last_serviced"><Waves class="size-3.5" /> Last serviced {{ p.last_serviced }}</template>
                            <span v-if="p.has_heater" class="ml-auto inline-flex items-center gap-1"
                                ><Flame class="size-3.5 text-orange-500" /> Heated</span
                            >
                        </div>
                    </div>
                </div>
            </div>
            <div v-else class="rounded-xl border border-dashed border-border p-10 text-center text-sm text-muted-foreground">
                No pools on your account yet.
            </div>
        </div>
    </AppLayout>
</template>
