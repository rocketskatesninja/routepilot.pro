<script setup lang="ts">
import EntityAvatar from '@/components/EntityAvatar.vue';
import ImageUpload from '@/components/ImageUpload.vue';
import MasterDetail from '@/components/MasterDetail.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/AppLayout.vue';
import { formatMoney } from '@/lib/utils';
import { type BreadcrumbItem } from '@/types';
import { Head, router, useForm } from '@inertiajs/vue3';
import { Mail, Pencil, Plus, Send, Trash2, Users } from 'lucide-vue-next';
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
    has_portal: boolean;
    pools: { id: number; name: string; type: string }[];
    recent_visits: { id: number; pool: string | null; completed_on: string | null }[];
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

interface Paginated<T> {
    data: T[];
    total: number;
}

const props = defineProps<{
    people: Paginated<PersonRow>;
    counts: { all: number; customers: number; agents: number };
    selected: CustomerDetail | AgentDetail | null;
    filters: { search: string; type: 'all' | 'customers' | 'agents' };
    canManage: boolean;
    canEmail: boolean;
    audiences: { key: string; label: string; count: number }[];
    recent: { id: number; subject: string; audience: string; recipients: number; sent_on: string | null }[];
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

const tabs = [
    { key: 'all', label: 'All' },
    { key: 'customers', label: 'Customers' },
    { key: 'agents', label: 'Agents' },
] as const;

const search = ref(props.filters.search);
let timer: ReturnType<typeof setTimeout> | undefined;
watch(search, (value) => {
    clearTimeout(timer);
    timer = setTimeout(() => visit({ search: value || undefined, type: tab() }), 300);
});

const tab = () => (props.filters.type === 'all' ? undefined : props.filters.type);

function visit(params: Record<string, string | number | undefined>) {
    router.get('/people', params, { preserveState: true, replace: true, preserveScroll: true });
}

function setType(type: 'all' | 'customers' | 'agents') {
    visit({ search: search.value || undefined, type: type === 'all' ? undefined : type });
}

function openPerson(person: PersonRow) {
    router.get(
        '/people',
        { search: search.value || undefined, type: tab(), selected: person.id, selected_type: person.person_type },
        { preserveState: true, preserveScroll: true },
    );
}

function closeDrawer() {
    visit({ search: search.value || undefined, type: tab() });
}

const fullName = (p: PersonRow) => `${p.first_name} ${p.last_name ?? ''}`.trim();
const selectedKey = computed(() => (props.selected ? `${props.selected.type}-${props.selected.id}` : null));

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
        emailOpen.value = false;
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
    if (!confirm('Remove this customer? Their service history is preserved.')) return;
    router.delete(`/customers/${props.selected.id}`, { preserveScroll: true, onSuccess: () => closeDrawer() });
}

function grantPortal() {
    if (!props.selected || props.selected.type !== 'customer') return;
    const pw = window.prompt('Set an initial portal password for this customer (min 8 characters):');
    if (!pw || pw.length < 8) return;
    router.post(`/customers/${props.selected.id}/portal`, { password: pw }, { preserveScroll: true });
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
                class="rounded-md px-2.5 py-1 text-sm font-medium transition-colors"
                :class="props.filters.type === t.key ? 'bg-primary text-primary-foreground' : 'text-muted-foreground hover:bg-muted'"
                @click="setType(t.key)"
            >
                {{ t.label }} <span class="opacity-70">{{ props.counts[t.key] }}</span>
            </button>
        </template>

        <template #actions>
            <Input v-model="search" type="search" placeholder="Search people…" class="h-9 w-40 lg:w-52" />
            <Button v-if="props.canManage" size="sm" @click="openCreate"><Plus class="mr-1 size-4" /> Customer</Button>
            <Button v-if="props.canManage" size="sm" variant="outline" @click="openAgentCreate"><Plus class="mr-1 size-4" /> Agent</Button>
            <Button v-if="props.canEmail" size="sm" variant="outline" @click="openEmail"
                ><Mail class="mr-1 size-4" /> Email<span v-if="picked.length"> ({{ picked.length }})</span></Button
            >
        </template>

        <div class="flex h-full flex-1 flex-col gap-4 p-4">
            <MasterDetail
                :has-selection="anyFormOpen || props.selected !== null"
                :selection-key="anyFormOpen ? formKey : selectedKey"
                empty-text="Select a person to see details."
                @close="closePane"
            >
                <template #list>
                    <div class="overflow-hidden rounded-xl border border-border">
                        <table class="w-full text-sm">
                            <thead class="bg-muted/50 text-left text-muted-foreground">
                                <tr>
                                    <th v-if="props.canEmail" class="w-10 px-4 py-2">
                                        <input type="checkbox" :checked="allOnPage" aria-label="Select all" @change="toggleAll" />
                                    </th>
                                    <th class="px-4 py-2 font-medium">Name</th>
                                    <th class="px-4 py-2 font-medium">Type</th>
                                    <th class="hidden px-4 py-2 font-medium md:table-cell">Email</th>
                                    <th class="hidden px-4 py-2 font-medium lg:table-cell">Phone</th>
                                    <th class="hidden px-4 py-2 font-medium lg:table-cell">Last visit</th>
                                    <th class="px-4 py-2 text-right font-medium">Balance</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr
                                    v-for="person in props.people.data"
                                    :key="`${person.person_type}-${person.id}`"
                                    class="cursor-pointer border-t border-border transition-colors hover:bg-muted/40"
                                    :class="{ 'bg-muted/60': selectedKey === `${person.person_type}-${person.id}` }"
                                    @click="openPerson(person)"
                                >
                                    <td v-if="props.canEmail" class="px-4 py-2.5" @click.stop>
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
                                    <td class="hidden px-4 py-2.5 text-muted-foreground md:table-cell">{{ person.email ?? '—' }}</td>
                                    <td class="hidden px-4 py-2.5 text-muted-foreground lg:table-cell">{{ person.phone ?? '—' }}</td>
                                    <td class="hidden px-4 py-2.5 text-muted-foreground lg:table-cell">{{ person.last_visit ?? '—' }}</td>
                                    <td
                                        class="px-4 py-2.5 text-right font-medium"
                                        :class="person.balance && person.balance > 0 ? 'text-amber-600 dark:text-amber-400' : 'text-muted-foreground'"
                                    >
                                        {{ person.balance != null ? money(person.balance) : '—' }}
                                    </td>
                                </tr>
                                <tr v-if="props.people.data.length === 0">
                                    <td :colspan="props.canEmail ? 7 : 6" class="px-4 py-10 text-center text-muted-foreground">
                                        <Users class="mx-auto mb-2 size-6 opacity-50" />
                                        No people yet.
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </template>

                <template #detail>
                    <!-- create / edit customer form: hosted in the docked pane, not an overlay -->
                    <form v-if="formOpen" class="space-y-4 text-sm" @submit.prevent="submitForm">
                        <div class="mb-1">
                            <h2 class="text-lg font-semibold">{{ formMode === 'create' ? 'New customer' : 'Edit customer' }}</h2>
                            <p class="text-sm text-muted-foreground">
                                {{ formMode === 'create' ? 'Add a customer and optionally their first pool.' : 'Update contact details.' }}
                            </p>
                        </div>
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
                                class="rounded-md border border-input bg-background px-3 py-2 text-sm"
                            ></textarea>
                        </div>

                        <fieldset v-if="formMode === 'create'" class="space-y-3 rounded-lg border border-border p-3">
                            <legend class="px-1 text-xs font-medium text-muted-foreground">First pool (optional)</legend>
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
                                        class="h-9 rounded-md border border-input bg-background px-2 text-sm"
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
                        <div class="mb-1">
                            <h2 class="text-lg font-semibold">{{ agentFormMode === 'create' ? 'New agent' : 'Edit agent' }}</h2>
                            <p class="text-sm text-muted-foreground">
                                {{ agentFormMode === 'create' ? 'They log in with this email + password.' : 'Update profile + access.' }}
                            </p>
                        </div>
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
                                ><input id="a_color" v-model="agentForm.map_color" type="color" class="h-9 w-full rounded-md border border-input" />
                            </div>
                        </div>
                        <div class="grid gap-1.5">
                            <Label for="a_pw">{{ agentFormMode === 'create' ? 'Initial password' : 'New password (optional)' }}</Label>
                            <Input id="a_pw" v-model="agentForm.password" type="password" autocomplete="new-password" />
                            <p v-if="agentForm.errors.password" class="text-xs text-red-600">{{ agentForm.errors.password }}</p>
                        </div>
                        <label class="flex items-center gap-2"
                            ><input v-model="agentForm.agent_plus" type="checkbox" /> Agent+ (edit own schedule, skip, add stops)</label
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
                        <div class="mb-1">
                            <h2 class="text-lg font-semibold">Email people</h2>
                            <p class="text-sm text-muted-foreground">Send to a whole audience. Customers who opted out are excluded.</p>
                        </div>
                        <div class="grid gap-1.5">
                            <Label for="aud">Audience</Label>
                            <select id="aud" v-model="emailForm.audience" class="h-9 rounded-md border border-input bg-background px-2 text-sm">
                                <option v-for="a in audienceOptions" :key="a.key" :value="a.key">{{ a.label }} ({{ a.count }})</option>
                            </select>
                            <p class="text-xs text-muted-foreground">{{ emailCount }} recipient(s).</p>
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
                                class="rounded-md border border-input bg-background px-3 py-2 text-sm"
                            ></textarea>
                            <p v-if="emailForm.errors.body" class="text-xs text-red-600">{{ emailForm.errors.body }}</p>
                        </div>
                        <div v-if="props.recent.length" class="border-t border-border pt-2">
                            <p class="mb-1 text-xs font-medium text-muted-foreground">Recent</p>
                            <ul class="space-y-0.5 text-xs text-muted-foreground">
                                <li v-for="c in props.recent.slice(0, 5)" :key="c.id" class="flex justify-between gap-2">
                                    <span class="truncate">{{ c.subject }}</span
                                    ><span class="shrink-0">{{ c.recipients }} · {{ c.sent_on }}</span>
                                </li>
                            </ul>
                        </div>
                        <div class="flex justify-end gap-2 pt-2">
                            <Button type="button" variant="outline" @click="emailOpen = false">Cancel</Button>
                            <Button type="submit" :disabled="emailForm.processing || emailCount === 0"
                                ><Send class="mr-1 size-4" /> Send to {{ emailCount }}</Button
                            >
                        </div>
                    </form>

                    <div v-else-if="props.selected">
                        <div class="mb-4 flex items-center gap-3">
                            <EntityAvatar :src="props.selected.photo_url" type="person" :name="props.selected.name" size="lg" shape="circle" />
                            <div>
                                <h2 class="text-lg font-semibold">{{ props.selected.name }}</h2>
                                <p class="text-sm capitalize text-muted-foreground">{{ props.selected.type }}</p>
                            </div>
                        </div>

                        <div class="space-y-5 text-sm">
                            <div v-if="props.canManage && props.selected.type === 'customer'" class="flex flex-wrap gap-2">
                                <Button size="sm" variant="outline" @click="openEdit"><Pencil class="mr-1 size-3.5" /> Edit</Button>
                                <Button v-if="!props.selected.has_portal" size="sm" variant="outline" @click="grantPortal">Grant portal</Button>
                                <Button size="sm" variant="outline" @click="exportCustomer">Export</Button>
                                <Button size="sm" variant="outline" class="text-red-600 hover:text-red-600" @click="destroyCustomer"
                                    ><Trash2 class="mr-1 size-3.5" /> Remove</Button
                                >
                            </div>

                            <section>
                                <h3 class="mb-1 font-medium">Contact</h3>
                                <dl class="space-y-1 text-muted-foreground">
                                    <div class="flex justify-between">
                                        <dt>Email</dt>
                                        <dd>{{ props.selected.email ?? '—' }}</dd>
                                    </div>
                                    <div class="flex justify-between">
                                        <dt>Phone</dt>
                                        <dd>{{ props.selected.phone ?? '—' }}</dd>
                                    </div>
                                </dl>
                            </section>

                            <template v-if="props.selected.type === 'customer'">
                                <section>
                                    <h3 class="mb-1 font-medium">Pools ({{ props.selected.pools.length }})</h3>
                                    <ul class="space-y-1 text-muted-foreground">
                                        <li v-for="pool in props.selected.pools" :key="pool.id" class="flex justify-between">
                                            <span>{{ pool.name }}</span
                                            ><span class="capitalize">{{ pool.type.replace('_', ' ') }}</span>
                                        </li>
                                        <li v-if="props.selected.pools.length === 0">No pools.</li>
                                    </ul>
                                </section>
                                <section v-if="props.selected.recent_visits.length">
                                    <h3 class="mb-1 font-medium">Recent visits</h3>
                                    <ul class="space-y-1 text-muted-foreground">
                                        <li v-for="visit in props.selected.recent_visits" :key="visit.id" class="flex justify-between">
                                            <span>{{ visit.pool }}</span
                                            ><span>{{ visit.completed_on }}</span>
                                        </li>
                                    </ul>
                                </section>
                            </template>

                            <template v-else>
                                <div v-if="props.canManage" class="flex flex-wrap gap-2">
                                    <Button size="sm" variant="outline" @click="openAgentEdit"><Pencil class="mr-1 size-3.5" /> Edit</Button>
                                    <Button size="sm" variant="outline" class="text-red-600 hover:text-red-600" @click="destroyAgent"
                                        ><Trash2 class="mr-1 size-3.5" /> Remove</Button
                                    >
                                </div>
                                <section>
                                    <h3 class="mb-1 font-medium">Activity</h3>
                                    <dl class="space-y-1 text-muted-foreground">
                                        <div class="flex justify-between">
                                            <dt>Status</dt>
                                            <dd>{{ props.selected.is_active ? 'Active' : 'Inactive' }}</dd>
                                        </div>
                                        <div class="flex justify-between">
                                            <dt>Agent+ mode</dt>
                                            <dd>{{ props.selected.agent_plus ? 'On' : 'Off' }}</dd>
                                        </div>
                                        <div class="flex justify-between">
                                            <dt>Completed visits</dt>
                                            <dd>{{ props.selected.stats.completed_visits }}</dd>
                                        </div>
                                        <div class="flex justify-between">
                                            <dt>This week</dt>
                                            <dd>{{ props.selected.stats.this_week }}</dd>
                                        </div>
                                    </dl>
                                </section>
                            </template>
                        </div>
                    </div>
                </template>
            </MasterDetail>
        </div>
    </AppLayout>
</template>
