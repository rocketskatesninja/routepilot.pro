<script setup lang="ts">
import ListTable from '@/components/ListTable.vue';
import MasterDetail from '@/components/MasterDetail.vue';
import NoteBubble from '@/components/NoteBubble.vue';
import PlanScheduleFields from '@/components/PlanScheduleFields.vue';
import Chip from '@/components/pane/Chip.vue';
import IconTile from '@/components/pane/IconTile.vue';
import PaneFacts from '@/components/pane/PaneFacts.vue';
import PaneHeader from '@/components/pane/PaneHeader.vue';
import PaneItem from '@/components/pane/PaneItem.vue';
import PaneList from '@/components/pane/PaneList.vue';
import PaneSection from '@/components/pane/PaneSection.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/AppLayout.vue';
import { POOL_TYPES } from '@/lib/labels';
import { customerLink, poolLink, telLink } from '@/lib/links';
import { cap } from '@/lib/portal';
import { formatDay } from '@/lib/utils';
import { type BreadcrumbItem } from '@/types';
import { type Paginated } from '@/types/pagination';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { CalendarCheck, Check, CirclePause, CircleStop, ClipboardList, Inbox, MessageSquare, UserPlus, X } from 'lucide-vue-next';
import { computed, ref, watch } from 'vue';

interface RequestRow {
    id: number;
    type: string;
    title: string;
    customer: string | null;
    pool: string | null;
    preferred_date: string | null;
    on: string | null;
    status: string;
    status_label: string;
}

interface PlanLine {
    id: number;
    service_type_id: number;
    service: string | null;
    schedule: string;
    status: string;
    agent: string | null;
}

interface RequestDetail extends RequestRow {
    customer_id: number;
    pool_id: number | null;
    for_service: boolean;
    service: { id: number; name: string; offer: string; description: string | null; is_active: boolean } | null;
    ends_on: string | null;
    message: string | null;
    reply: string | null;
    answered_on: string | null;
    plan: string | null;
    plan_service: string | null;
    pool_plans: PlanLine[];
    defaults: {
        frequency: string;
        preferred_day: string;
        day_of_month: number;
        starts_on: string;
        assigned_agent_id: number | null;
    } | null;
}

interface LeadRow {
    id: number;
    name: string;
    contact: string | null;
    source: string;
    service: string | null;
    on: string | null;
    status: string;
}

// The detail's `service` is the full pick (name + price), not just the name the row shows.
interface LeadDetail extends Omit<LeadRow, 'service'> {
    email: string | null;
    phone: string | null;
    message: string | null;
    details: { label: string; value: string }[];
    service: { name: string; offer: string } | null;
    customer: { id: number; name: string } | null;
    convert: { first_name: string; last_name: string; email: string; phone: string; pool_type: string; pool_volume: number | null };
}

const props = defineProps<{
    tab: 'requests' | 'leads';
    status: 'open' | 'closed';
    rows: Paginated<RequestRow | LeadRow>;
    selected: RequestDetail | LeadDetail | null;
    counts: { requests: number; leads: number };
    agents: { id: number; name: string }[];
}>();

const breadcrumbs: BreadcrumbItem[] = [{ title: 'Requests', href: '/inbox' }];

const requestRows = computed(() => props.rows as Paginated<RequestRow>);
const leadRows = computed(() => props.rows as Paginated<LeadRow>);
const request = computed(() => (props.tab === 'requests' ? (props.selected as RequestDetail | null) : null));
const lead = computed(() => (props.tab === 'leads' ? (props.selected as LeadDetail | null) : null));

// The URL keeps only what differs from the defaults (requests tab, "To do").
const visit = (tab: string, status: string, selected?: number, keep = true) =>
    router.get(
        '/inbox',
        { tab: tab === 'leads' ? tab : undefined, status: status === 'closed' ? status : undefined, selected },
        { preserveState: keep, preserveScroll: keep },
    );
const setTab = (tab: string) => visit(tab, 'open', undefined, false);
const setStatus = (status: string) => visit(props.tab, status);
const open = (id: number) => visit(props.tab, props.status, id);
const closePane = () => {
    if (mode.value !== 'none') mode.value = 'none';
    else visit(props.tab, props.status);
};

const tabs = computed(() => [
    { key: 'requests', label: 'Customer requests', count: props.counts.requests },
    { key: 'leads', label: 'Website leads', count: props.counts.leads },
]);

const statusClass = (status: string) =>
    ({
        pending: 'bg-amber-500/15 text-amber-600 dark:text-amber-400',
        new: 'bg-amber-500/15 text-amber-600 dark:text-amber-400',
        approved: 'bg-emerald-500/15 text-emerald-600 dark:text-emerald-400',
        converted: 'bg-emerald-500/15 text-emerald-600 dark:text-emerald-400',
        contacted: 'bg-sky-500/15 text-sky-600 dark:text-sky-400',
        declined: 'bg-muted text-muted-foreground',
        archived: 'bg-muted text-muted-foreground',
    })[status] ?? 'bg-muted text-muted-foreground';

const sources: Record<string, string> = { quote: 'Website quote', booking: 'Booking form', contact: 'Contact form', chatbot: 'Website chat' };
const leadStatuses: Record<string, string> = { new: 'New', contacted: 'Contacted', converted: 'Customer', archived: 'Archived' };
const requestIcon: Record<string, typeof Inbox> = { service: ClipboardList, hold: CirclePause, cancel: CircleStop, other: MessageSquare };
const firstWord = (name: string | null) => name?.split(' ')[0] ?? null;

// --- answering a request: one panel at a time in the detail pane ---
const mode = ref<'none' | 'approve' | 'decline' | 'convert'>('none');
watch(
    () => props.selected?.id,
    () => (mode.value = 'none'),
);

// Forms are built from functions so reset() always starts blank — never with
// the note or reason typed for the previous request.
const approveForm = useForm(() => ({
    frequency: 'weekly',
    preferred_day: '',
    day_of_month: '' as number | string,
    starts_on: '',
    ends_on: '',
    assigned_agent_id: '' as number | string,
    reply: '',
}));
const declineForm = useForm(() => ({ reply: '' }));

function openApprove() {
    const d = request.value?.defaults;
    approveForm.reset();
    approveForm.clearErrors();
    if (d) {
        approveForm.frequency = d.frequency;
        approveForm.preferred_day = d.preferred_day;
        approveForm.day_of_month = d.day_of_month;
        approveForm.starts_on = d.starts_on;
        approveForm.assigned_agent_id = d.assigned_agent_id ?? '';
    }
    mode.value = 'approve';
}

function openDecline() {
    declineForm.reset();
    declineForm.clearErrors();
    mode.value = 'decline';
}

const submitApprove = () => {
    if (!request.value) return;
    approveForm.post(`/requests/${request.value.id}/approve`, { preserveScroll: true, onSuccess: () => (mode.value = 'none') });
};
const submitDecline = () => {
    if (!request.value) return;
    declineForm.post(`/requests/${request.value.id}/decline`, { preserveScroll: true, onSuccess: () => (mode.value = 'none') });
};

// Approving a service the pool already has would add a second plan for it.
const duplicatePlan = computed(() => {
    const r = request.value;
    return r?.for_service ? r.pool_plans.find((p) => p.status === 'active' && p.service_type_id === r.service?.id) : undefined;
});

// What "approve" does for this kind of request, in plain words.
const approveLabel = computed(() => {
    const r = request.value;
    if (!r) return '';
    if (r.for_service) return 'Approve & schedule';
    return { hold: 'Set the pause', cancel: 'Stop service' }[r.type] ?? 'Mark done';
});
const approveExplainer = computed(() => {
    const r = request.value;
    if (!r) return '';
    if ((r.type === 'hold' || r.type === 'cancel') && !r.plan_service) {
        return 'That service has since been removed, so there is nothing to change — this just marks the request done.';
    }
    const service = `${r.plan_service} (${r.plan}) on ${r.pool}`;
    if (r.type === 'hold') {
        return `Pauses ${service} from ${formatDay(r.preferred_date)} through ${formatDay(r.ends_on)}; it comes back on its own after that. Their other services carry on.`;
    }
    if (r.type === 'cancel') {
        const when = r.preferred_date ? `after ${formatDay(r.preferred_date)}` : 'right away';
        return `Ends ${service} ${when}. Their other services carry on, and completed visits and billing history stay.`;
    }
    return 'Marks the request as handled. Add a note if you want to reply.';
});

// --- website leads ---
const setLeadStatus = (status: string) => {
    if (!lead.value) return;
    router.patch(`/leads/${lead.value.id}`, { status }, { preserveScroll: true });
};

const convertForm = useForm(() => ({
    first_name: '',
    last_name: '',
    email: '',
    phone: '',
    address_line1: '',
    city: '',
    state: '',
    zip: '',
    pool_name: 'Pool',
    pool_type: 'inground',
    pool_volume: '' as number | string,
}));

function openConvert() {
    const c = lead.value?.convert;
    if (!c) return;
    convertForm.reset();
    convertForm.clearErrors();
    Object.assign(convertForm, { ...c, pool_volume: c.pool_volume ?? '' });
    mode.value = 'convert';
}

const submitConvert = () => {
    if (!lead.value) return;
    convertForm.post(`/leads/${lead.value.id}/convert`, { onSuccess: () => (mode.value = 'none') });
};
</script>

<template>
    <Head title="Requests" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <template #filters>
            <button
                v-for="t in tabs"
                :key="t.key"
                class="shrink-0 rounded-md px-2.5 py-1 text-sm font-medium whitespace-nowrap transition-colors"
                :class="props.tab === t.key ? 'bg-primary text-primary-foreground' : 'text-muted-foreground hover:bg-muted'"
                @click="setTab(t.key)"
            >
                {{ t.label }} <span v-if="t.count" class="opacity-70">{{ t.count }}</span>
            </button>
        </template>

        <template #actions>
            <div class="border-input flex rounded-md border p-0.5">
                <button
                    v-for="s in ['open', 'closed']"
                    :key="s"
                    class="rounded px-2.5 py-1 text-sm font-medium capitalize transition-colors"
                    :class="props.status === s ? 'bg-muted text-foreground' : 'text-muted-foreground hover:text-foreground'"
                    @click="setStatus(s)"
                >
                    {{ s === 'open' ? 'To do' : 'Done' }}
                </button>
            </div>
        </template>

        <div class="flex h-full flex-1 flex-col gap-4 p-4">
            <MasterDetail
                :has-selection="props.selected !== null"
                :selection-key="props.selected ? `${props.tab}-${props.selected.id}` : null"
                :empty-text="props.tab === 'leads' ? 'Select a lead to see what they asked for.' : 'Select a request to answer it.'"
                @close="closePane"
            >
                <template #list>
                    <!-- customer requests -->
                    <ListTable
                        v-if="props.tab === 'requests'"
                        :meta="requestRows"
                        :columns="4"
                        :row-key="(r) => r.id"
                        :selected-key="props.selected?.id ?? null"
                        @select="(r) => open(r.id)"
                    >
                        <template #head>
                            <th class="px-4 py-2 text-left font-medium">Request</th>
                            <th class="px-4 py-2 text-left font-medium">Customer</th>
                            <th class="hidden px-4 py-2 text-left font-medium md:table-cell">Received</th>
                            <th class="px-4 py-2 text-left font-medium">Status</th>
                        </template>
                        <template #row="{ item: r }">
                            <td class="px-4 py-2.5 font-medium">{{ r.title }}</td>
                            <td class="text-muted-foreground px-4 py-2.5">
                                {{ r.customer }}<span v-if="r.pool" class="hidden lg:inline"> · {{ r.pool }}</span>
                            </td>
                            <td class="text-muted-foreground hidden px-4 py-2.5 whitespace-nowrap md:table-cell">{{ formatDay(r.on) }}</td>
                            <td class="px-4 py-2.5">
                                <span class="inline-flex rounded-full px-2 py-0.5 text-xs font-medium" :class="statusClass(r.status)">{{
                                    r.status_label
                                }}</span>
                            </td>
                        </template>
                        <template #empty>
                            <Inbox class="mx-auto mb-2 size-6 opacity-50" />
                            {{
                                props.status === 'open'
                                    ? 'Nothing waiting — customers request services from their portal.'
                                    : 'No answered requests yet.'
                            }}
                        </template>
                    </ListTable>

                    <!-- website leads -->
                    <ListTable
                        v-else
                        :meta="leadRows"
                        :columns="4"
                        :row-key="(l) => l.id"
                        :selected-key="props.selected?.id ?? null"
                        @select="(l) => open(l.id)"
                    >
                        <template #head>
                            <th class="px-4 py-2 text-left font-medium">Name</th>
                            <th class="px-4 py-2 text-left font-medium">Asked for</th>
                            <th class="hidden px-4 py-2 text-left font-medium md:table-cell">Received</th>
                            <th class="px-4 py-2 text-left font-medium">Status</th>
                        </template>
                        <template #row="{ item: l }">
                            <td class="px-4 py-2.5">
                                <div class="font-medium">{{ l.name }}</div>
                                <div class="text-muted-foreground text-xs">{{ l.contact ?? '—' }}</div>
                            </td>
                            <td class="text-muted-foreground px-4 py-2.5">{{ l.service ?? sources[l.source] ?? l.source }}</td>
                            <td class="text-muted-foreground hidden px-4 py-2.5 whitespace-nowrap md:table-cell">{{ formatDay(l.on) }}</td>
                            <td class="px-4 py-2.5">
                                <span class="inline-flex rounded-full px-2 py-0.5 text-xs font-medium" :class="statusClass(l.status)">{{
                                    leadStatuses[l.status] ?? l.status
                                }}</span>
                            </td>
                        </template>
                        <template #empty>
                            <Inbox class="mx-auto mb-2 size-6 opacity-50" />
                            {{ props.status === 'open' ? 'No new leads — they arrive from your website.' : 'No converted or archived leads yet.' }}
                        </template>
                    </ListTable>
                </template>

                <template #detail>
                    <!-- ===== a customer request ===== -->
                    <div v-if="request" class="space-y-6">
                        <PaneHeader :title="request.title" :summary="`Received ${formatDay(request.on)}.`">
                            <template #media><IconTile :icon="requestIcon[request.type] ?? MessageSquare" size="lg" /></template>
                            <template #badge>
                                <Chip :tone="request.status === 'pending' ? 'warn' : request.status === 'approved' ? 'good' : 'muted'">{{
                                    request.status_label
                                }}</Chip>
                            </template>
                            <p class="mt-2 text-[15px]">
                                From
                                <Link :href="customerLink(request.customer_id)" class="text-primary font-medium underline-offset-4 hover:underline">{{
                                    request.customer
                                }}</Link
                                ><template v-if="request.pool_id"
                                    >, for
                                    <Link :href="poolLink(request.pool_id)" class="text-primary font-medium underline-offset-4 hover:underline">{{
                                        request.pool
                                    }}</Link></template
                                >
                            </p>
                        </PaneHeader>

                        <PaneFacts
                            :facts="[
                                {
                                    label: 'Would like to start',
                                    value:
                                        request.type === 'service'
                                            ? request.preferred_date
                                                ? formatDay(request.preferred_date)
                                                : 'As soon as possible'
                                            : null,
                                },
                                {
                                    label: 'Service',
                                    value:
                                        (request.type === 'hold' || request.type === 'cancel') && request.plan
                                            ? `${request.plan_service}, ${request.plan}`
                                            : null,
                                },
                                {
                                    label: 'Pause',
                                    value: request.type === 'hold' ? `${formatDay(request.preferred_date)} to ${formatDay(request.ends_on)}` : null,
                                },
                                {
                                    label: 'Last service',
                                    value:
                                        request.type === 'cancel'
                                            ? request.preferred_date
                                                ? formatDay(request.preferred_date)
                                                : 'As soon as possible'
                                            : null,
                                },
                            ]"
                        />

                        <PaneList v-if="request.service">
                            <PaneItem :title="request.service.name" :sub="request.service.description">
                                <p v-if="!request.service.is_active" class="mt-1 text-amber-700 dark:text-amber-400">
                                    This service is no longer active.
                                </p>
                                <template #trailing
                                    ><span class="text-primary text-xs font-semibold whitespace-nowrap">{{ request.service.offer }}</span></template
                                >
                            </PaneItem>
                        </PaneList>

                        <NoteBubble v-if="request.message" :author="`${firstWord(request.customer) ?? 'They'} wrote`">{{
                            request.message
                        }}</NoteBubble>

                        <PaneSection v-if="request.pool_id" title="Already on this pool">
                            <PaneList v-if="request.pool_plans.length">
                                <PaneItem
                                    v-for="p in request.pool_plans"
                                    :key="p.id"
                                    :title="p.service"
                                    :sub="`${p.schedule}${p.agent ? ` with ${p.agent}` : ''}`"
                                >
                                    <template v-if="p.status !== 'active'" #trailing>
                                        <Chip>{{ p.status === 'cancelled' ? 'Ended' : 'Paused' }}</Chip>
                                    </template>
                                </PaneItem>
                            </PaneList>
                            <p v-else class="text-muted-foreground text-sm">No plans yet.</p>
                        </PaneSection>

                        <!-- answered -->
                        <PaneSection v-if="request.status !== 'pending'" title="Your answer">
                            <p class="text-sm">
                                {{ request.status_label }}<template v-if="request.answered_on"> on {{ formatDay(request.answered_on) }}</template
                                ><template v-if="request.plan && request.type === 'service'">: {{ request.plan }}</template
                                >.
                            </p>
                            <NoteBubble
                                v-if="request.reply"
                                :author="request.status === 'declined' ? 'The reason you gave' : 'Your note'"
                                class="mt-2"
                                >{{ request.reply }}</NoteBubble
                            >
                        </PaneSection>

                        <!-- answer it -->
                        <template v-else>
                            <p
                                v-if="duplicatePlan"
                                class="rounded-2xl border border-amber-500/30 bg-amber-500/10 p-4 text-sm text-amber-700 dark:text-amber-400"
                            >
                                {{ request.pool }} already has {{ request.title }} ({{ duplicatePlan.schedule }}). Approving adds a second plan —
                                decline instead if this is a mix-up.
                            </p>
                            <div v-if="mode === 'none'" class="flex flex-wrap gap-2">
                                <Button size="sm" @click="openApprove"><CalendarCheck class="mr-1 size-3.5" /> {{ approveLabel }}</Button>
                                <Button size="sm" variant="outline" @click="openDecline"><X class="mr-1 size-3.5" /> Decline</Button>
                            </div>

                            <!-- approve: a service request gets its plan -->
                            <form
                                v-else-if="mode === 'approve'"
                                class="border-border space-y-4 rounded-2xl border p-4 text-sm"
                                @submit.prevent="submitApprove"
                            >
                                <template v-if="request.for_service">
                                    <div>
                                        <h3 class="font-display text-lg font-semibold tracking-tight">Schedule it</h3>
                                        <p class="text-muted-foreground">
                                            Creates the plan on {{ request.pool }}. Its visits go on the route as soon as you save.
                                        </p>
                                    </div>
                                    <PlanScheduleFields v-model="approveForm" :agents="props.agents" id-prefix="ap" />
                                </template>
                                <p v-else class="text-muted-foreground">{{ approveExplainer }}</p>

                                <div class="grid gap-1.5">
                                    <Label for="ap_reply"
                                        >Note to the customer <span class="text-muted-foreground font-normal">(optional)</span></Label
                                    >
                                    <textarea
                                        id="ap_reply"
                                        v-model="approveForm.reply"
                                        rows="2"
                                        class="border-input bg-background rounded-md border px-3 py-2 text-sm"
                                    ></textarea>
                                </div>
                                <p class="text-muted-foreground text-xs">The customer is told in their portal and by email.</p>
                                <div class="flex justify-end gap-2">
                                    <Button type="button" variant="outline" size="sm" @click="mode = 'none'">Cancel</Button>
                                    <Button type="submit" size="sm" :disabled="approveForm.processing"
                                        ><Check class="mr-1 size-3.5" /> {{ request.for_service ? 'Schedule it' : approveLabel }}</Button
                                    >
                                </div>
                            </form>

                            <!-- decline -->
                            <form
                                v-else-if="mode === 'decline'"
                                class="border-border space-y-3 rounded-2xl border p-4 text-sm"
                                @submit.prevent="submitDecline"
                            >
                                <div class="grid gap-1.5">
                                    <Label for="dc_reply">Why? <span class="text-muted-foreground font-normal">The customer sees this.</span></Label>
                                    <textarea
                                        id="dc_reply"
                                        v-model="declineForm.reply"
                                        rows="3"
                                        class="border-input bg-background rounded-md border px-3 py-2 text-sm"
                                        placeholder="e.g. We're fully booked on Tuesdays — reply and we'll find another day."
                                    ></textarea>
                                    <p v-if="declineForm.errors.reply" class="text-xs text-red-600">{{ declineForm.errors.reply }}</p>
                                </div>
                                <div class="flex justify-end gap-2">
                                    <Button type="button" variant="outline" size="sm" @click="mode = 'none'">Cancel</Button>
                                    <Button type="submit" size="sm" variant="destructive" :disabled="declineForm.processing">Decline request</Button>
                                </div>
                            </form>
                        </template>
                    </div>

                    <!-- ===== a website lead ===== -->
                    <div v-else-if="lead" class="space-y-6">
                        <PaneHeader :title="lead.name" :summary="`${sources[lead.source] ?? lead.source}, ${formatDay(lead.on)}.`">
                            <template #media><IconTile :icon="UserPlus" size="lg" /></template>
                            <template #badge>
                                <Chip
                                    :tone="
                                        lead.status === 'new'
                                            ? 'warn'
                                            : lead.status === 'converted'
                                              ? 'good'
                                              : lead.status === 'contacted'
                                                ? 'info'
                                                : 'muted'
                                    "
                                    >{{ leadStatuses[lead.status] ?? lead.status }}</Chip
                                >
                            </template>
                            <p v-if="lead.customer" class="mt-2 text-[15px]">
                                Now a customer:
                                <Link :href="customerLink(lead.customer.id)" class="text-primary font-medium underline-offset-4 hover:underline">{{
                                    lead.customer.name
                                }}</Link>
                            </p>
                        </PaneHeader>

                        <PaneFacts
                            :facts="[
                                { label: 'Email', value: lead.email, href: lead.email ? `mailto:${lead.email}` : null },
                                { label: 'Phone', value: lead.phone, href: lead.phone ? telLink(lead.phone) : null },
                                ...lead.details.map((d) => ({
                                    label: d.label,
                                    value: d.label === 'Preferred date' ? formatDay(d.value) : cap(d.value),
                                })),
                            ]"
                        />

                        <PaneSection v-if="lead.service" title="Picked on your website">
                            <PaneList>
                                <PaneItem :title="lead.service.name">
                                    <template #trailing
                                        ><span class="text-primary text-xs font-semibold whitespace-nowrap">{{ lead.service.offer }}</span></template
                                    >
                                </PaneItem>
                            </PaneList>
                        </PaneSection>

                        <NoteBubble v-if="lead.message" :author="`${firstWord(lead.name) ?? 'They'} wrote`">{{ lead.message }}</NoteBubble>

                        <template v-if="!lead.customer">
                            <div v-if="mode !== 'convert'" class="flex flex-wrap items-center gap-2">
                                <Button size="sm" @click="openConvert"><UserPlus class="mr-1 size-3.5" /> Convert to customer</Button>
                                <select
                                    :value="lead.status"
                                    class="border-input bg-background h-8 w-36 rounded-md border px-2 text-sm"
                                    aria-label="Lead status"
                                    @change="setLeadStatus(($event.target as HTMLSelectElement).value)"
                                >
                                    <option value="new">New</option>
                                    <option value="contacted">Contacted</option>
                                    <option value="archived">Archived</option>
                                </select>
                            </div>

                            <!-- convert: the add-customer form, pre-filled -->
                            <form v-else class="border-border space-y-4 rounded-2xl border p-4 text-sm" @submit.prevent="submitConvert">
                                <div>
                                    <h3 class="font-display text-lg font-semibold tracking-tight">New customer</h3>
                                    <p class="text-muted-foreground">
                                        Adds them and their pool.<template v-if="lead.service">
                                            Their pick — {{ lead.service.name }} — then waits in Customer requests for you to approve &amp;
                                            schedule.</template
                                        >
                                    </p>
                                </div>
                                <div class="flex flex-wrap gap-3">
                                    <div class="grid gap-1.5">
                                        <Label for="cv_first">First name</Label>
                                        <Input id="cv_first" v-model="convertForm.first_name" class="w-44" />
                                        <p v-if="convertForm.errors.first_name" class="text-xs text-red-600">{{ convertForm.errors.first_name }}</p>
                                    </div>
                                    <div class="grid gap-1.5">
                                        <Label for="cv_last">Last name</Label>
                                        <Input id="cv_last" v-model="convertForm.last_name" class="w-44" />
                                    </div>
                                </div>
                                <div class="flex flex-wrap gap-3">
                                    <div class="grid gap-1.5">
                                        <Label for="cv_email">Email</Label>
                                        <Input id="cv_email" v-model="convertForm.email" type="email" class="w-60" />
                                        <p v-if="convertForm.errors.email" class="text-xs text-red-600">{{ convertForm.errors.email }}</p>
                                    </div>
                                    <div class="grid gap-1.5">
                                        <Label for="cv_phone">Phone</Label>
                                        <Input id="cv_phone" v-model="convertForm.phone" class="w-40" />
                                    </div>
                                </div>
                                <div class="grid gap-1.5">
                                    <Label for="cv_addr">Street address</Label>
                                    <Input id="cv_addr" v-model="convertForm.address_line1" />
                                </div>
                                <div class="flex flex-wrap gap-3">
                                    <div class="grid gap-1.5">
                                        <Label for="cv_city">City</Label>
                                        <Input id="cv_city" v-model="convertForm.city" class="w-40" />
                                    </div>
                                    <div class="grid gap-1.5">
                                        <Label for="cv_state">State</Label>
                                        <Input id="cv_state" v-model="convertForm.state" maxlength="2" class="w-16 uppercase" />
                                        <p v-if="convertForm.errors.state" class="text-xs text-red-600">{{ convertForm.errors.state }}</p>
                                    </div>
                                    <div class="grid gap-1.5">
                                        <Label for="cv_zip">ZIP</Label>
                                        <Input id="cv_zip" v-model="convertForm.zip" class="w-24" />
                                    </div>
                                </div>
                                <div class="flex flex-wrap gap-3">
                                    <div class="grid gap-1.5">
                                        <Label for="cv_pool">Pool name</Label>
                                        <Input id="cv_pool" v-model="convertForm.pool_name" class="w-44" />
                                        <p v-if="convertForm.errors.pool_name" class="text-xs text-red-600">{{ convertForm.errors.pool_name }}</p>
                                    </div>
                                    <div class="grid gap-1.5">
                                        <Label for="cv_type">Type</Label>
                                        <select
                                            id="cv_type"
                                            v-model="convertForm.pool_type"
                                            class="border-input bg-background h-9 w-36 rounded-md border px-2 text-sm"
                                        >
                                            <option v-for="(label, key) in POOL_TYPES" :key="key" :value="key">{{ label }}</option>
                                        </select>
                                    </div>
                                    <div class="grid gap-1.5">
                                        <Label for="cv_gal">Gallons</Label>
                                        <Input id="cv_gal" v-model="convertForm.pool_volume" type="number" min="0" class="w-28" />
                                    </div>
                                </div>
                                <div class="flex justify-end gap-2">
                                    <Button type="button" variant="outline" size="sm" @click="mode = 'none'">Cancel</Button>
                                    <Button type="submit" size="sm" :disabled="convertForm.processing">Add customer</Button>
                                </div>
                            </form>
                        </template>
                    </div>
                </template>
            </MasterDetail>
        </div>
    </AppLayout>
</template>
