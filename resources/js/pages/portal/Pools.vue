<script setup lang="ts">
import Sparkline from '@/components/charts/Sparkline.vue';
import PortalPageHeader from '@/components/portal/PortalPageHeader.vue';
import WaterChecks from '@/components/portal/WaterChecks.vue';
import AppLayout from '@/layouts/AppLayout.vue';
import { targetsFor } from '@/lib/chemistry';
import { andList, checkWater, howOften, longDay, waterSummary, type ScheduleLike, type WaterReading, type WaterTargets } from '@/lib/portal';
import { formatDay } from '@/lib/utils';
import { type BreadcrumbItem } from '@/types';
import { Head, Link } from '@inertiajs/vue3';
import { Waves } from 'lucide-vue-next';

interface PoolPlan extends ScheduleLike {
    id: number;
    service: string | null;
    agent: string | null;
    status: string;
}

interface PoolInfo {
    id: number;
    name: string;
    photo: string | null;
    type: string;
    sanitizer: string | null;
    volume: number | null;
    features: string[];
    last_serviced: string | null;
    tested_on: string | null;
    reading: WaterReading | null;
    targets: WaterTargets;
    trend: { chlorine: number[]; ph: number[]; dates: string[] };
    plans: PoolPlan[];
}

const props = defineProps<{ pools: PoolInfo[] }>();

const breadcrumbs: BreadcrumbItem[] = [{ title: props.pools.length === 1 ? 'Your pool' : 'Your pools', href: '/my-pools' }];

const TYPES: Record<string, string> = {
    inground: 'in-ground',
    above_ground: 'above-ground',
    indoor: 'indoor',
    spa: 'spa',
    infinity: 'infinity',
    other: '',
};
const SANITIZERS: Record<string, string> = {
    salt: 'salt',
    chlorine: 'chlorine',
    bromine: 'bromine',
    biguanide: 'biguanide',
    ozone: 'ozone',
    uv: 'UV',
};
const FEATURES: Record<string, string> = {
    heater: 'a heater',
    cover: 'a cover',
    automation: 'automation',
    cleaner: 'a pool cleaner',
    water_feature: 'a water feature',
    auto_fill: 'an auto-fill',
};

/** "A 12,000-gallon in-ground salt pool, with a heater and a cover." */
function describe(p: PoolInfo): string {
    const size = p.volume ? `${p.volume.toLocaleString('en-US')}-gallon` : '';
    const kind = TYPES[p.type] ?? '';
    const water = p.sanitizer ? (SANITIZERS[p.sanitizer] ?? '') : '';
    const words = [size, kind, water, p.type === 'spa' ? '' : 'pool'].filter(Boolean).join(' ');
    const article = /^[aeiou8]/i.test(words) || /^1[18],/.test(words) ? 'An' : 'A';
    const extras = p.features.map((f) => FEATURES[f]).filter(Boolean);
    return `${article} ${words || 'pool'}${extras.length ? `, with ${andList(extras)}` : ''}.`;
}

const band = (p: PoolInfo, key: 'free_chlorine' | 'ph'): [number, number] => {
    const r = targetsFor({ custom_target_ranges: p.targets })[key];
    return [r.min, r.max];
};
</script>

<template>
    <Head :title="props.pools.length === 1 ? 'Your pool' : 'Your pools'" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="mx-auto flex w-full max-w-3xl flex-1 flex-col gap-12 px-4 py-6">
            <p v-if="!props.pools.length" class="border-border text-muted-foreground rounded-2xl border p-5 text-[15px]">
                Your pool isn’t on your account yet. Send your service company a note from the
                <Link href="/requests" class="text-primary underline-offset-4 hover:underline">Services</Link> page and they’ll add it.
            </p>

            <article v-for="p in props.pools" :key="p.id" class="flex flex-col gap-8" :aria-labelledby="`pool-${p.id}`">
                <!-- what it is -->
                <div class="flex flex-col gap-5 sm:flex-row sm:items-center">
                    <img v-if="p.photo" :src="p.photo" :alt="p.name" class="aspect-[4/3] w-full rounded-2xl object-cover sm:w-56" />
                    <span v-else class="text-primary grid size-16 shrink-0 place-items-center rounded-2xl bg-[hsl(var(--brand)/0.1)]">
                        <Waves class="size-8" />
                    </span>
                    <PortalPageHeader :id="`pool-${p.id}`" :title="p.name" :summary="describe(p)">
                        <p v-if="p.last_serviced" class="text-muted-foreground mt-1 text-sm">Last serviced {{ longDay(p.last_serviced) }}.</p>
                    </PortalPageHeader>
                </div>

                <!-- how the water looked -->
                <section :aria-labelledby="`water-${p.id}`">
                    <h2 :id="`water-${p.id}`" class="font-display text-xl font-semibold tracking-tight">
                        {{ p.tested_on ? `Your water on ${formatDay(p.tested_on)}` : 'Your water' }}
                    </h2>
                    <template v-if="p.reading">
                        <p class="text-muted-foreground mt-1 text-sm">{{ waterSummary(checkWater(p.reading, p.targets)) }}</p>
                        <div class="border-border mt-4 rounded-2xl border px-4 sm:px-5">
                            <WaterChecks :checks="checkWater(p.reading, p.targets)" />
                        </div>

                        <div v-if="p.trend.chlorine.length > 1 || p.trend.ph.length > 1" class="mt-4 grid gap-4 sm:grid-cols-2">
                            <figure v-if="p.trend.chlorine.length > 1" class="border-border rounded-2xl border p-4">
                                <figcaption class="text-sm font-medium">Chlorine, last {{ p.trend.chlorine.length }} visits</figcaption>
                                <div class="mt-2">
                                    <Sparkline :values="p.trend.chlorine" :band="band(p, 'free_chlorine')" color="hsl(var(--brand))" />
                                </div>
                                <p class="text-muted-foreground mt-1 text-xs">Shaded area is the healthy range.</p>
                            </figure>
                            <figure v-if="p.trend.ph.length > 1" class="border-border rounded-2xl border p-4">
                                <figcaption class="text-sm font-medium">pH, last {{ p.trend.ph.length }} visits</figcaption>
                                <div class="mt-2"><Sparkline :values="p.trend.ph" :band="band(p, 'ph')" color="hsl(var(--brand))" /></div>
                                <p class="text-muted-foreground mt-1 text-xs">Shaded area is the healthy range.</p>
                            </figure>
                        </div>
                    </template>
                    <p v-else class="text-muted-foreground mt-1 text-sm">Your technician tests the water on each visit. Results show up here.</p>
                </section>

                <!-- the service it gets -->
                <section :aria-labelledby="`service-${p.id}`">
                    <div class="flex flex-wrap items-baseline justify-between gap-2">
                        <h2 :id="`service-${p.id}`" class="font-display text-xl font-semibold tracking-tight">Service on this pool</h2>
                        <Link :href="`/requests?pool=${p.id}`" class="text-primary text-sm font-medium underline-offset-4 hover:underline"
                            >Manage</Link
                        >
                    </div>
                    <ul v-if="p.plans.length" class="border-border divide-border mt-4 divide-y rounded-2xl border">
                        <li v-for="s in p.plans" :key="s.id" class="flex flex-wrap items-baseline justify-between gap-x-4 gap-y-1 p-4 sm:px-5">
                            <span class="font-medium">{{ s.service }}</span>
                            <span class="text-muted-foreground text-sm"
                                >{{ s.status === 'paused' ? 'Paused' : howOften(s) }}<template v-if="s.agent">, with {{ s.agent }}</template></span
                            >
                        </li>
                    </ul>
                    <p v-else class="text-muted-foreground mt-1 text-sm">
                        No regular service yet.
                        <Link :href="`/requests?pool=${p.id}`" class="text-primary underline-offset-4 hover:underline">Pick a service</Link>.
                    </p>
                </section>
            </article>
        </div>
    </AppLayout>
</template>
