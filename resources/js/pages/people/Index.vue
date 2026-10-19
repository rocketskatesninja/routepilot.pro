<script setup lang="ts">
import EmailChoices from '@/components/EmailChoices.vue';
import EntityAvatar from '@/components/EntityAvatar.vue';
import ImageUpload from '@/components/ImageUpload.vue';
import ListTable from '@/components/ListTable.vue';
import MasterDetail from '@/components/MasterDetail.vue';
import PasswordInput from '@/components/PasswordInput.vue';
import SortableTh from '@/components/SortableTh.vue';
import Chip from '@/components/pane/Chip.vue';
import IconTile from '@/components/pane/IconTile.vue';
import PaneFacts from '@/components/pane/PaneFacts.vue';
import PaneHeader from '@/components/pane/PaneHeader.vue';
import PaneItem from '@/components/pane/PaneItem.vue';
import PaneList from '@/components/pane/PaneList.vue';
import PaneSection from '@/components/pane/PaneSection.vue';
import { Button } from '@/components/ui/button';
import { DropdownMenu, DropdownMenuContent, DropdownMenuItem, DropdownMenuTrigger } from '@/components/ui/dropdown-menu';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { useListSearch } from '@/composables/useListSearch';
import AppLayout from '@/layouts/AppLayout.vue';
import { POOL_TYPES } from '@/lib/labels';
import { composeLink, poolLink, reportLink, telLink } from '@/lib/links';
import { visitQuery } from '@/lib/listQuery';
import { andList, monthYear } from '@/lib/portal';
import { formatDay, formatMoney } from '@/lib/utils';
import { type BreadcrumbItem } from '@/types';
import { type Paginated } from '@/types/pagination';
import { Head, Link, router, useForm, usePage } from '@inertiajs/vue3';
import { Download, FileSpreadsheet, FileText, HardHat, Mail, Pencil, RotateCcw, Send, Trash2, UserPlus, Users, UserX, Waves } from 'lucide-vue-next';
import { computed, ref, watch } from 'vue';

type PersonType = 'customer' | 'agent';

interface PersonRow {
    id: number;
    person_type: PersonType;
    first_name: string;
    last_name: string | null;
    email: string | null;
    phone: string | null;
    balance: number | null;
    last_visit: string | null;
    portal_status: 'none' | 'active' | 'revoked' | null;
    photo_url: string | null;
}

interface CustomerFields {
    first_name: string;
    last_name: string | null;
    email: string | null;
    phone: string | null;
    address_line1: string | null;
    city: string | null;
    state: string | null;
    zip: string | null;
    notes: string | null;
    bill_chemicals: boolean;
}

interface CustomerDetail {
    type: 'customer';
    id: number;
    name: string;
    photo_url: string | null;
    email: string | null;
    phone: string | null;
    city: string | null;
    address: string | null;
    since: string | null;
    balance: number;
    next_visit: { date: string; agent: string | null; pool: string | null } | null;
    portal_status: 'none' | 'active' | 'revoked';
    emails: Record<string, boolean>;
    archived: boolean;
    archived_on: string | null;
    anonymized_on: string | null;
    summary: { pools: number; visits: number; invoices: number; charges: number } | null;
    pools: { id: number; name: string; type: string; volume: number | null; services: string[] }[];
    recent_visits: { id: number; pool: string | null; service: string | null; agent: string | null; completed_on: string | null }[];
    fields: CustomerFields;
}

interface AgentFields {
    first_name: string;
    last_name: string | null;
    email: string | null;
    phone: string | null;
    map_color: string | null;
    is_active: boolean;
    agent_plus: boolean;
}

interface AgentDetail {
    type: 'agent';
    id: number;
    name: string;
    photo_url: string | null;
    email: string | null;
    phone: string | null;
    is_active: boolean;
    agent_plus: boolean;
    stats: { completed_visits: number; this_week: number };
    fields: AgentFields;
}

const props = defineProps<{
    people: Paginated<PersonRow>;
    counts: { all: number; customers: number; agents: number; archived: number };
    selected: CustomerDetail | AgentDetail | null;
    filters: { search: string; type: 'all' | 'customers' | 'agents' | 'archived' };
    sort: { key: string; dir: string };
    canManage: boolean;
    canEmail: boolean;
    audiences: { key: string; label: string; count: number }[];
    recent: { id: number; subject: string; audience: string; recipients: number; sent: number; failed: number; sent_on: string | null }[];
}>();

const breadcrumbs: BreadcrumbItem[] = [{ title: 'People', href: '/people' }];

// --- selection + email ---
const picked = ref<string[]>([]);
const keyFor = (p: PersonRow) => `${p.person_type}:${p.id}`;
const money = formatMoney;
const isSelected = (p: PersonRow) => picked.value.includes(keyFor(p));
function toggleSelect(p: PersonRow) {
    const k = keyFor(p);
    const i = picked.value.indexOf(k);
    if (i === -1) picked.value.push(k);
    else picked.value.splice(i, 1);
}
const pageKeys = computed(() => props.people.data.map(keyFor));
const allOnPage = computed(() => pageKeys.value.length > 0 && pageKeys.value.every((k) => picked.value.includes(k)));
function toggleAll() {
    if (allOnPage.value) picked.value = picked.value.filter((k) => !pageKeys.value.includes(k));
    else picked.value = [...new Set([...picked.value, ...pageKeys.value])];
}

const emailOpen = ref(false);
const emailForm = useForm<{ audience: string; subject: string; body: string; recipients: string[] }>({
    audience: 'customers',
    subject: '',
    body: '',
    recipients: [],
});
const audienceOptions = computed(() => [
    ...(picked.value.length ? [{ key: 'selected', label: 'Selected people', count: picked.value.length }] : []),
    ...props.audiences,
]);
const emailCount = computed(() => audienceOptions.value.find((a) => a.key === emailForm.audience)?.count ?? 0);
function openEmail() {
    emailForm.audience = picked.value.length ? 'selected' : (props.audiences[0]?.key ?? 'customers');
    emailForm.clearErrors();
    emailOpen.value = true;
}
/** Put the email form away; the checkboxes, and anyone ticked, go with it (a typed draft stays). */
function closeEmail() {
    emailOpen.value = false;
    picked.value = [];
    emailForm.clearErrors();
}
const toggleEmail = () => (emailOpen.value ? closeEmail() : openEmail());

// Export: the list exactly as shown — same tab, search and sort, every page.
function exportUrl(format: 'csv' | 'pdf') {
    const q = new URLSearchParams({ format, sort: props.sort.key, dir: props.sort.dir });
    if (props.filters.type !== 'all') q.set('type', props.filters.type);
    if (props.filters.search) q.set('search', props.filters.search);
    return `/people/export?${q}`;
}
function submitEmail() {
    emailForm.recipients = emailForm.audience === 'selected' ? [...picked.value] : [];
    emailForm.post('/people/email', {
        preserveScroll: true,
        onSuccess: () => {
            emailOpen.value = false;
            emailForm.reset();
            picked.value = [];
        },
    });
}

// A deep link (?compose=customer:5) opens the email panel pre-targeted at one
// person — used by the "email" links in other detail panes. Fires on fresh
// mounts and same-page navigations alike (page.url is reactive).
const page = usePage();
watch(
    () => page.url,
    (url) => {
        const compose = new URL(url, 'http://localhost').searchParams.get('compose');
        if (compose && /^(customer|agent):\d+$/.test(compose)) {
            picked.value = [compose];
            emailForm.audience = 'selected';
            emailForm.clearErrors();
            emailOpen.value = true;
        }
    },
    { immediate: true },
);

const tabs = [
    { key: 'all', label: 'All' },
    { key: 'customers', label: 'Customers' },
    { key: 'agents', label: 'Agents' },
    { key: 'archived', label: 'Archived' },
] as const;

const { search } = useListSearch(props.filters.search, { extra: () => ({ type: tab() }) });

const tab = () => (props.filters.type === 'all' ? undefined : props.filters.type);

// Switching tabs keeps the search and sort; selection and page reset.
function setType(type: 'all' | 'customers' | 'agents' | 'archived') {
    visitQuery(
        {
            search: search.value || undefined,
            type: type === 'all' ? undefined : type,
            selected: undefined,
            selected_type: undefined,
            page: undefined,
        },
        { replace: true },
    );
}

function openPerson(person: PersonRow) {
    visitQuery({ search: search.value || undefined, type: tab(), selected: person.id, selected_type: person.person_type });
}

function closeDrawer() {
    visitQuery({ search: search.value || undefined, type: tab(), selected: undefined, selected_type: undefined });
}

const fullName = (p: PersonRow) => `${p.first_name} ${p.last_name ?? ''}`.trim();
const selectedKey = computed(() => (props.selected ? `${props.selected.type}-${props.selected.id}` : null));
const customer = computed(() => (props.selected?.type === 'customer' ? props.selected : null));

// Which optional emails the customer gets — the office can change them (say they asked on the phone).
const EMAIL_TYPES: Record<string, [string, string]> = {
    visit_reports: ['Visit reports', 'After each visit: what was done, the water test and photos.'],
    reminders: ['Visit reminders', 'The day before someone comes.'],
    news: ['News and offers', 'Emails you send from Email people.'],
};
const emailBusy = ref(false);
const setEmail = (type: string, on: boolean) => {
    if (!customer.value) return;
    router.patch(
        `/customers/${customer.value.id}/emails`,
        { [type]: on },
        {
            preserveScroll: true,
            onStart: () => (emailBusy.value = true),
            onFinish: () => (emailBusy.value = false),
        },
    );
};
const agent = computed(() => (props.selected?.type === 'agent' ? props.selected : null));

/** "In-ground, 18,000 gal. Weekly Pool Service and Chemistry Check." */
const poolLine = (pool: CustomerDetail['pools'][number]) => {
    const size = [POOL_TYPES[pool.type] ?? pool.type, pool.volume ? `${pool.volume.toLocaleString('en-US')} gal` : ''].filter(Boolean).join(', ');
    return `${size}. ${pool.services.length ? `${andList(pool.services)}.` : 'No service yet.'}`;
};
/** "Weekly Pool Service at Beach House Pool with Ashley." */
const visitLine = (v: CustomerDetail['recent_visits'][number]) =>
    `${v.service ?? 'Service visit'}${v.pool ? ` at ${v.pool}` : ''}${v.agent ? ` with ${v.agent}` : ''}.`;
const agentSummary = (a: AgentDetail) =>
    `Agent. ${a.stats.completed_visits} visit${a.stats.completed_visits === 1 ? '' : 's'} done, ${a.stats.this_week} this week.`;

// The detail pane is shared: it hosts whichever form is open (customer, agent,
// or broadcast email), otherwise the selected item's detail. Only one form is
// ever open at a time. Closing the pane cancels an open form first.
const anyFormOpen = computed(() => formOpen.value || agentFormOpen.value || emailOpen.value);
const formKey = computed(() => {
    if (formOpen.value) return `customer-${formMode.value}`;
    if (agentFormOpen.value) return `agent-${agentFormMode.value}`;
    if (emailOpen.value) return 'email';
    return null;
});
function closePane() {
    if (anyFormOpen.value) {
        formOpen.value = false;
        agentFormOpen.value = false;
        if (emailOpen.value) closeEmail();
    } else {
        closeDrawer();
    }
}

// --- Customer create / edit form ---
const formOpen = ref(false);
const formMode = ref<'create' | 'edit'>('create');
const formId = ref<number | null>(null);

const form = useForm({
    first_name: '',
    last_name: '',
    email: '',
    phone: '',
    address_line1: '',
    city: '',
    state: '',
    zip: '',
    notes: '',
    bill_chemicals: false,
    photo: null as File | null,
    pool_name: '',
    pool_type: 'inground',
    pool_volume: '',
    pool_sanitizer: 'chlorine',
});

function openCreate() {
    form.reset();
    form.clearErrors();
    formMode.value = 'create';
    formId.value = null;
    formOpen.value = true;
}

function openEdit() {
    if (!props.selected || props.selected.type !== 'customer') return;
    const f = props.selected.fields;
    form.first_name = f.first_name;
    form.last_name = f.last_name ?? '';
    form.email = f.email ?? '';
    form.phone = f.phone ?? '';
    form.address_line1 = f.address_line1 ?? '';
    form.city = f.city ?? '';
    form.state = f.state ?? '';
    form.zip = f.zip ?? '';
    form.notes = f.notes ?? '';
    form.bill_chemicals = f.bill_chemicals;
    form.photo = null;
    form.clearErrors();
    formMode.value = 'edit';
    formId.value = props.selected.id;
    formOpen.value = true;
}

function submitForm() {
    if (formMode.value === 'create') {
        form.post('/customers', {
            preserveScroll: true,
            onSuccess: () => {
                formOpen.value = false;
                form.reset();
            },
        });
    } else if (formId.value !== null) {
        form.patch(`/customers/${formId.value}`, {
            preserveScroll: true,
            onSuccess: () => {
                formOpen.value = false;
            },
        });
    }
}

function destroyCustomer() {
    if (!props.selected || props.selected.type !== 'customer') return;
    if (
        !confirm(
            'Archive this customer? Their pools and subscriptions are archived too (no more scheduling or metering). You can restore them from the Archived tab.',
        )
    )
        return;
    router.delete(`/customers/${props.selected.id}`, { preserveScroll: true, onSuccess: () => closeDrawer() });
}
function restoreCustomer() {
    if (!props.selected || props.selected.type !== 'customer') return;
    router.post(`/customers/${props.selected.id}/restore`, {}, { preserveScroll: true, onSuccess: () => closeDrawer() });
}
function anonymizeCustomer() {
    if (!props.selected || props.selected.type !== 'customer') return;
    const ok = confirm(
        'Remove this customer’s personal details? Their name, contact details, addresses, notes, photos, portal login and AI conversations are erased. ' +
            'Invoices, payments and visit records stay, under “Former customer”. This cannot be undone.',
    );
    if (ok) router.post(`/customers/${props.selected.id}/anonymize`, {}, { preserveScroll: true });
}
function purgeCustomer() {
    if (!props.selected || props.selected.type !== 'customer') return;
    const s = props.selected.summary;
    const detail = s ? ` This removes ${s.pools} pool(s), ${s.visits} visit(s), ${s.invoices} invoice(s) and ${s.charges} charge(s).` : '';
    if (!confirm(`Permanently delete this customer?${detail} This cannot be undone.`)) return;
    router.delete(`/customers/${props.selected.id}/force`, { preserveScroll: true, onSuccess: () => closeDrawer() });
}

function grantPortal() {
    if (!props.selected || props.selected.type !== 'customer') return;
    const pw = window.prompt('Set an initial portal password for this customer (min 8 characters):');
    if (!pw || pw.length < 8) return;
    router.post(`/customers/${props.selected.id}/portal`, { password: pw }, { preserveScroll: true });
}
function restorePortal() {
    if (!props.selected || props.selected.type !== 'customer') return;
    router.post(`/customers/${props.selected.id}/portal/restore`, {}, { preserveScroll: true });
}
function exportCustomer() {
    if (!props.selected || props.selected.type !== 'customer') return;
    window.open(`/customers/${props.selected.id}/export`, '_blank');
}

// --- agent create / edit ---
const agentFormOpen = ref(false);
const agentFormMode = ref<'create' | 'edit'>('create');
const agentFormId = ref<number | null>(null);

const agentForm = useForm({
    first_name: '',
    last_name: '',
    email: '',
    phone: '',
    password: '',
    map_color: '#0ea5e9',
    is_active: true,
    agent_plus: false,
    photo: null as File | null,
});

function openAgentCreate() {
    agentForm.reset();
    agentForm.clearErrors();
    agentFormMode.value = 'create';
    agentFormId.value = null;
    agentFormOpen.value = true;
}

function openAgentEdit() {
    if (!props.selected || props.selected.type !== 'agent') return;
    const f = props.selected.fields;
    agentForm.first_name = f.first_name;
    agentForm.last_name = f.last_name ?? '';
    agentForm.email = f.email ?? '';
    agentForm.phone = f.phone ?? '';
    agentForm.password = '';
    agentForm.map_color = f.map_color ?? '#0ea5e9';
    agentForm.is_active = f.is_active;
    agentForm.agent_plus = f.agent_plus;
    agentForm.photo = null;
    agentForm.clearErrors();
    agentFormMode.value = 'edit';
    agentFormId.value = props.selected.id;
    agentFormOpen.value = true;
}

function submitAgent() {
    if (agentFormMode.value === 'create') {
        agentForm.post('/agents', {
            preserveScroll: true,
            onSuccess: () => {
                agentFormOpen.value = false;
                agentForm.reset();
            },
        });
    } else if (agentFormId.value !== null) {
        agentForm.patch(`/agents/${agentFormId.value}`, {
            preserveScroll: true,
            onSuccess: () => {
                agentFormOpen.value = false;
            },
        });
    }
}

function destroyAgent() {
    if (!props.selected || props.selected.type !== 'agent') return;
    if (!confirm('Remove this agent? Their visit history is preserved.')) return;
    router.delete(`/agents/${props.selected.id}`, { preserveScroll: true, onSuccess: () => closeDrawer() });
}
</script>

<template>
    <Head title="People" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <template #filters>
            <button
                v-for="t in tabs"
                :key="t.key"
                class="shrink-0 rounded-md px-2.5 py-1 text-sm font-medium whitespace-nowrap transition-colors"
                :class="props.filters.type === t.key ? 'bg-primary text-primary-foreground' : 'text-muted-foreground hover:bg-muted'"
                @click="setType(t.key)"
            >
                {{ t.label }} <span class="opacity-70">{{ props.counts[t.key] }}</span>
            </button>
        </template>

        <template #actions>
            <Input v-model="search" type="search" placeholder="Search people…" class="h-9 min-w-0 flex-1 sm:w-40 sm:flex-none lg:w-52" />
            <Button v-if="props.canManage" size="sm" title="New customer" @click="openCreate">
                <UserPlus class="size-4 sm:mr-1" /><span class="hidden sm:inline">Customer</span>
            </Button>
            <Button v-if="props.canManage" size="sm" variant="outline" title="New agent" @click="openAgentCreate">
                <HardHat class="size-4 sm:mr-1" /><span class="hidden sm:inline">Agent</span>
            </Button>
            <Button
                v-if="props.canEmail"
                size="sm"
                :variant="emailOpen ? 'default' : 'outline'"
                :aria-pressed="emailOpen"
                :title="emailOpen ? 'Close the email form' : 'Email people'"
                @click="toggleEmail"
            >
                <Mail class="size-4 sm:mr-1" /><span class="hidden sm:inline">Email</span
                ><span v-if="picked.length" class="ml-1">({{ picked.length }})</span>
            </Button>
            <DropdownMenu v-if="props.canManage">
                <DropdownMenuTrigger as-child>
                    <Button size="sm" variant="outline" title="Export this list">
                        <Download class="size-4 sm:mr-1" /><span class="hidden sm:inline">Export</span>
                    </Button>
                </DropdownMenuTrigger>
                <DropdownMenuContent align="end">
                    <DropdownMenuItem as-child>
                        <a :href="exportUrl('csv')"><FileSpreadsheet class="mr-2 size-4" /> Spreadsheet (CSV)</a>
                    </DropdownMenuItem>
                    <DropdownMenuItem as-child>
                        <a :href="exportUrl('pdf')"><FileText class="mr-2 size-4" /> PDF</a>
                    </DropdownMenuItem>
                </DropdownMenuContent>
            </DropdownMenu>
        </template>

        <div class="flex h-full flex-1 flex-col gap-4 p-4">
            <MasterDetail
                :has-selection="anyFormOpen || props.selected !== null"
                :selection-key="anyFormOpen ? formKey : selectedKey"
                empty-text="Select a person to see details."
                @close="closePane"
            >
                <template #list>
                    <ListTable
                        :meta="props.people"
                        :columns="emailOpen ? 8 : 7"
                        :row-key="(p) => `${p.person_type}-${p.id}`"
                        :selected-key="selectedKey"
                        @select="openPerson"
                    >
                        <template #head>
                            <th v-if="emailOpen" class="w-10 px-4 py-2">
                                <input type="checkbox" :checked="allOnPage" aria-label="Select all" @change="toggleAll" />
                            </th>
                            <SortableTh sort-key="name" :active="props.sort">Name</SortableTh>
                            <SortableTh sort-key="type" :active="props.sort">Type</SortableTh>
                            <SortableTh sort-key="email" :active="props.sort" class="hidden md:table-cell">Email</SortableTh>
                            <SortableTh sort-key="phone" :active="props.sort" class="hidden min-[1360px]:table-cell lg:table-cell xl:hidden"
                                >Phone</SortableTh
                            >
                            <th class="hidden px-4 py-2 font-medium min-[1680px]:table-cell md:table-cell xl:hidden">Portal</th>
                            <th class="hidden px-4 py-2 font-medium lg:table-cell xl:hidden 2xl:table-cell">Last visit</th>
                            <th class="px-4 py-2 text-right font-medium">Balance</th>
                        </template>
                        <template #row="{ item: person }">
                            <td v-if="emailOpen" class="px-4 py-2.5" @click.stop>
                                <input
                                    type="checkbox"
                                    :checked="isSelected(person)"
                                    :aria-label="`Select ${fullName(person)}`"
                                    @change="toggleSelect(person)"
                                />
                            </td>
                            <td class="px-4 py-2.5">
                                <div class="flex items-center gap-2.5">
                                    <EntityAvatar :src="person.photo_url" type="person" :name="fullName(person)" size="sm" shape="circle" />
                                    <span class="font-medium">{{ fullName(person) }}</span>
                                </div>
                            </td>
                            <td class="px-4 py-2.5">
                                <span
                                    class="inline-flex rounded-full px-2 py-0.5 text-xs font-medium capitalize"
                                    :class="
                                        person.person_type === 'agent'
                                            ? 'bg-sky-500/15 text-sky-600 dark:text-sky-400'
                                            : 'bg-emerald-500/15 text-emerald-600 dark:text-emerald-400'
                                    "
                                >
                                    {{ person.person_type }}
                                </span>
                            </td>
                            <td class="text-muted-foreground hidden px-4 py-2.5 md:table-cell">{{ person.email ?? '—' }}</td>
                            <td class="text-muted-foreground hidden px-4 py-2.5 min-[1360px]:table-cell lg:table-cell xl:hidden">
                                {{ person.phone ?? '—' }}
                            </td>
                            <td class="hidden px-4 py-2.5 min-[1680px]:table-cell md:table-cell xl:hidden">
                                <template v-if="person.person_type === 'customer'">
                                    <span
                                        v-if="person.portal_status === 'active'"
                                        class="inline-flex rounded-full bg-emerald-500/15 px-2 py-0.5 text-xs font-medium text-emerald-600 dark:text-emerald-400"
                                        >Yes</span
                                    >
                                    <span
                                        v-else-if="person.portal_status === 'revoked'"
                                        class="inline-flex rounded-full bg-amber-500/15 px-2 py-0.5 text-xs font-medium text-amber-600 dark:text-amber-400"
                                        >Revoked</span
                                    >
                                    <span v-else class="text-muted-foreground text-xs">No</span>
                                </template>
                                <span v-else class="text-muted-foreground">—</span>
                            </td>
                            <td class="text-muted-foreground hidden px-4 py-2.5 lg:table-cell xl:hidden 2xl:table-cell">
                                {{ person.last_visit ?? '—' }}
                            </td>
                            <td
                                class="px-4 py-2.5 text-right font-medium"
                                :class="person.balance && person.balance > 0 ? 'text-amber-600 dark:text-amber-400' : 'text-muted-foreground'"
                            >
                                {{ person.balance != null ? money(person.balance) : '—' }}
                            </td>
                        </template>
                        <template #empty>
                            <Users class="mx-auto mb-2 size-6 opacity-50" />
                            No people yet.
                        </template>
                    </ListTable>
                </template>

                <template #detail>
                    <!-- create / edit customer form: hosted in the docked pane, not an overlay -->
                    <form v-if="formOpen" class="space-y-4 text-sm" @submit.prevent="submitForm">
                        <PaneHeader
                            :title="formMode === 'create' ? 'New customer' : 'Edit customer'"
                            :summary="formMode === 'create' ? 'Add a customer and, if you like, their first pool.' : 'Update their contact details.'"
                        />
                        <ImageUpload
                            :model-value="form.photo"
                            :current="formMode === 'edit' ? (props.selected?.photo_url ?? null) : null"
                            shape="circle"
                            @update:model-value="(f) => (form.photo = f)"
                        />
                        <div class="grid grid-cols-2 gap-3">
                            <div class="grid gap-1.5">
                                <Label for="first_name">First name</Label>
                                <Input id="first_name" v-model="form.first_name" />
                                <p v-if="form.errors.first_name" class="text-xs text-red-600">{{ form.errors.first_name }}</p>
                            </div>
                            <div class="grid gap-1.5">
                                <Label for="last_name">Last name</Label>
                                <Input id="last_name" v-model="form.last_name" />
                            </div>
                        </div>
                        <div class="grid grid-cols-2 gap-3">
                            <div class="grid gap-1.5">
                                <Label for="email">Email</Label>
                                <Input id="email" v-model="form.email" type="email" />
                                <p v-if="form.errors.email" class="text-xs text-red-600">{{ form.errors.email }}</p>
                            </div>
                            <div class="grid gap-1.5">
                                <Label for="phone">Phone</Label>
                                <Input id="phone" v-model="form.phone" />
                            </div>
                        </div>
                        <div class="grid gap-1.5">
                            <Label for="address">Address</Label>
                            <Input id="address" v-model="form.address_line1" />
                        </div>
                        <div class="grid grid-cols-3 gap-3">
                            <div class="col-span-1 grid gap-1.5">
                                <Label for="city">City</Label>
                                <Input id="city" v-model="form.city" />
                            </div>
                            <div class="grid gap-1.5">
                                <Label for="state">State</Label>
                                <Input id="state" v-model="form.state" maxlength="2" />
                            </div>
                            <div class="grid gap-1.5">
                                <Label for="zip">ZIP</Label>
                                <Input id="zip" v-model="form.zip" />
                            </div>
                        </div>
                        <label class="flex items-center gap-2"
                            ><input v-model="form.bill_chemicals" type="checkbox" /> <span>Itemize chemicals on this customer's bill</span></label
                        >
                        <div class="grid gap-1.5">
                            <Label for="notes">Notes</Label>
                            <textarea
                                id="notes"
                                v-model="form.notes"
                                rows="2"
                                class="border-input bg-background rounded-md border px-3 py-2 text-sm"
                            ></textarea>
                        </div>

                        <fieldset v-if="formMode === 'create'" class="border-border space-y-3 rounded-lg border p-3">
                            <legend class="text-muted-foreground px-1 text-xs font-medium">First pool (optional)</legend>
                            <div class="grid gap-1.5">
                                <Label for="pool_name">Pool name</Label>
                                <Input id="pool_name" v-model="form.pool_name" placeholder="e.g. Backyard Pool" />
                            </div>
                            <div class="grid grid-cols-2 gap-3">
                                <div class="grid gap-1.5">
                                    <Label for="pool_type">Type</Label>
                                    <select
                                        id="pool_type"
                                        v-model="form.pool_type"
                                        class="border-input bg-background h-9 rounded-md border px-2 text-sm"
                                    >
                                        <option value="inground">In-ground</option>
                                        <option value="above_ground">Above ground</option>
                                        <option value="spa">Spa</option>
                                        <option value="indoor">Indoor</option>
                                        <option value="infinity">Infinity</option>
                                        <option value="other">Other</option>
                                    </select>
                                </div>
                                <div class="grid gap-1.5">
                                    <Label for="pool_volume">Volume (gal)</Label>
                                    <Input id="pool_volume" v-model="form.pool_volume" type="number" min="0" />
                                </div>
                            </div>
                        </fieldset>

                        <div class="flex justify-end gap-2 pt-2">
                            <Button type="button" variant="outline" @click="formOpen = false">Cancel</Button>
                            <Button type="submit" :disabled="form.processing">{{ formMode === 'create' ? 'Add customer' : 'Save' }}</Button>
                        </div>
                    </form>

                    <!-- create / edit agent form: hosted in the docked pane -->
                    <form v-else-if="agentFormOpen" class="space-y-4 text-sm" @submit.prevent="submitAgent">
                        <PaneHeader
                            :title="agentFormMode === 'create' ? 'New agent' : 'Edit agent'"
                            :summary="agentFormMode === 'create' ? 'They sign in with this email and password.' : 'Update their profile and access.'"
                        />
                        <ImageUpload
                            :model-value="agentForm.photo"
                            :current="agentFormMode === 'edit' ? (props.selected?.photo_url ?? null) : null"
                            shape="circle"
                            @update:model-value="(f) => (agentForm.photo = f)"
                        />
                        <div class="grid grid-cols-2 gap-3">
                            <div class="grid gap-1.5">
                                <Label for="a_first">First name</Label>
                                <Input id="a_first" v-model="agentForm.first_name" />
                                <p v-if="agentForm.errors.first_name" class="text-xs text-red-600">{{ agentForm.errors.first_name }}</p>
                            </div>
                            <div class="grid gap-1.5"><Label for="a_last">Last name</Label><Input id="a_last" v-model="agentForm.last_name" /></div>
                        </div>
                        <div class="grid gap-1.5">
                            <Label for="a_email">Email</Label>
                            <Input id="a_email" v-model="agentForm.email" type="email" />
                            <p v-if="agentForm.errors.email" class="text-xs text-red-600">{{ agentForm.errors.email }}</p>
                        </div>
                        <div class="grid grid-cols-2 gap-3">
                            <div class="grid gap-1.5"><Label for="a_phone">Phone</Label><Input id="a_phone" v-model="agentForm.phone" /></div>
                            <div class="grid gap-1.5">
                                <Label for="a_color">Map color</Label
                                ><input id="a_color" v-model="agentForm.map_color" type="color" class="border-input h-9 w-full rounded-md border" />
                            </div>
                        </div>
                        <div class="grid gap-1.5">
                            <Label for="a_pw">{{ agentFormMode === 'create' ? 'Initial password' : 'New password (optional)' }}</Label>
                            <PasswordInput id="a_pw" v-model="agentForm.password" autocomplete="new-password" />
                            <p v-if="agentForm.errors.password" class="text-xs text-red-600">{{ agentForm.errors.password }}</p>
                        </div>
                        <label class="flex items-center gap-2"
                            ><input v-model="agentForm.agent_plus" type="checkbox" /> Agent+ (reorder and skip stops on their route, and see every
                            report)</label
                        >
                        <label v-if="agentFormMode === 'edit'" class="flex items-center gap-2"
                            ><input v-model="agentForm.is_active" type="checkbox" /> Active (can log in)</label
                        >
                        <div class="flex justify-end gap-2 pt-2">
                            <Button type="button" variant="outline" @click="agentFormOpen = false">Cancel</Button>
                            <Button type="submit" :disabled="agentForm.processing">{{ agentFormMode === 'create' ? 'Add agent' : 'Save' }}</Button>
                        </div>
                    </form>

                    <!-- broadcast email: hosted in the docked pane -->
                    <form v-else-if="emailOpen" class="space-y-4 text-sm" @submit.prevent="submitEmail">
                        <PaneHeader title="Email people" summary="Send to a whole group at once. Customers who opted out won’t get it." />
                        <div class="grid gap-1.5">
                            <Label for="aud">Audience</Label>
                            <select id="aud" v-model="emailForm.audience" class="border-input bg-background h-9 rounded-md border px-2 text-sm">
                                <option v-for="a in audienceOptions" :key="a.key" :value="a.key">{{ a.label }} ({{ a.count }})</option>
                            </select>
                            <p class="text-muted-foreground text-xs">{{ emailCount }} recipient(s).</p>
                        </div>
                        <div class="grid gap-1.5">
                            <Label for="esub">Subject</Label>
                            <Input id="esub" v-model="emailForm.subject" />
                            <p v-if="emailForm.errors.subject" class="text-xs text-red-600">{{ emailForm.errors.subject }}</p>
                        </div>
                        <div class="grid gap-1.5">
                            <Label for="ebody">Message</Label>
                            <textarea
                                id="ebody"
                                v-model="emailForm.body"
                                rows="7"
                                class="border-input bg-background rounded-md border px-3 py-2 text-sm"
                            ></textarea>
                            <p v-if="emailForm.errors.body" class="text-xs text-red-600">{{ emailForm.errors.body }}</p>
                        </div>
                        <div v-if="props.recent.length" class="border-border border-t pt-2">
                            <p class="text-muted-foreground mb-1 text-xs font-medium">Recent</p>
                            <ul class="text-muted-foreground space-y-0.5 text-xs">
                                <li v-for="c in props.recent.slice(0, 5)" :key="c.id" class="flex justify-between gap-2">
                                    <span class="truncate">{{ c.subject }}</span
                                    ><span class="shrink-0"
                                        >{{ c.sent }}/{{ c.recipients }} sent<span v-if="c.failed" class="text-red-500">
                                            · {{ c.failed }} failed</span
                                        >
                                        · {{ c.sent_on }}</span
                                    >
                                </li>
                            </ul>
                        </div>
                        <div class="flex justify-end gap-2 pt-2">
                            <Button type="button" variant="outline" @click="closeEmail">Cancel</Button>
                            <Button type="submit" :disabled="emailForm.processing || emailCount === 0"
                                ><Send class="mr-1 size-4" /> Send to {{ emailCount }}</Button
                            >
                        </div>
                    </form>

                    <!-- a customer -->
                    <div v-else-if="customer" class="space-y-6">
                        <PaneHeader :title="customer.name" :summary="customer.since ? `Customer since ${monthYear(customer.since)}.` : null">
                            <template #media>
                                <EntityAvatar :src="customer.photo_url" type="person" :name="customer.name" size="lg" shape="circle" />
                            </template>
                            <template #badge>
                                <Chip v-if="customer.archived" tone="warn">Archived</Chip>
                                <Chip v-else-if="customer.portal_status === 'active'" tone="good">Portal login</Chip>
                            </template>
                            <div class="mt-3 space-y-1 text-[15px]">
                                <p v-if="customer.next_visit">
                                    Next visit {{ formatDay(customer.next_visit.date)
                                    }}<template v-if="customer.next_visit.agent"> with {{ customer.next_visit.agent }}</template
                                    ><template v-if="customer.pools.length > 1"> at {{ customer.next_visit.pool }}</template
                                    >.
                                </p>
                                <p>
                                    <template v-if="customer.balance > 0">Owes {{ formatMoney(customer.balance) }}.</template>
                                    <template v-else-if="customer.balance < 0">{{ formatMoney(-customer.balance) }} in credit.</template>
                                    <template v-else>All paid up.</template>
                                    <Link
                                        :href="`/balances?selected=${customer.id}`"
                                        class="text-primary ml-1 font-medium underline-offset-4 hover:underline"
                                        >{{ customer.balance !== 0 ? 'See balance' : 'See payments' }}</Link
                                    >
                                </p>
                            </div>
                            <template v-if="props.canManage && !customer.archived" #actions>
                                <Button size="sm" variant="outline" @click="openEdit"><Pencil class="mr-1 size-3.5" /> Edit</Button>
                                <Button v-if="customer.portal_status === 'none'" size="sm" variant="outline" @click="grantPortal"
                                    >Grant portal</Button
                                >
                                <Button v-else-if="customer.portal_status === 'revoked'" size="sm" variant="outline" @click="restorePortal"
                                    ><RotateCcw class="mr-1 size-3.5" /> Restore access</Button
                                >
                                <Button size="sm" variant="outline" @click="exportCustomer">Export</Button>
                                <Button size="sm" variant="outline" class="text-red-600 hover:text-red-600 dark:text-red-400" @click="destroyCustomer"
                                    ><Trash2 class="mr-1 size-3.5" /> Archive</Button
                                >
                            </template>
                        </PaneHeader>

                        <!-- archived: restore or permanently delete -->
                        <div v-if="customer.archived && props.canManage" class="rounded-2xl border border-amber-500/30 bg-amber-500/10 p-4 text-sm">
                            <p class="font-medium">Archived{{ customer.archived_on ? ` on ${formatDay(customer.archived_on)}` : '' }}.</p>
                            <p v-if="customer.anonymized_on" class="text-muted-foreground mt-0.5">
                                Personal details removed on {{ formatDay(customer.anonymized_on) }}. Their billing records are kept.
                            </p>
                            <p v-if="customer.summary" class="text-muted-foreground mt-0.5">
                                Still holds {{ customer.summary.pools }} pool(s), {{ customer.summary.visits }} visit(s),
                                {{ customer.summary.invoices }} invoice(s) and {{ customer.summary.charges }} charge(s).
                            </p>
                            <div class="mt-3 flex flex-wrap gap-2">
                                <Button v-if="!customer.anonymized_on" size="sm" variant="outline" @click="restoreCustomer"
                                    ><RotateCcw class="mr-1 size-3.5" /> Restore</Button
                                >
                                <Button v-if="!customer.anonymized_on" size="sm" variant="outline" @click="anonymizeCustomer"
                                    ><UserX class="mr-1 size-3.5" /> Remove personal details</Button
                                >
                                <Button size="sm" variant="outline" class="text-red-600 hover:text-red-600 dark:text-red-400" @click="purgeCustomer"
                                    ><Trash2 class="mr-1 size-3.5" /> Delete permanently</Button
                                >
                            </div>
                        </div>

                        <PaneSection title="Contact">
                            <PaneFacts
                                :facts="[
                                    { label: 'Email', value: customer.email, href: composeLink(customer.id, 'customer') },
                                    { label: 'Phone', value: customer.phone, href: customer.phone ? telLink(customer.phone) : null },
                                    { label: 'Address', value: customer.address },
                                ]"
                            />
                            <p v-if="!customer.email && !customer.phone && !customer.address" class="text-muted-foreground text-sm">
                                No contact details yet.
                            </p>
                        </PaneSection>

                        <PaneSection title="Emails">
                            <EmailChoices
                                :types="EMAIL_TYPES"
                                :preferences="customer.emails"
                                :busy="!props.canManage || emailBusy"
                                who="they"
                                @change="(type, on) => setEmail(type, on)"
                            />
                        </PaneSection>

                        <PaneSection :title="customer.pools.length === 1 ? 'Pool' : 'Pools'">
                            <PaneList v-if="customer.pools.length">
                                <PaneItem
                                    v-for="pool in customer.pools"
                                    :key="pool.id"
                                    :title="pool.name"
                                    :sub="poolLine(pool)"
                                    :href="poolLink(pool.id)"
                                >
                                    <template #icon><IconTile :icon="Waves" size="sm" /></template>
                                </PaneItem>
                            </PaneList>
                            <p v-else class="text-muted-foreground text-sm">No pools yet.</p>
                        </PaneSection>

                        <PaneSection v-if="customer.recent_visits.length" title="Recent visits">
                            <PaneList>
                                <PaneItem
                                    v-for="visit in customer.recent_visits"
                                    :key="visit.id"
                                    :title="formatDay(visit.completed_on)"
                                    :sub="visitLine(visit)"
                                    :href="reportLink(visit.id)"
                                />
                            </PaneList>
                        </PaneSection>
                    </div>

                    <!-- an agent -->
                    <div v-else-if="agent" class="space-y-6">
                        <PaneHeader :title="agent.name" :summary="agentSummary(agent)">
                            <template #media>
                                <EntityAvatar :src="agent.photo_url" type="person" :name="agent.name" size="lg" shape="circle" />
                            </template>
                            <template #badge>
                                <Chip :tone="agent.is_active ? 'good' : 'muted'">{{ agent.is_active ? 'Active' : 'Inactive' }}</Chip>
                            </template>
                            <template v-if="props.canManage" #actions>
                                <Button size="sm" variant="outline" @click="openAgentEdit"><Pencil class="mr-1 size-3.5" /> Edit</Button>
                                <Button size="sm" variant="outline" class="text-red-600 hover:text-red-600 dark:text-red-400" @click="destroyAgent"
                                    ><Trash2 class="mr-1 size-3.5" /> Remove</Button
                                >
                            </template>
                        </PaneHeader>

                        <PaneSection title="Contact">
                            <PaneFacts
                                :facts="[
                                    { label: 'Email', value: agent.email, href: composeLink(agent.id, 'agent') },
                                    { label: 'Phone', value: agent.phone, href: agent.phone ? telLink(agent.phone) : null },
                                ]"
                            />
                        </PaneSection>

                        <PaneSection title="Access">
                            <p class="text-muted-foreground text-sm">
                                {{
                                    agent.agent_plus
                                        ? 'Agent+ is on: they can reorder and skip stops on their own route, and see every report.'
                                        : 'They work the route as it’s planned and see only their own reports. Turn on Agent+ to let them reorder and skip stops and see every report.'
                                }}
                            </p>
                        </PaneSection>
                    </div>
                </template>
            </MasterDetail>
        </div>
    </AppLayout>
</template>
