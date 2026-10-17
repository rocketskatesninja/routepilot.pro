<script setup lang="ts">
import PortalHero from '@/components/portal/PortalHero.vue';
import { Button } from '@/components/ui/button';
import { subscribeCustomerEta } from '@/echo';
import AppLayout from '@/layouts/AppLayout.vue';
import { andList, checkWater, longDay, waterSummary, when, type WaterReading, type WaterTargets } from '@/lib/portal';
import { formatDay, formatMoney } from '@/lib/utils';
import { type BreadcrumbItem, type SharedData } from '@/types';
import { Head, Link, useForm, usePage } from '@inertiajs/vue3';
import { Banknote, Check, Droplets, Inbox } from 'lucide-vue-next';
import { computed, onBeforeUnmount, onMounted, ref } from 'vue';

interface Upcoming {
    date: string;
    agent: string | null;
    window: string | null;
    services: string[];
    pools: string[];
}

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

const props = defineProps<{
    firstName: string;
    customerId: number;
    poolCount: number;
    upcoming: Upcoming[];
    hasService: boolean;
    lastVisit: Visit | null;
    water: { pool: string | null; on: string | null; reading: WaterReading | null; targets: WaterTargets }[];
    balance: number;
    autopay: boolean;
    canPay: boolean;
    pendingRequests: number;
    today: string;
}>();

const breadcrumbs: BreadcrumbItem[] = [{ title: 'Home', href: '/dashboard' }];
const page = usePage<SharedData>();
const company = computed(() => page.props.tenant?.name ?? 'your pool company');

const next = computed(() => props.upcoming[0] ?? null);
const then = computed(() => props.upcoming[1] ?? null);

// With more than one pool, say which pool a visit is for.
const manyPools = computed(() => props.poolCount > 1);
const at = (pools: string[]) => (manyPools.value && pools.length ? ` at ${andList(pools)}` : '');

// Live "on the way": the arrival window updates in place as the tech drives.
const liveWindow = ref<string | null>(null);
let unsubscribe: (() => void) | null = null;
onMounted(() => {
    unsubscribe = subscribeCustomerEta(props.customerId, (e) => {
        if (e.window) liveWindow.value = e.window;
    });
});
onBeforeUnmount(() => unsubscribe?.());
const arriving = computed(() => liveWindow.value ?? (next.value && next.value.date === props.today ? next.value.window : null));

// The water, one sentence per pool.
const waterLines = computed(() =>
    props.water.map((w) => {
        const checks = checkWater(w.reading, w.targets);
        const ok = checks.every((c) => c.status === 'good');
        const where = props.water.length > 1 ? `${w.pool}: ` : '';
        return {
            ok,
            text: ok
                ? `${where}the water was in good shape on ${formatDay(w.on)}.`
                : `${where}on ${formatDay(w.on)}, ${waterSummary(checks).charAt(0).toLowerCase()}${waterSummary(checks).slice(1)}`,
        };
    }),
);

const payForm = useForm({});
const pay = () => payForm.post('/balance/pay', { preserveScroll: true });

const doneTasks = computed(() => props.lastVisit?.tasks.filter((t) => t.done) ?? []);
</script>

<template>
    <Head title="Home" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="mx-auto flex w-full max-w-3xl flex-1 flex-col gap-10 px-4 py-6">
            <!-- when someone is coming next -->
            <PortalHero :greeting="`Hi ${props.firstName}, here’s your pool service with ${company}.`">
                <template v-if="next && liveWindow">{{ next.agent ?? 'Your technician' }} is on the way.</template>
                <template v-else-if="next">
                    {{ next.agent ?? 'Your technician' }} is coming {{ when(next.date, props.today) }} for your {{ andList(next.services)
                    }}{{ at(next.pools) }}.
                </template>
                <template v-else-if="props.hasService">Your next visit isn’t on the schedule yet.</template>
                <template v-else>You don’t have regular service yet.</template>

                <template #sub>
                    <p v-if="arriving">Expected between {{ arriving.replace('–', 'and') }}.</p>
                    <p v-if="then">Then {{ andList(then.services) }}{{ at(then.pools) }} {{ when(then.date, props.today) }}.</p>
                    <p v-if="!next && !props.hasService">Pick a service and {{ company }} will set it up.</p>
                </template>
                <template v-if="!next && !props.hasService" #actions>
                    <Button as-child><Link href="/requests">See services</Link></Button>
                </template>
            </PortalHero>

            <!-- the account at a glance -->
            <section aria-label="Your account" class="border-border divide-border divide-y rounded-2xl border">
                <div class="grid grid-cols-[auto_1fr] items-center gap-x-4 gap-y-2 p-4 sm:grid-cols-[auto_1fr_auto] sm:p-5">
                    <span class="text-primary grid size-10 shrink-0 place-items-center self-start rounded-xl bg-[hsl(var(--brand)/0.1)]"
                        ><Droplets class="size-5"
                    /></span>
                    <div class="min-w-0">
                        <p class="font-medium">Your water</p>
                        <template v-if="waterLines.length">
                            <p v-for="(w, i) in waterLines" :key="i" class="text-muted-foreground text-sm first-letter:uppercase">{{ w.text }}</p>
                        </template>
                        <p v-else class="text-muted-foreground text-sm">Your technician tests the water on each visit. Results show up here.</p>
                    </div>
                    <Link
                        href="/my-pools"
                        class="text-primary col-start-2 text-sm font-medium whitespace-nowrap underline-offset-4 hover:underline sm:col-start-auto"
                        >{{ manyPools ? 'See your pools' : 'See your pool' }}</Link
                    >
                </div>

                <div class="grid grid-cols-[auto_1fr] items-center gap-x-4 gap-y-2 p-4 sm:grid-cols-[auto_1fr_auto] sm:p-5">
                    <span class="text-primary grid size-10 shrink-0 place-items-center self-start rounded-xl bg-[hsl(var(--brand)/0.1)]"
                        ><Banknote class="size-5"
                    /></span>
                    <div class="min-w-0">
                        <p class="font-medium">Your balance</p>
                        <p class="text-muted-foreground text-sm">
                            <template v-if="props.balance > 0">You owe {{ formatMoney(props.balance) }}.</template>
                            <template v-else>You’re all paid up.</template>
                            <template v-if="props.autopay"> Autopay is on.</template>
                        </p>
                    </div>
                    <div class="col-start-2 flex items-center gap-4 sm:col-start-auto">
                        <Button v-if="props.balance > 0 && props.canPay && !props.autopay" size="sm" :disabled="payForm.processing" @click="pay"
                            >Pay {{ formatMoney(props.balance) }}</Button
                        >
                        <Link href="/balance" class="text-primary text-sm font-medium whitespace-nowrap underline-offset-4 hover:underline"
                            >See your bill</Link
                        >
                    </div>
                </div>

                <div class="grid grid-cols-[auto_1fr] items-center gap-x-4 gap-y-2 p-4 sm:grid-cols-[auto_1fr_auto] sm:p-5">
                    <span class="text-primary grid size-10 shrink-0 place-items-center self-start rounded-xl bg-[hsl(var(--brand)/0.1)]"
                        ><Inbox class="size-5"
                    /></span>
                    <div class="min-w-0">
                        <p class="font-medium">Requests</p>
                        <p class="text-muted-foreground text-sm">
                            <template v-if="props.pendingRequests"
                                >{{ props.pendingRequests === 1 ? 'One request is' : `${props.pendingRequests} requests are` }} waiting for
                                {{ company }}.</template
                            >
                            <template v-else>Need something? Add a service, pause one, or send a note.</template>
                        </p>
                    </div>
                    <Link
                        href="/requests"
                        class="text-primary col-start-2 text-sm font-medium whitespace-nowrap underline-offset-4 hover:underline sm:col-start-auto"
                        >Services</Link
                    >
                </div>
            </section>

            <!-- the latest visit report -->
            <section v-if="props.lastVisit" aria-labelledby="last">
                <div class="flex flex-wrap items-baseline justify-between gap-2">
                    <h2 id="last" class="font-display text-xl font-semibold tracking-tight">Your last visit</h2>
                    <Link href="/history" class="text-primary text-sm font-medium underline-offset-4 hover:underline">All visits</Link>
                </div>
                <article class="border-border mt-4 rounded-2xl border p-5 sm:p-6">
                    <p class="font-medium">{{ props.lastVisit.on ? longDay(props.lastVisit.on) : '' }}</p>
                    <p class="text-muted-foreground text-sm">
                        {{ props.lastVisit.service ?? 'Service visit' }}{{ at(props.lastVisit.pool ? [props.lastVisit.pool] : [])
                        }}<template v-if="props.lastVisit.agent"> with {{ props.lastVisit.agent }}</template>
                    </p>

                    <ul
                        v-if="doneTasks.length || props.lastVisit.reading || props.lastVisit.treatments.length"
                        class="mt-4 grid gap-x-6 gap-y-1.5 text-sm sm:grid-cols-2"
                    >
                        <li v-for="t in doneTasks" :key="t.name" class="flex gap-2">
                            <Check class="text-primary mt-0.5 size-4 shrink-0" />{{ t.name }}
                        </li>
                        <li v-if="props.lastVisit.reading" class="flex gap-2"><Check class="text-primary mt-0.5 size-4 shrink-0" />Water tested</li>
                        <li v-if="props.lastVisit.treatments.length" class="flex gap-2">
                            <Check class="text-primary mt-0.5 size-4 shrink-0" />{{ props.lastVisit.treatments.length }} chemical{{
                                props.lastVisit.treatments.length === 1 ? '' : 's'
                            }}
                            added
                        </li>
                    </ul>

                    <div v-if="props.lastVisit.notes" class="bg-muted mt-4 max-w-prose rounded-2xl rounded-tl-md px-4 py-2.5 text-sm">
                        <p class="text-muted-foreground text-xs font-medium">{{ props.lastVisit.agent ?? 'Your technician' }} wrote</p>
                        <p>{{ props.lastVisit.notes }}</p>
                    </div>

                    <div v-if="props.lastVisit.photos.length" class="mt-4 grid grid-cols-3 gap-2 sm:grid-cols-4">
                        <a
                            v-for="(src, i) in props.lastVisit.photos.slice(0, 4)"
                            :key="src"
                            :href="src"
                            target="_blank"
                            rel="noopener"
                            class="focus-visible:ring-ring overflow-hidden rounded-xl focus-visible:ring-2 focus-visible:outline-none"
                        >
                            <img :src="src" :alt="`Photo ${i + 1} from the visit`" class="aspect-square w-full object-cover" loading="lazy" />
                        </a>
                    </div>

                    <Link
                        :href="`/history?selected=${props.lastVisit.id}`"
                        class="text-primary mt-4 inline-block text-sm font-medium underline-offset-4 hover:underline"
                        >See the full report</Link
                    >
                </article>
            </section>
        </div>
    </AppLayout>
</template>
