<script setup lang="ts">
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/AppLayout.vue';
import { formatDay } from '@/lib/utils';
import { type BreadcrumbItem } from '@/types';
import { Head, useForm, usePage } from '@inertiajs/vue3';
import { ClipboardList, Inbox, MessageSquare, PauseCircle, StopCircle } from 'lucide-vue-next';
import { computed, nextTick, ref, watch } from 'vue';

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

interface Service {
    id: number;
    name: string;
    description: string | null;
    frequency: string;
    offer: string;
}

interface PoolOption {
    id: number;
    name: string;
    has_service: boolean;
}

const props = defineProps<{
    requests: ServiceRequest[];
    services: Service[];
    pools: PoolOption[];
    today: string;
}>();

const breadcrumbs: BreadcrumbItem[] = [{ title: 'Services', href: '/requests' }];

type Kind = 'service' | 'hold' | 'cancel' | 'other';
const active = ref<Kind | null>(null);
const service = ref<Service | null>(null);
const panel = ref<HTMLElement | null>(null);

// Built from a function so reset() always starts blank — never with the last request's note or dates.
const form = useForm(() => ({
    type: 'service' as Kind,
    service_type_id: null as number | null,
    pool_id: '' as number | string,
    preferred_date: '',
    ends_on: '',
    message: '',
}));

// Pause/stop only make sense for a pool that has service.
const servicedPools = computed(() => props.pools.filter((p) => p.has_service));
const poolChoices = computed(() => (active.value === 'hold' || active.value === 'cancel' ? servicedPools.value : props.pools));

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

function start(kind: Kind, svc: Service | null = null) {
    form.reset();
    form.clearErrors();
    form.type = kind;
    form.service_type_id = svc?.id ?? null;
    service.value = svc;
    active.value = kind;
    const choices = poolChoices.value;
    const preferred = choices.find((p) => p.id === preferredPool.value);
    form.pool_id = preferred?.id ?? (choices.length === 1 && kind !== 'other' ? choices[0].id : '');
    nextTick(() => panel.value?.scrollIntoView({ behavior: 'smooth', block: 'nearest' }));
}

function cancel() {
    active.value = null;
    service.value = null;
}

function submit() {
    form.post('/requests', { preserveScroll: true, onSuccess: () => cancel() });
}

const heading = computed(
    () =>
        ({
            service: `Request ${service.value?.name ?? 'a service'}`,
            hold: 'Pause service',
            cancel: 'Stop service',
            other: 'Something else',
        })[active.value ?? 'other'],
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
            <!-- the company's catalog -->
            <section>
                <h2 class="text-lg font-semibold">Request a service</h2>
                <p class="text-muted-foreground mb-3 text-sm">Pick what you need — your service company confirms the day and lets you know.</p>

                <div v-if="props.services.length" class="grid gap-3 sm:grid-cols-2">
                    <div
                        v-for="s in props.services"
                        :key="s.id"
                        class="bg-card flex flex-col rounded-xl border p-4 text-sm"
                        :class="service?.id === s.id && active === 'service' ? 'border-primary ring-primary/30 ring-2' : ''"
                    >
                        <div class="flex items-baseline justify-between gap-3">
                            <span class="font-semibold">{{ s.name }}</span>
                        </div>
                        <span class="text-primary text-xs font-semibold">{{ s.offer }}</span>
                        <p v-if="s.description" class="text-muted-foreground mt-1.5 flex-1">{{ s.description }}</p>
                        <div class="mt-3">
                            <Button size="sm" :disabled="!props.pools.length" @click="start('service', s)">Request</Button>
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

            <!-- other changes -->
            <section>
                <h2 class="mb-2 font-medium">Need a change?</h2>
                <div class="flex flex-wrap gap-2">
                    <Button
                        size="sm"
                        variant="outline"
                        :disabled="!servicedPools.length"
                        :title="servicedPools.length ? '' : 'Only pools with regular service can be paused'"
                        @click="start('hold')"
                        ><PauseCircle class="mr-1 size-4" /> Pause service</Button
                    >
                    <Button
                        size="sm"
                        variant="outline"
                        :disabled="!servicedPools.length"
                        :title="servicedPools.length ? '' : 'None of your pools has service to stop'"
                        @click="start('cancel')"
                        ><StopCircle class="mr-1 size-4" /> Stop service</Button
                    >
                    <Button size="sm" variant="outline" @click="start('other')"><MessageSquare class="mr-1 size-4" /> Something else</Button>
                </div>
            </section>

            <!-- the request form -->
            <div v-if="active" ref="panel">
                <form class="border-primary/40 space-y-4 rounded-xl border p-4 text-sm" @submit.prevent="submit">
                    <div>
                        <h2 class="font-medium">{{ heading }}</h2>
                        <p v-if="active === 'service' && service" class="text-muted-foreground text-xs">{{ service.offer }}</p>
                    </div>

                    <div v-if="poolChoices.length" class="grid gap-1.5">
                        <Label for="pool">{{ active === 'other' ? 'Pool (optional)' : 'Which pool?' }}</Label>
                        <select
                            id="pool"
                            v-model="form.pool_id"
                            class="border-input bg-background h-9 w-60 max-w-full rounded-md border px-2 text-sm"
                        >
                            <option value="">{{ active === 'other' ? 'Any / all' : 'Pick a pool…' }}</option>
                            <option v-for="p in poolChoices" :key="p.id" :value="p.id">{{ p.name }}</option>
                        </select>
                        <p v-if="form.errors.pool_id" class="text-xs text-red-600">{{ form.errors.pool_id }}</p>
                    </div>

                    <div v-if="active === 'service'" class="grid gap-1.5">
                        <Label for="date"
                            >{{ service?.frequency === 'one_time' ? 'Preferred date' : 'Preferred start' }}
                            <span class="text-muted-foreground font-normal">(optional)</span></Label
                        >
                        <Input id="date" v-model="form.preferred_date" type="date" :min="props.today" class="w-44" />
                        <p v-if="form.errors.preferred_date" class="text-xs text-red-600">{{ form.errors.preferred_date }}</p>
                        <p v-else class="text-muted-foreground text-xs">Leave blank for as soon as possible.</p>
                    </div>

                    <div v-else-if="active === 'hold'" class="flex flex-wrap gap-3">
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

                    <div v-else-if="active === 'cancel'" class="grid gap-1.5">
                        <Label for="last">Last service day <span class="text-muted-foreground font-normal">(optional)</span></Label>
                        <Input id="last" v-model="form.preferred_date" type="date" :min="props.today" class="w-44" />
                        <p v-if="form.errors.preferred_date" class="text-xs text-red-600">{{ form.errors.preferred_date }}</p>
                        <p v-else class="text-muted-foreground text-xs">Leave blank to stop as soon as possible.</p>
                    </div>

                    <div class="grid gap-1.5">
                        <Label for="message">
                            {{ active === 'other' ? 'What do you need?' : 'Anything we should know?' }}
                            <span v-if="active !== 'other'" class="text-muted-foreground font-normal">(optional)</span>
                        </Label>
                        <textarea
                            id="message"
                            v-model="form.message"
                            rows="3"
                            class="border-input bg-background rounded-md border px-3 py-2 text-sm"
                            :placeholder="active === 'service' ? 'Gate code, pets, best time to come…' : ''"
                        ></textarea>
                        <p v-if="form.errors.message" class="text-xs text-red-600">{{ form.errors.message }}</p>
                    </div>

                    <div class="flex justify-end gap-2">
                        <Button type="button" variant="outline" size="sm" @click="cancel">Cancel</Button>
                        <Button type="submit" size="sm" :disabled="form.processing">Send request</Button>
                    </div>
                </form>
            </div>

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
                            <p v-if="r.plan" class="mt-1 text-emerald-700 dark:text-emerald-400">{{ r.plan }}</p>
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
    </AppLayout>
</template>
