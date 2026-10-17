<script setup lang="ts">
import PortalPageHeader from '@/components/portal/PortalPageHeader.vue';
import WaterChecks from '@/components/portal/WaterChecks.vue';
import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/AppLayout.vue';
import { checkWater, monthYear, waterSummary, type WaterCheck, type WaterReading, type WaterTargets } from '@/lib/portal';
import { type BreadcrumbItem } from '@/types';
import { type Paginated } from '@/types/pagination';
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import { Check, ChevronDown, Minus } from 'lucide-vue-next';
import { computed, ref } from 'vue';

interface Visit {
    id: number;
    pool: string | null;
    pool_id: number;
    service: string | null;
    on: string | null;
    agent: string | null;
    notes: string | null;
    reading: WaterReading | null;
    targets: WaterTargets;
    treatments: { name: string; amount: number; unit: string }[];
    tasks: { name: string; done: boolean }[];
    photos: string[];
}

const props = defineProps<{ visits: Paginated<Visit> }>();

const breadcrumbs: BreadcrumbItem[] = [{ title: 'Service history', href: '/history' }];
const page = usePage();
const manyPools = computed(() => new Set(props.visits.data.map((v) => v.pool_id)).size > 1);

// A link from Home (?selected=ID) opens that visit's report.
const openId = ref<number | null>(Number(new URL(page.url, 'http://localhost').searchParams.get('selected')) || null);
const toggle = (id: number) => (openId.value = openId.value === id ? null : id);

// Visits grouped by month, newest first, each with its water test checked against the pool's targets.
const months = computed(() => {
    const groups: { label: string; visits: (Visit & { checks: WaterCheck[] })[] }[] = [];
    for (const v of props.visits.data) {
        const visit = { ...v, checks: checkWater(v.reading, v.targets) };
        const label = v.on ? monthYear(v.on) : 'Earlier';
        const g = groups.find((x) => x.label === label);
        if (g) g.visits.push(visit);
        else groups.push({ label, visits: [visit] });
    }
    return groups;
});

const dayNum = (ymd: string | null) => (ymd ? Number(ymd.slice(8, 10)) : '');
const weekday = (ymd: string | null) => {
    if (!ymd) return '';
    const [y, m, d] = ymd.split('-').map(Number);
    return new Date(y, m - 1, d).toLocaleDateString('en-US', { weekday: 'short' });
};

/** "Checklist done, water tested, 2 chemicals added." */
function summary(v: Visit): string {
    const parts: string[] = [];
    if (v.tasks.length) {
        const done = v.tasks.filter((t) => t.done).length;
        parts.push(done === v.tasks.length ? 'checklist done' : `${done} of ${v.tasks.length} checklist steps done`);
    }
    if (v.reading) parts.push('water tested');
    if (v.treatments.length) parts.push(`${v.treatments.length} chemical${v.treatments.length === 1 ? '' : 's'} added`);
    if (v.photos.length) parts.push(`${v.photos.length} photo${v.photos.length === 1 ? '' : 's'}`);
    const s = parts.join(', ');
    return s ? `${s.charAt(0).toUpperCase()}${s.slice(1)}.` : 'Visit completed.';
}

const amount = (t: Visit['treatments'][number]) => `${Number.isInteger(t.amount) ? t.amount : t.amount.toFixed(2)} ${t.unit}`;
const goTo = (n: number) => router.get('/history', { page: n }, { preserveScroll: false });
</script>

<template>
    <Head title="Service history" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="mx-auto flex w-full max-w-3xl flex-1 flex-col gap-8 px-4 py-6">
            <PortalPageHeader
                title="Service history"
                :summary="`Every visit to your ${manyPools ? 'pools' : 'pool'}: what was done, how the water looked, and photos when they were taken.`"
            />

            <p v-if="!props.visits.data.length" class="border-border text-muted-foreground rounded-2xl border p-5 text-[15px]">
                No visits yet. After each one, the report shows up here.
            </p>

            <section v-for="m in months" :key="m.label" :aria-label="m.label">
                <h2 class="text-muted-foreground mb-2 text-sm font-medium">{{ m.label }}</h2>
                <ol class="border-border divide-border divide-y rounded-2xl border">
                    <li v-for="v in m.visits" :key="v.id">
                        <button
                            type="button"
                            class="focus-visible:ring-ring hover:bg-muted/40 flex w-full items-center gap-4 rounded-2xl p-4 text-left focus-visible:ring-2 focus-visible:outline-none sm:p-5"
                            :aria-expanded="openId === v.id"
                            :aria-controls="`visit-${v.id}`"
                            @click="toggle(v.id)"
                        >
                            <span class="w-11 shrink-0 text-center">
                                <span class="font-display block text-2xl leading-none font-semibold">{{ dayNum(v.on) }}</span>
                                <span class="text-muted-foreground text-xs">{{ weekday(v.on) }}</span>
                            </span>
                            <span class="min-w-0 flex-1">
                                <span class="block font-medium"
                                    >{{ v.service ?? 'Service visit' }}<template v-if="v.agent"> with {{ v.agent }}</template></span
                                >
                                <span class="text-muted-foreground block text-sm"
                                    ><template v-if="manyPools">{{ v.pool }}. </template>{{ summary(v) }}</span
                                >
                            </span>
                            <span v-if="v.photos.length" class="hidden shrink-0 gap-1.5 sm:flex">
                                <img
                                    v-for="src in v.photos.slice(0, 3)"
                                    :key="src"
                                    :src="src"
                                    alt=""
                                    class="size-10 rounded-lg object-cover"
                                    loading="lazy"
                                />
                            </span>
                            <ChevronDown
                                class="text-muted-foreground size-4 shrink-0 transition-transform"
                                :class="openId === v.id ? 'rotate-180' : ''"
                            />
                        </button>

                        <!-- the full report -->
                        <div v-if="openId === v.id" :id="`visit-${v.id}`" class="space-y-6 px-4 pb-6 sm:px-5 sm:pl-[5.25rem]">
                            <div v-if="v.tasks.length">
                                <h3 class="text-sm font-medium">What was done</h3>
                                <ul class="mt-2 grid gap-x-6 gap-y-1.5 text-sm sm:grid-cols-2">
                                    <li v-for="t in v.tasks" :key="t.name" class="flex gap-2" :class="t.done ? '' : 'text-muted-foreground'">
                                        <Check v-if="t.done" class="text-primary mt-0.5 size-4 shrink-0" />
                                        <Minus v-else class="mt-0.5 size-4 shrink-0" />
                                        {{ t.name }}<template v-if="!t.done"> (skipped)</template>
                                    </li>
                                </ul>
                            </div>

                            <div v-if="v.reading">
                                <h3 class="text-sm font-medium">Water test</h3>
                                <p class="text-muted-foreground text-sm">{{ waterSummary(v.checks) }}</p>
                                <WaterChecks :checks="v.checks" :explain="false" class="mt-1" />
                            </div>

                            <div v-if="v.treatments.length">
                                <h3 class="text-sm font-medium">Chemicals added</h3>
                                <ul class="mt-2 space-y-1 text-sm">
                                    <li v-for="t in v.treatments" :key="t.name" class="flex justify-between gap-4 sm:max-w-sm">
                                        <span>{{ t.name }}</span
                                        ><span class="text-muted-foreground tabular-nums">{{ amount(t) }}</span>
                                    </li>
                                </ul>
                            </div>

                            <div v-if="v.notes" class="bg-muted max-w-prose rounded-2xl rounded-tl-md px-4 py-2.5 text-sm">
                                <p class="text-muted-foreground text-xs font-medium">{{ v.agent ?? 'Your technician' }} wrote</p>
                                <p class="whitespace-pre-line">{{ v.notes }}</p>
                            </div>

                            <div v-if="v.photos.length" class="grid grid-cols-2 gap-2 sm:grid-cols-3">
                                <a
                                    v-for="(src, i) in v.photos"
                                    :key="src"
                                    :href="src"
                                    target="_blank"
                                    rel="noopener"
                                    class="focus-visible:ring-ring overflow-hidden rounded-xl focus-visible:ring-2 focus-visible:outline-none"
                                >
                                    <img
                                        :src="src"
                                        :alt="`Photo ${i + 1} from this visit`"
                                        class="aspect-square w-full object-cover"
                                        loading="lazy"
                                    />
                                </a>
                            </div>

                            <p class="text-muted-foreground text-sm">
                                Questions about this visit?
                                <Link :href="`/requests?pool=${v.pool_id}`" class="text-primary font-medium underline-offset-4 hover:underline"
                                    >Send a note</Link
                                >
                            </p>
                        </div>
                    </li>
                </ol>
            </section>

            <nav v-if="props.visits.last_page > 1" class="flex items-center justify-between gap-4" aria-label="More visits">
                <Button variant="outline" :disabled="props.visits.current_page <= 1" @click="goTo(props.visits.current_page - 1)"
                    >Newer visits</Button
                >
                <span class="text-muted-foreground text-sm">Page {{ props.visits.current_page }} of {{ props.visits.last_page }}</span>
                <Button variant="outline" :disabled="props.visits.current_page >= props.visits.last_page" @click="goTo(props.visits.current_page + 1)"
                    >Older visits</Button
                >
            </nav>
        </div>
    </AppLayout>
</template>
