<script setup lang="ts">
import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/AppLayout.vue';
import { formatDay } from '@/lib/utils';
import { type BreadcrumbItem } from '@/types';
import { Head, useForm, usePage } from '@inertiajs/vue3';
import { CalendarCheck, ClipboardList, Inbox, MessageSquare } from 'lucide-vue-next';
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
    one_time: boolean;
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
    offer: string;
}

const props = defineProps<{
    requests: ServiceRequest[];
    plans: Plan[];
    services: Service[];
    pools: { id: number; name: string }[];
    today: string;
}>();

const breadcrumbs: BreadcrumbItem[] = [{ title: 'Services', href: '/requests' }];

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

// Which of their pools already have a catalog service ("You have this").
const poolsWith = (serviceId: number) => [...new Set(props.plans.filter((p) => p.service_type_id === serviceId).map((p) => p.pool ?? ''))];

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
const page = usePage();
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
    return 'Something else';
});
const subheading = computed(() => {
    if (kind.value === 'service') return service.value?.offer ?? '';
    if (plan.value) return [plan.value.schedule, plan.value.pool].filter(Boolean).join(' · ');
    return 'Questions, gate codes, a one-off problem — your service company will reply here.';
});

// Asking for a service the chosen pool already has — fine (an extra visit day), but say so.
const alreadyHas = computed(() =>
    kind.value === 'service' ? props.plans.find((p) => p.service_type_id === form.service_type_id && p.pool_id === Number(form.pool_id)) : undefined,
);

const statusClass = (status: string) =>
    ({
        pending: 'bg-amber-500/15 text-amber-600 dark:text-amber-400',
        approved: 'bg-emerald-500/15 text-emerald-600 dark:text-emerald-400',
    })[status] ?? 'bg-muted text-muted-foreground';
</script>

<template>
    <Head title="Services" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="mx-auto flex w-full max-w-3xl flex-1 flex-col gap-6 p-4">
            <!-- what they have now -->
            <section>
                <h2 class="text-lg font-semibold">Your services</h2>
                <p class="text-muted-foreground mb-3 text-sm">Pause or stop any one of them — the rest carry on as usual.</p>

                <div v-if="props.plans.length" class="space-y-3">
                    <div v-for="g in plansByPool" :key="g.pool ?? ''" class="border-border overflow-hidden rounded-xl border">
                        <h3 v-if="manyPools" class="bg-muted/40 border-border border-b px-4 py-1.5 text-xs font-medium">{{ g.pool }}</h3>
                        <ul class="divide-border divide-y text-sm">
                            <li v-for="p in g.plans" :key="p.id" class="flex flex-wrap items-center justify-between gap-3 px-4 py-3">
                                <div class="min-w-0">
                                    <div class="font-medium">{{ p.service }}</div>
                                    <div class="text-muted-foreground">
                                        {{ p.schedule }}<span v-if="p.agent"> · with {{ p.agent }}</span
                                        ><span v-if="!manyPools && p.pool"> · {{ p.pool }}</span>
                                    </div>
                                    <div class="mt-1 flex flex-wrap gap-1.5 empty:hidden">
                                        <span
                                            v-if="p.hold_from"
                                            class="rounded-full bg-sky-500/15 px-2 py-0.5 text-xs font-medium text-sky-600 dark:text-sky-400"
                                            >Paused {{ formatDay(p.hold_from) }} – {{ formatDay(p.hold_to) }}</span
                                        >
                                        <span
                                            v-if="p.status === 'paused'"
                                            class="bg-muted text-muted-foreground rounded-full px-2 py-0.5 text-xs font-medium"
                                            >Paused</span
                                        >
                                        <span
                                            v-if="p.pending"
                                            class="rounded-full bg-amber-500/15 px-2 py-0.5 text-xs font-medium text-amber-600 dark:text-amber-400"
                                            >{{ p.pending === 'hold' ? 'Pause requested' : p.one_time ? 'Cancel requested' : 'Stop requested' }}</span
                                        >
                                    </div>
                                </div>
                                <div class="flex shrink-0 gap-2">
                                    <template v-if="p.pending">
                                        <span class="text-muted-foreground text-xs">Waiting for your service company</span>
                                    </template>
                                    <template v-else>
                                        <Button
                                            v-if="!p.one_time && p.status === 'active'"
                                            size="sm"
                                            variant="outline"
                                            @click="start('hold', { target: p })"
                                            >Pause</Button
                                        >
                                        <Button size="sm" variant="outline" @click="start('cancel', { target: p })">{{
                                            p.one_time ? 'Cancel' : 'Stop'
                                        }}</Button>
                                    </template>
                                </div>
                            </li>
                        </ul>
                    </div>
                </div>
                <p v-else class="text-muted-foreground rounded-xl border p-4 text-sm">
                    <CalendarCheck class="mr-1 inline size-4 opacity-60" />
                    No regular service yet — request one below.
                </p>
            </section>

            <!-- the company's catalog -->
            <section>
                <h2 class="text-lg font-semibold">Request a service</h2>
                <p class="text-muted-foreground mb-3 text-sm">Pick what you need — your service company confirms the day and lets you know.</p>

                <div v-if="props.services.length" class="grid gap-3 sm:grid-cols-2">
                    <div v-for="s in props.services" :key="s.id" class="bg-card flex flex-col rounded-xl border p-4 text-sm">
                        <div class="flex items-start justify-between gap-2">
                            <span class="font-semibold">{{ s.name }}</span>
                            <span
                                v-if="poolsWith(s.id).length"
                                class="shrink-0 rounded-full bg-emerald-500/15 px-2 py-0.5 text-xs font-medium text-emerald-600 dark:text-emerald-400"
                                :title="manyPools ? `On ${poolsWith(s.id).join(', ')}` : ''"
                                >{{ manyPools ? `On ${poolsWith(s.id).join(', ')}` : 'You have this' }}</span
                            >
                        </div>
                        <span class="text-primary text-xs font-semibold">{{ s.offer }}</span>
                        <p v-if="s.description" class="text-muted-foreground mt-1.5 flex-1">{{ s.description }}</p>
                        <div class="mt-3">
                            <Button
                                size="sm"
                                :variant="poolsWith(s.id).length ? 'outline' : 'default'"
                                :disabled="!props.pools.length"
                                @click="start('service', { svc: s })"
                            >
                                {{ poolsWith(s.id).length ? 'Request another' : 'Request' }}
                            </Button>
                        </div>
                    </div>
                </div>
                <p v-else class="text-muted-foreground rounded-xl border p-4 text-sm">
                    <ClipboardList class="mr-1 inline size-4 opacity-60" />
                    Your service company hasn't listed services here yet — use <strong>Something else</strong> below to ask.
                </p>
                <p v-if="props.services.length && !props.pools.length" class="text-muted-foreground mt-2 text-xs">
                    Your pool isn't set up on your account yet — use Something else and your service company will add it.
                </p>
            </section>

            <!-- anything that isn't in the catalog -->
            <section class="flex flex-wrap items-center justify-between gap-3 rounded-xl border p-4 text-sm">
                <div>
                    <h2 class="font-medium">Anything else?</h2>
                    <p class="text-muted-foreground">Questions, gate codes, a one-off problem — send a note.</p>
                </div>
                <Button size="sm" variant="outline" @click="start('other')"><MessageSquare class="mr-1 size-4" /> Something else</Button>
            </section>

            <!-- what they've asked for, and the answers -->
            <section class="border-border overflow-hidden rounded-xl border">
                <h2 class="border-border border-b px-4 py-2 font-medium">Your requests</h2>
                <ul class="divide-border divide-y text-sm">
                    <li v-for="r in props.requests" :key="r.id" class="flex items-start justify-between gap-3 px-4 py-3">
                        <div class="min-w-0">
                            <span class="font-medium">{{ r.title }}</span>
                            <span v-if="r.pool" class="text-muted-foreground"> · {{ r.pool }}</span>
                            <p class="text-muted-foreground text-xs">
                                Sent {{ formatDay(r.on) }}
                                <template v-if="r.type === 'hold'"> · {{ formatDay(r.preferred_date) }} – {{ formatDay(r.ends_on) }}</template>
                                <template v-else-if="r.preferred_date">
                                    · {{ r.type === 'cancel' ? 'last service' : 'from' }} {{ formatDay(r.preferred_date) }}</template
                                >
                            </p>
                            <p v-if="r.message" class="text-muted-foreground mt-0.5">{{ r.message }}</p>
                            <p v-if="r.plan && r.type === 'service'" class="mt-1 text-emerald-700 dark:text-emerald-400">{{ r.plan }}</p>
                            <p v-if="r.reply" class="bg-muted/50 mt-1.5 rounded-md px-2.5 py-1.5">
                                <span class="font-medium">{{ r.status === 'declined' ? 'Why' : 'Reply' }}:</span> {{ r.reply }}
                            </p>
                        </div>
                        <span class="shrink-0 rounded-full px-2 py-0.5 text-xs font-medium" :class="statusClass(r.status)">{{ r.status_label }}</span>
                    </li>
                    <li v-if="props.requests.length === 0" class="text-muted-foreground px-4 py-8 text-center">
                        <Inbox class="mx-auto mb-2 size-6 opacity-50" />
                        No requests yet.
                    </li>
                </ul>
            </section>
        </div>

        <!-- the request form -->
        <Dialog v-model:open="open">
            <DialogContent class="max-h-[90vh] overflow-y-auto">
                <form class="space-y-4 text-sm" @submit.prevent="submit">
                    <DialogHeader>
                        <DialogTitle>{{ heading }}</DialogTitle>
                        <DialogDescription>{{ subheading }}</DialogDescription>
                    </DialogHeader>

                    <!-- which pool: for a new service when they have several, or optional for a note -->
                    <div v-if="(kind === 'service' && manyPools) || (kind === 'other' && props.pools.length)" class="grid gap-1.5">
                        <Label for="pool">{{ kind === 'other' ? 'Pool (optional)' : 'Which pool?' }}</Label>
                        <select
                            id="pool"
                            v-model="form.pool_id"
                            class="border-input bg-background h-9 w-60 max-w-full rounded-md border px-2 text-sm"
                        >
                            <option value="">{{ kind === 'other' ? 'Any / all' : 'Pick a pool…' }}</option>
                            <option v-for="p in props.pools" :key="p.id" :value="p.id">{{ p.name }}</option>
                        </select>
                        <p v-if="form.errors.pool_id" class="text-xs text-red-600">{{ form.errors.pool_id }}</p>
                    </div>

                    <p v-if="alreadyHas" class="rounded-lg border border-amber-500/30 bg-amber-500/5 p-3 text-amber-700 dark:text-amber-400">
                        {{ alreadyHas.pool }} already has {{ alreadyHas.service }} ({{ alreadyHas.schedule }}). Send this only if you'd like an extra
                        visit — to change the day, use Something else.
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
                        <Label for="last">Last service day <span class="text-muted-foreground font-normal">(optional)</span></Label>
                        <Input id="last" v-model="form.preferred_date" type="date" :min="props.today" class="w-44" />
                        <p v-if="form.errors.preferred_date" class="text-xs text-red-600">{{ form.errors.preferred_date }}</p>
                        <p v-else class="text-muted-foreground text-xs">Leave blank to stop as soon as possible.</p>
                    </div>

                    <p v-if="form.errors.service_subscription_id" class="text-xs text-red-600">{{ form.errors.service_subscription_id }}</p>

                    <div class="grid gap-1.5">
                        <Label for="message">
                            {{ kind === 'other' ? 'What do you need?' : 'Anything we should know?' }}
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
                        <Button type="button" variant="outline" size="sm" @click="open = false">Close</Button>
                        <Button type="submit" size="sm" :disabled="form.processing">Send request</Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    </AppLayout>
</template>
