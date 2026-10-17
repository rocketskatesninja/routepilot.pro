<script setup lang="ts">
import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/AppLayout.vue';
import { formatDay } from '@/lib/utils';
import { type BreadcrumbItem, type SharedData } from '@/types';
import { Head, useForm, usePage } from '@inertiajs/vue3';
import { Check, Clock, FlaskConical, MessageSquare, Waves, Wrench } from 'lucide-vue-next';
import { computed, ref, watch } from 'vue';

interface ServiceRequest {
    id: number;
    type: string;
    title: string;
    message: string | null;
    status: string;
    status_label: string;
    reply: string | null;
    plan: string | null;
    pool: string | null;
    preferred_date: string | null;
    ends_on: string | null;
    on: string | null;
}

/** One of the customer's current services (a plan on one of their pools). */
interface Plan {
    id: number;
    pool_id: number;
    pool: string | null;
    service_type_id: number;
    service: string | null;
    schedule: string;
    frequency: string;
    preferred_day: string | null;
    day_of_month: number | null;
    starts_on: string | null;
    ends_on: string | null;
    one_time: boolean;
    modules: string[];
    next_visit: string | null;
    agent: string | null;
    status: string;
    hold_from: string | null;
    hold_to: string | null;
    pending: 'hold' | 'cancel' | null;
}

interface Service {
    id: number;
    name: string;
    description: string | null;
    frequency: string;
    price: number;
    offer: string;
    minutes: number;
    tasks: string[];
    modules: string[];
}

const props = defineProps<{
    requests: ServiceRequest[];
    plans: Plan[];
    services: Service[];
    pools: { id: number; name: string }[];
    firstName: string;
    today: string;
}>();

const breadcrumbs: BreadcrumbItem[] = [{ title: 'Services', href: '/requests' }];
const page = usePage<SharedData>();
const company = computed(() => page.props.tenant?.name ?? 'your pool company');

// --- plain-language helpers ---
const cap = (s: string) => s.charAt(0).toUpperCase() + s.slice(1);
const ordinal = (n: number) => `${n}${n % 10 === 1 && n !== 11 ? 'st' : n % 10 === 2 && n !== 12 ? 'nd' : n % 10 === 3 && n !== 13 ? 'rd' : 'th'}`;
const longDay = (ymd: string) => {
    const [y, m, d] = ymd.split('-').map(Number);
    return new Date(y, m - 1, d).toLocaleDateString('en-US', { weekday: 'long', month: 'long', day: 'numeric' });
};
const dayAfter = (ymd: string) => {
    const [y, m, d] = ymd.split('-').map(Number);
    return new Date(y, m - 1, d + 1).toLocaleDateString('en-CA');
};
/** "today" / "tomorrow" / "on Wednesday, October 21". */
const when = (ymd: string) => (ymd === props.today ? 'today' : ymd === dayAfter(props.today) ? 'tomorrow' : `on ${longDay(ymd)}`);

function howOften(p: Plan): string {
    const day = p.preferred_day ? cap(p.preferred_day) : null;
    if (p.frequency === 'weekly') return day ? `Every ${day}` : 'Every week';
    if (p.frequency === 'biweekly') return day ? `Every other ${day}` : 'Every two weeks';
    if (p.frequency === 'monthly') return p.day_of_month ? `Once a month, on the ${ordinal(p.day_of_month)}` : 'Once a month';
    return p.starts_on ? `One visit, ${longDay(p.starts_on)}` : 'One visit';
}
/** "Wednesday visits" / "monthly visits" — how a customer thinks of a plan's visits. */
const visitsWord = (p: Plan) =>
    (p.frequency === 'weekly' || p.frequency === 'biweekly') && p.preferred_day
        ? `${cap(p.preferred_day)} visits`
        : p.frequency === 'monthly'
          ? 'monthly visits'
          : 'visits';
const soldAs = (f: string) => ({ weekly: 'Every week', biweekly: 'Every two weeks', monthly: 'Once a month', one_time: 'One-time job' })[f] ?? '';
const price = (n: number) => `$${Number.isInteger(n) ? n : n.toFixed(2)}`;

/** A simple picture for a service: a one-off job, a water-testing visit, or a regular clean. */
const iconFor = (modules: string[], frequency: string) =>
    frequency === 'one_time' ? Wrench : !modules.includes('tasks') && modules.includes('chemistry') ? FlaskConical : Waves;

/** What the tech does on the visit, in a homeowner's words (from the service's visit sections). */
function extras(modules: string[]): string[] {
    const out: string[] = [];
    if (modules.includes('chemistry')) out.push('Water tested and balanced');
    if (modules.includes('treatments')) out.push('Chemicals added as needed');
    if (modules.includes('photos')) out.push('Photos of your pool after each visit');
    return out;
}

// --- the next visit leads the page ---
const upcoming = computed(() =>
    props.plans.filter((p) => p.next_visit).sort((a, b) => (a.next_visit as string).localeCompare(b.next_visit as string)),
);
const nextDay = computed(() => upcoming.value[0]?.next_visit ?? null);
const nextUp = computed(() => upcoming.value.filter((p) => p.next_visit === nextDay.value));
const afterThat = computed(() => upcoming.value.find((p) => p.next_visit !== nextDay.value) ?? null);
const names = (list: Plan[]) => {
    const n = list.map((p) => p.service ?? 'service');
    return n.length > 1 ? `${n.slice(0, -1).join(', ')} and ${n[n.length - 1]}` : (n[0] ?? '');
};

// Group their services by pool — pool names only show when they have more than one.
const manyPools = computed(() => props.pools.length > 1);
const plansByPool = computed(() => {
    const groups: { pool: string | null; plans: Plan[] }[] = [];
    for (const p of props.plans) {
        const g = groups.find((x) => x.pool === p.pool);
        if (g) g.plans.push(p);
        else groups.push({ pool: p.pool, plans: [p] });
    }
    return groups;
});

// Which of their pools already have a catalog service.
const poolsWith = (serviceId: number) => [...new Set(props.plans.filter((p) => p.service_type_id === serviceId).map((p) => p.pool ?? ''))];

// Long checklists open on request.
const expanded = ref<number[]>([]);
const toggle = (id: number) => (expanded.value = expanded.value.includes(id) ? expanded.value.filter((x) => x !== id) : [...expanded.value, id]);
const longList = (s: Service) => s.tasks.length > 6;
const shownTasks = (s: Service) => (longList(s) && !expanded.value.includes(s.id) ? s.tasks.slice(0, 4) : s.tasks);

// --- one request form, in a dialog ---
type Kind = 'service' | 'hold' | 'cancel' | 'other';
const open = ref(false);
const kind = ref<Kind>('service');
const service = ref<Service | null>(null);
const plan = ref<Plan | null>(null);

// Built from a function so reset() always starts blank — never with the last request's note or dates.
const form = useForm(() => ({
    type: 'service' as Kind,
    service_type_id: null as number | null,
    service_subscription_id: null as number | null,
    pool_id: '' as number | string,
    preferred_date: '',
    ends_on: '',
    message: '',
}));

// A deep link from the service-history pane (?pool=5) pre-selects that pool.
const preferredPool = ref<number | null>(null);
watch(
    () => page.url,
    (url) => {
        const id = Number(new URL(url, 'http://localhost').searchParams.get('pool'));
        preferredPool.value = props.pools.some((p) => p.id === id) ? id : null;
    },
    { immediate: true },
);

function start(k: Kind, opts: { svc?: Service; target?: Plan } = {}) {
    form.reset();
    form.clearErrors();
    kind.value = k;
    service.value = opts.svc ?? null;
    plan.value = opts.target ?? null;
    form.type = k;
    form.service_type_id = opts.svc?.id ?? null;
    form.service_subscription_id = opts.target?.id ?? null;
    if (k === 'service') form.pool_id = preferredPool.value ?? (props.pools.length === 1 ? props.pools[0].id : '');
    if (k === 'other') form.pool_id = preferredPool.value ?? '';
    open.value = true;
}

function submit() {
    form.post('/requests', { preserveScroll: true, onSuccess: () => (open.value = false) });
}

const heading = computed(() => {
    if (kind.value === 'service') return `Request ${service.value?.name ?? 'a service'}`;
    if (kind.value === 'hold') return `Pause ${plan.value?.service ?? 'service'}`;
    if (kind.value === 'cancel') return plan.value?.one_time ? `Cancel ${plan.value.service}` : `Stop ${plan.value?.service ?? 'service'}`;
    return `Send ${company.value} a note`;
});
const subheading = computed(() => {
    if (kind.value === 'service' && service.value)
        return `${price(service.value.price)} ${service.value.frequency === 'one_time' ? 'for the job' : 'per visit'}, ${soldAs(service.value.frequency).toLowerCase()}.`;
    if (kind.value === 'hold') return `Pick the days to skip. Your ${visitsWord(plan.value as Plan)} start again on their own afterward.`;
    if (kind.value === 'cancel')
        return plan.value?.one_time ? 'We’ll let them know you no longer need this visit.' : 'Your other services carry on as usual.';
    if (kind.value === 'other') return 'Questions, a gate code, a one-off problem. They’ll reply here.';
    return '';
});

// Asking for a service the chosen pool already has — fine (an extra visit day), but say so.
const alreadyHas = computed(() =>
    kind.value === 'service' ? props.plans.find((p) => p.service_type_id === form.service_type_id && p.pool_id === Number(form.pool_id)) : undefined,
);

// What each request was about, as one short line.
function sentLine(r: ServiceRequest): string {
    const sent = `Sent ${formatDay(r.on)}`;
    if (r.type === 'hold') return `${sent}, to skip ${formatDay(r.preferred_date)} through ${formatDay(r.ends_on)}`;
    if (r.type === 'cancel') return r.preferred_date ? `${sent}, last visit ${formatDay(r.preferred_date)}` : `${sent}, as soon as possible`;
    if (r.type === 'service' && r.preferred_date) return `${sent}, to start ${formatDay(r.preferred_date)}`;
    return sent;
}

const statusClass = (status: string) =>
    ({
        pending: 'bg-amber-500/15 text-amber-700 dark:text-amber-400',
        approved: 'bg-emerald-500/15 text-emerald-700 dark:text-emerald-400',
    })[status] ?? 'bg-muted text-muted-foreground';
</script>

<template>
    <Head title="Services" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="mx-auto flex w-full max-w-3xl flex-1 flex-col gap-10 px-4 py-6">
            <!-- the next visit: what a homeowner wants to know first -->
            <section class="overflow-hidden rounded-2xl bg-[hsl(var(--brand)/0.08)]">
                <div class="px-6 pt-6 pb-7 sm:px-8 sm:pt-7">
                    <p class="text-muted-foreground text-[15px]">Hi {{ props.firstName }}, here’s your pool service with {{ company }}.</p>
                    <h1
                        class="font-display mt-2 max-w-[24ch] text-[1.75rem] leading-[1.15] font-semibold tracking-tight text-balance sm:text-[2.125rem]"
                    >
                        <template v-if="nextUp.length">
                            {{ nextUp[0].agent ?? 'Your technician' }} is coming {{ when(nextDay as string) }} for your {{ names(nextUp) }}.
                        </template>
                        <template v-else-if="props.plans.some((p) => p.hold_from || p.status === 'paused')"
                            >Your service is paused right now.</template
                        >
                        <template v-else-if="props.plans.length">Your next visit isn’t on the schedule yet.</template>
                        <template v-else>You don’t have regular service yet.</template>
                    </h1>
                    <p v-if="afterThat" class="text-muted-foreground mt-3 text-[15px]">
                        Then {{ afterThat.service }} {{ when(afterThat.next_visit as string) }}.
                    </p>
                    <p v-else-if="!props.plans.length" class="text-muted-foreground mt-3 text-[15px]">
                        Pick a service below and {{ company }} will set it up.
                    </p>
                </div>
                <div class="waterline h-4" aria-hidden="true"></div>
            </section>

            <!-- what they have now -->
            <section v-if="props.plans.length" aria-labelledby="yours">
                <h2 id="yours" class="font-display text-xl font-semibold tracking-tight">Your services</h2>
                <p class="text-muted-foreground mt-1 text-sm">Pause or stop any one of them. The rest carry on as usual.</p>

                <div class="mt-4 space-y-4">
                    <div v-for="g in plansByPool" :key="g.pool ?? ''">
                        <h3 v-if="manyPools" class="mb-2 text-sm font-medium">{{ g.pool }}</h3>
                        <ul class="divide-border border-border divide-y rounded-2xl border">
                            <li v-for="p in g.plans" :key="p.id" class="flex gap-4 p-4 sm:p-5">
                                <span class="text-primary grid size-10 shrink-0 place-items-center rounded-xl bg-[hsl(var(--brand)/0.1)]">
                                    <component :is="iconFor(p.modules, p.frequency)" class="size-5" />
                                </span>
                                <div class="min-w-0 flex-1">
                                    <div class="flex flex-wrap items-baseline justify-between gap-x-4 gap-y-1">
                                        <span class="font-medium">{{ p.service }}</span>
                                        <span v-if="p.next_visit" class="text-sm">Next visit {{ formatDay(p.next_visit) }}</span>
                                    </div>
                                    <p class="text-muted-foreground text-sm">
                                        {{ howOften(p) }}<template v-if="p.agent">, with {{ p.agent }}</template>
                                    </p>
                                    <div
                                        v-if="p.hold_from || p.status === 'paused' || (p.ends_on && !p.one_time)"
                                        class="mt-2 flex flex-wrap gap-1.5"
                                    >
                                        <span
                                            v-if="p.hold_from"
                                            class="rounded-full bg-sky-500/15 px-2.5 py-0.5 text-xs font-medium text-sky-700 dark:text-sky-400"
                                            >Paused {{ formatDay(p.hold_from) }} to {{ formatDay(p.hold_to) }}</span
                                        >
                                        <span
                                            v-if="p.status === 'paused'"
                                            class="bg-muted text-muted-foreground rounded-full px-2.5 py-0.5 text-xs font-medium"
                                            >Paused</span
                                        >
                                        <span
                                            v-if="p.ends_on && !p.one_time"
                                            class="bg-muted text-muted-foreground rounded-full px-2.5 py-0.5 text-xs font-medium"
                                            >Last visit {{ formatDay(p.ends_on) }}</span
                                        >
                                    </div>
                                    <p v-if="p.pending" class="mt-2 text-sm text-amber-700 dark:text-amber-400">
                                        {{ p.pending === 'hold' ? 'Pause' : p.one_time ? 'Cancellation' : 'Stop' }} requested. Waiting for
                                        {{ company }} to confirm.
                                    </p>
                                    <div v-else class="mt-2 flex gap-4 text-sm">
                                        <button
                                            v-if="!p.one_time && p.status === 'active'"
                                            type="button"
                                            class="text-muted-foreground hover:text-foreground focus-visible:ring-ring rounded font-medium underline-offset-4 hover:underline focus-visible:ring-2 focus-visible:outline-none"
                                            @click="start('hold', { target: p })"
                                        >
                                            Pause
                                        </button>
                                        <button
                                            type="button"
                                            class="text-muted-foreground hover:text-foreground focus-visible:ring-ring rounded font-medium underline-offset-4 hover:underline focus-visible:ring-2 focus-visible:outline-none"
                                            @click="start('cancel', { target: p })"
                                        >
                                            {{ p.one_time ? 'Cancel this visit' : 'Stop' }}
                                        </button>
                                    </div>
                                </div>
                            </li>
                        </ul>
                    </div>
                </div>
            </section>

            <!-- the company's price list -->
            <section aria-labelledby="add">
                <h2 id="add" class="font-display text-xl font-semibold tracking-tight">Add a service</h2>
                <p class="text-muted-foreground mt-1 text-sm">Pick what you’d like. {{ company }} confirms the day and lets you know here.</p>

                <ul v-if="props.services.length" class="divide-border border-border mt-4 divide-y rounded-2xl border">
                    <li v-for="s in props.services" :key="s.id" class="grid gap-5 p-5 sm:grid-cols-[1fr_11rem] sm:p-6">
                        <div class="flex gap-4">
                            <span class="text-primary grid size-11 shrink-0 place-items-center rounded-xl bg-[hsl(var(--brand)/0.1)]">
                                <component :is="iconFor(s.modules, s.frequency)" class="size-5" />
                            </span>
                            <div class="min-w-0">
                                <h3 class="font-display text-lg leading-snug font-semibold">{{ s.name }}</h3>
                                <p v-if="s.description" class="text-muted-foreground mt-1 max-w-[60ch] text-[15px]">{{ s.description }}</p>

                                <ul v-if="s.tasks.length || extras(s.modules).length" class="mt-3 grid gap-x-6 gap-y-1.5 text-sm sm:grid-cols-2">
                                    <li v-for="t in shownTasks(s)" :key="`t-${t}`" class="flex gap-2">
                                        <Check class="text-primary mt-0.5 size-4 shrink-0" />{{ t }}
                                    </li>
                                    <li v-for="x in extras(s.modules)" :key="`x-${x}`" class="flex gap-2">
                                        <Check class="text-primary mt-0.5 size-4 shrink-0" />{{ x }}
                                    </li>
                                </ul>
                                <button
                                    v-if="longList(s)"
                                    type="button"
                                    class="text-primary focus-visible:ring-ring mt-2 rounded text-sm font-medium underline-offset-4 hover:underline focus-visible:ring-2 focus-visible:outline-none"
                                    @click="toggle(s.id)"
                                >
                                    {{ expanded.includes(s.id) ? 'Show less' : `Show all ${s.tasks.length} steps` }}
                                </button>
                            </div>
                        </div>

                        <div class="flex flex-wrap items-end justify-between gap-3 sm:flex-col sm:items-end sm:justify-start sm:text-right">
                            <div>
                                <p>
                                    <span class="font-display text-[1.75rem] leading-none font-semibold tracking-tight">{{ price(s.price) }}</span>
                                    <span class="text-muted-foreground ml-1 text-sm">{{
                                        s.frequency === 'one_time' ? 'for the job' : 'per visit'
                                    }}</span>
                                </p>
                                <p class="text-muted-foreground mt-1.5 text-sm">{{ soldAs(s.frequency) }}</p>
                                <p v-if="s.minutes" class="text-muted-foreground flex items-center gap-1 text-sm sm:justify-end">
                                    <Clock class="size-3.5" /> About {{ s.minutes }} minutes
                                </p>
                            </div>
                            <div class="sm:mt-1">
                                <p v-if="poolsWith(s.id).length" class="mb-2 text-sm font-medium text-emerald-700 dark:text-emerald-400">
                                    {{ manyPools ? `On ${poolsWith(s.id).join(', ')}` : 'You have this' }}
                                </p>
                                <Button
                                    :variant="poolsWith(s.id).length ? 'outline' : 'default'"
                                    :disabled="!props.pools.length"
                                    @click="start('service', { svc: s })"
                                >
                                    {{ poolsWith(s.id).length ? 'Request another' : 'Request' }}
                                </Button>
                            </div>
                        </div>
                    </li>
                </ul>
                <p v-else class="border-border text-muted-foreground mt-4 rounded-2xl border p-5 text-[15px]">
                    {{ company }} hasn’t listed services here yet. Send them a note below and they’ll get you set up.
                </p>
                <p v-if="props.services.length && !props.pools.length" class="text-muted-foreground mt-2 text-sm">
                    Your pool isn’t on your account yet. Send {{ company }} a note and they’ll add it.
                </p>
            </section>

            <!-- anything that isn't on the list -->
            <section
                class="border-border grid grid-cols-[auto_1fr] gap-x-4 gap-y-3 rounded-2xl border p-5 sm:grid-cols-[auto_1fr_auto] sm:items-center"
            >
                <span class="bg-muted grid size-10 shrink-0 place-items-center rounded-xl"><MessageSquare class="size-5" /></span>
                <div class="min-w-0">
                    <h2 class="font-medium">Something else?</h2>
                    <p class="text-muted-foreground text-sm">
                        Questions, a new gate code, a one-off problem. Send a note and {{ company }} will reply here.
                    </p>
                </div>
                <Button variant="outline" class="col-start-2 justify-self-start sm:col-start-auto" @click="start('other')">Send a note</Button>
            </section>

            <!-- what they've asked for, and the answers -->
            <section v-if="props.requests.length" aria-labelledby="sent">
                <h2 id="sent" class="font-display text-xl font-semibold tracking-tight">Your requests</h2>
                <ul class="divide-border mt-2 divide-y">
                    <li v-for="r in props.requests" :key="r.id" class="py-4">
                        <div class="flex items-start justify-between gap-3">
                            <div class="min-w-0">
                                <p class="font-medium">{{ r.title }}</p>
                                <p class="text-muted-foreground text-sm">{{ sentLine(r) }}</p>
                            </div>
                            <span class="shrink-0 rounded-full px-2.5 py-0.5 text-xs font-medium" :class="statusClass(r.status)">{{
                                r.status_label
                            }}</span>
                        </div>
                        <p v-if="r.message" class="text-muted-foreground mt-1.5 text-sm">You wrote: “{{ r.message }}”</p>
                        <p v-if="r.plan && r.type === 'service' && r.status === 'approved'" class="mt-1.5 text-sm">Set up: {{ r.plan }}.</p>
                        <div v-if="r.reply" class="bg-muted mt-2 max-w-prose rounded-2xl rounded-tl-md px-4 py-2.5 text-sm">
                            <p class="text-muted-foreground text-xs font-medium">{{ company }}</p>
                            <p>{{ r.reply }}</p>
                        </div>
                    </li>
                </ul>
            </section>
        </div>

        <!-- the request form -->
        <Dialog v-model:open="open">
            <DialogContent class="max-h-[90vh] overflow-y-auto">
                <form class="space-y-4 text-sm" @submit.prevent="submit">
                    <DialogHeader>
                        <DialogTitle class="font-display text-xl">{{ heading }}</DialogTitle>
                        <DialogDescription>{{ subheading }}</DialogDescription>
                    </DialogHeader>

                    <!-- which pool: for a new service when they have several, or optional for a note -->
                    <div v-if="(kind === 'service' && manyPools) || (kind === 'other' && props.pools.length > 1)" class="grid gap-1.5">
                        <Label for="pool">{{ kind === 'other' ? 'Which pool is this about? (optional)' : 'Which pool?' }}</Label>
                        <select
                            id="pool"
                            v-model="form.pool_id"
                            class="border-input bg-background h-9 w-60 max-w-full rounded-md border px-2 text-sm"
                        >
                            <option value="">{{ kind === 'other' ? 'Not about one pool' : 'Pick a pool…' }}</option>
                            <option v-for="p in props.pools" :key="p.id" :value="p.id">{{ p.name }}</option>
                        </select>
                        <p v-if="form.errors.pool_id" class="text-xs text-red-600">{{ form.errors.pool_id }}</p>
                    </div>

                    <p v-if="alreadyHas" class="rounded-lg border border-amber-500/30 bg-amber-500/5 p-3 text-amber-700 dark:text-amber-400">
                        {{ alreadyHas.pool }} already has {{ alreadyHas.service }}, {{ howOften(alreadyHas).toLowerCase() }}. Send this only if you’d
                        like an extra visit. To change the day, send a note instead.
                    </p>

                    <div v-if="kind === 'service'" class="grid gap-1.5">
                        <Label for="date"
                            >{{ service?.frequency === 'one_time' ? 'Preferred date' : 'Preferred start' }}
                            <span class="text-muted-foreground font-normal">(optional)</span></Label
                        >
                        <Input id="date" v-model="form.preferred_date" type="date" :min="props.today" class="w-44" />
                        <p v-if="form.errors.preferred_date" class="text-xs text-red-600">{{ form.errors.preferred_date }}</p>
                        <p v-else class="text-muted-foreground text-xs">Leave blank for as soon as possible.</p>
                    </div>

                    <div v-else-if="kind === 'hold'" class="flex flex-wrap gap-3">
                        <div class="grid gap-1.5">
                            <Label for="from">First day to skip</Label>
                            <Input id="from" v-model="form.preferred_date" type="date" :min="props.today" class="w-44" />
                            <p v-if="form.errors.preferred_date" class="text-xs text-red-600">{{ form.errors.preferred_date }}</p>
                        </div>
                        <div class="grid gap-1.5">
                            <Label for="to">Last day to skip</Label>
                            <Input id="to" v-model="form.ends_on" type="date" :min="form.preferred_date || props.today" class="w-44" />
                            <p v-if="form.errors.ends_on" class="text-xs text-red-600">{{ form.errors.ends_on }}</p>
                        </div>
                    </div>

                    <div v-else-if="kind === 'cancel' && !plan?.one_time" class="grid gap-1.5">
                        <Label for="last">Last visit <span class="text-muted-foreground font-normal">(optional)</span></Label>
                        <Input id="last" v-model="form.preferred_date" type="date" :min="props.today" class="w-44" />
                        <p v-if="form.errors.preferred_date" class="text-xs text-red-600">{{ form.errors.preferred_date }}</p>
                        <p v-else class="text-muted-foreground text-xs">Leave blank to stop as soon as possible.</p>
                    </div>

                    <p v-if="form.errors.service_subscription_id" class="text-xs text-red-600">{{ form.errors.service_subscription_id }}</p>

                    <div class="grid gap-1.5">
                        <Label for="message">
                            {{ kind === 'other' ? 'Your note' : 'Anything they should know?' }}
                            <span v-if="kind !== 'other'" class="text-muted-foreground font-normal">(optional)</span>
                        </Label>
                        <textarea
                            id="message"
                            v-model="form.message"
                            rows="3"
                            class="border-input bg-background rounded-md border px-3 py-2 text-sm"
                            :placeholder="kind === 'service' ? 'Gate code, pets, best time to come…' : ''"
                        ></textarea>
                        <p v-if="form.errors.message" class="text-xs text-red-600">{{ form.errors.message }}</p>
                    </div>

                    <DialogFooter class="gap-2">
                        <Button type="button" variant="outline" @click="open = false">Close</Button>
                        <Button type="submit" :disabled="form.processing">{{ kind === 'other' ? 'Send note' : 'Send request' }}</Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    </AppLayout>
</template>

<style scoped>
/* A strip of pool waterline tile in the company's colour: two rows of small
   square tiles with light grout, under the next-visit panel. */
.waterline {
    background:
        linear-gradient(90deg, hsl(var(--background) / 0.7) 1px, transparent 1px) 0 0 / 8px 8px,
        linear-gradient(hsl(var(--background) / 0.7) 1px, transparent 1px) 0 0 / 8px 8px,
        repeating-conic-gradient(hsl(var(--brand) / 0.55) 0 25%, hsl(var(--brand) / 0.28) 0 50%) 0 0 / 16px 16px;
}
</style>
