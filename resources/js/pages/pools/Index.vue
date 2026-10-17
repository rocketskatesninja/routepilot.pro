<script setup lang="ts">
import Combobox from '@/components/Combobox.vue';
import EntityAvatar from '@/components/EntityAvatar.vue';
import ImageUpload from '@/components/ImageUpload.vue';
import ListTable from '@/components/ListTable.vue';
import MasterDetail from '@/components/MasterDetail.vue';
import PlanScheduleFields from '@/components/PlanScheduleFields.vue';
import SortableTh from '@/components/SortableTh.vue';
import Chip from '@/components/pane/Chip.vue';
import IconTile from '@/components/pane/IconTile.vue';
import PaneAction from '@/components/pane/PaneAction.vue';
import PaneFacts from '@/components/pane/PaneFacts.vue';
import PaneHeader from '@/components/pane/PaneHeader.vue';
import PaneItem from '@/components/pane/PaneItem.vue';
import PaneList from '@/components/pane/PaneList.vue';
import PaneSection from '@/components/pane/PaneSection.vue';
import WaterChecks from '@/components/portal/WaterChecks.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { useListSearch } from '@/composables/useListSearch';
import AppLayout from '@/layouts/AppLayout.vue';
import { SCHEDULES } from '@/lib/labels';
import { agentLink, customerLink, reportLink } from '@/lib/links';
import { cap, checkWater, describePool, waterSummary } from '@/lib/portal';
import { formatDay, formatMoney } from '@/lib/utils';
import { type BreadcrumbItem } from '@/types';
import { type Paginated } from '@/types/pagination';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { CalendarDays, FileText, Pencil, Plus, Trash2, Waves, Wrench } from 'lucide-vue-next';
import { computed, ref, watch } from 'vue';

interface PoolRow {
    id: number;
    name: string;
    photo_url: string | null;
    type: string;
    sanitizer: string;
    customer: string;
    customer_photo: string | null;
    city: string | null;
    cadence: string | null;
    agent: string | null;
    agent_photo: string | null;
    health: Health | null;
}

interface Health {
    color: 'red' | 'amber' | 'green';
    label: string;
}

interface PoolFields {
    customer_id: number;
    name: string;
    type: string;
    volume_gallons: number | null;
    surface_type: string | null;
    sanitizer_type: string;
    filter_type: string | null;
    pump_type: string | null;
    has_heater: boolean;
    has_automation: boolean;
    has_pool_cleaner: boolean;
    has_cover: boolean;
    has_water_feature: boolean;
    has_auto_fill: boolean;
    notes: string | null;
    address_line1: string | null;
    city: string | null;
    state: string | null;
    zip: string | null;
    gate_code: string | null;
    access_notes: string | null;
}

interface PoolDetail {
    id: number;
    name: string;
    photo_url: string | null;
    type: string;
    volume_gallons: number | null;
    sanitizer: string;
    filter: string | null;
    equipment: string[];
    features: string[];
    customer: { id: number | null; name: string; email: string | null; phone: string | null };
    location: { address: string | null; city: string | null; gate_code: string | null; access_notes: string | null } | null;
    subscriptions: {
        id: number;
        service: string;
        schedule: string;
        agent: string;
        status: string;
        service_type_id: number;
        agent_id: number | null;
        frequency: string;
        preferred_day: string | null;
        starts_on: string | null;
        ends_on: string | null;
        day_of_month: number | null;
        hold_label: string | null;
        hold_starts_at: string | null;
        hold_ends_at: string | null;
    }[];
    equipment_items: {
        id: number;
        type: string;
        make: string | null;
        model: string | null;
        serial: string | null;
        installed_on: string | null;
        warranty_until: string | null;
        notes: string | null;
        service_log: { id: number; on: string | null; description: string; cost: number }[];
    }[];
    targets: Record<string, { min?: number; max?: number }>;
    latest_reading: {
        taken_on: string | null;
        free_chlorine: number | null;
        ph: number | null;
        alkalinity: number | null;
        calcium_hardness: number | null;
        cyanuric_acid: number | null;
        salt: number | null;
        health: Health | null;
        last_report_id: number | null;
    } | null;
    fields: PoolFields;
}

const props = defineProps<{
    pools: Paginated<PoolRow>;
    selected: PoolDetail | null;
    filters: { search: string };
    sort: { key: string; dir: string };
    customers: { id: number; name: string }[];
    serviceTypes: { id: number; name: string; frequency: string }[];
    agents: { id: number; name: string }[];
    canManage: boolean;
}>();

type Subscription = PoolDetail['subscriptions'][number];

const breadcrumbs: BreadcrumbItem[] = [{ title: 'Pools', href: '/pools' }];

const { search } = useListSearch('/pools', props.filters.search);

const openPool = (id: number) =>
    router.get('/pools', { search: search.value || undefined, selected: id }, { preserveState: true, preserveScroll: true });
const closeDrawer = () => router.get('/pools', { search: search.value || undefined }, { preserveState: true, preserveScroll: true });

// The detail pane is shared: it hosts the create/edit/sub-action forms when one
// is open, otherwise the selected pool's detail. Every form is a sub-action of
// the selected pool, so only one is open at a time. Closing the pane cancels the
// open form first; with no form open it deselects the pool.
const anyFormOpen = computed(() => formOpen.value || subFormOpen.value || equipOpen.value || serviceOpen.value || targetsOpen.value);
const formKey = computed(() => {
    if (formOpen.value) return `pool-${formMode.value}`;
    if (subFormOpen.value) return `sub-${subFormMode.value}`;
    if (equipOpen.value) return 'equipment';
    if (serviceOpen.value) return 'service';
    if (targetsOpen.value) return 'targets';
    return null;
});
function closePane() {
    if (anyFormOpen.value) {
        formOpen.value = false;
        subFormOpen.value = false;
        equipOpen.value = false;
        serviceOpen.value = false;
        targetsOpen.value = false;
    } else {
        closeDrawer();
    }
}

const healthTone = { green: 'good', amber: 'warn', red: 'bad' } as const;
const pool = computed(() => props.selected);
// The latest water test, checked against this pool's own targets.
const waterChecks = computed(() => (pool.value?.latest_reading ? checkWater(pool.value.latest_reading, pool.value.targets) : []));

const equipmentToggles = [
    { key: 'has_heater', label: 'Heater' },
    { key: 'has_automation', label: 'Automation' },
    { key: 'has_pool_cleaner', label: 'Cleaner' },
    { key: 'has_cover', label: 'Cover' },
    { key: 'has_water_feature', label: 'Water feature' },
    { key: 'has_auto_fill', label: 'Auto-fill' },
] as const;

// --- create / edit form ---
const formOpen = ref(false);
const formMode = ref<'create' | 'edit'>('create');
const formId = ref<number | null>(null);

const form = useForm<Record<string, unknown>>({
    customer_id: '',
    name: '',
    type: 'inground',
    volume_gallons: '',
    surface_type: '',
    sanitizer_type: 'chlorine',
    filter_type: '',
    pump_type: '',
    has_heater: false,
    has_automation: false,
    has_pool_cleaner: false,
    has_cover: false,
    has_water_feature: false,
    has_auto_fill: false,
    notes: '',
    address_line1: '',
    city: '',
    state: '',
    zip: '',
    gate_code: '',
    access_notes: '',
    photo: null,
});

const photoFile = computed(() => form.photo as File | null);

function openCreate() {
    form.reset();
    form.clearErrors();
    formMode.value = 'create';
    formId.value = null;
    formOpen.value = true;
}

function openEdit() {
    if (!props.selected) return;
    const f = props.selected.fields;
    form.customer_id = f.customer_id;
    form.name = f.name;
    form.type = f.type;
    form.volume_gallons = f.volume_gallons ?? '';
    form.surface_type = f.surface_type ?? '';
    form.sanitizer_type = f.sanitizer_type;
    form.filter_type = f.filter_type ?? '';
    form.pump_type = f.pump_type ?? '';
    form.has_heater = f.has_heater;
    form.has_automation = f.has_automation;
    form.has_pool_cleaner = f.has_pool_cleaner;
    form.has_cover = f.has_cover;
    form.has_water_feature = f.has_water_feature;
    form.has_auto_fill = f.has_auto_fill;
    form.notes = f.notes ?? '';
    form.address_line1 = f.address_line1 ?? '';
    form.city = f.city ?? '';
    form.state = f.state ?? '';
    form.zip = f.zip ?? '';
    form.gate_code = f.gate_code ?? '';
    form.access_notes = f.access_notes ?? '';
    form.photo = null;
    form.clearErrors();
    formMode.value = 'edit';
    formId.value = props.selected.id;
    formOpen.value = true;
}

function submitForm() {
    if (formMode.value === 'create') {
        form.post('/pools', {
            preserveScroll: true,
            onSuccess: () => {
                formOpen.value = false;
                form.reset();
            },
        });
    } else if (formId.value !== null) {
        form.patch(`/pools/${formId.value}`, {
            preserveScroll: true,
            onSuccess: () => {
                formOpen.value = false;
            },
        });
    }
}

function destroyPool() {
    if (!props.selected) return;
    if (!confirm('Remove this pool? Its service history is preserved.')) return;
    router.delete(`/pools/${props.selected.id}`, { preserveScroll: true, onSuccess: () => closeDrawer() });
}

// --- service plans (subscriptions) ---
const subFormOpen = ref(false);
const subFormMode = ref<'create' | 'edit'>('create');
const subFormId = ref<number | null>(null);
const planStatus: Record<string, string> = { active: 'Active', paused: 'Paused', cancelled: 'Ended' };
const localToday = () => new Date().toLocaleDateString('en-CA'); // YYYY-MM-DD in the office's time zone

const subForm = useForm<{
    pool_id: number | null;
    service_type_id: number | string;
    assigned_agent_id: number | string;
    frequency: string;
    preferred_day: string;
    day_of_month: number | string;
    starts_on: string;
    ends_on: string;
    status: string;
    hold_starts_at: string;
    hold_ends_at: string;
}>({
    pool_id: null,
    service_type_id: '',
    assigned_agent_id: '',
    frequency: 'weekly',
    preferred_day: '',
    day_of_month: '',
    starts_on: '',
    ends_on: '',
    status: 'active',
    hold_starts_at: '',
    hold_ends_at: '',
});

// A new plan starts from how its service is sold (e.g. a one-time job → One-time).
watch(
    () => subForm.service_type_id,
    (id) => {
        if (subFormMode.value !== 'create') return;
        const service = props.serviceTypes.find((t) => t.id === Number(id));
        if (service && SCHEDULES[service.frequency]) subForm.frequency = service.frequency;
    },
);

function openSubCreate() {
    if (!props.selected) return;
    subForm.reset();
    subForm.pool_id = props.selected.id;
    subForm.starts_on = localToday();
    subForm.clearErrors();
    subFormMode.value = 'create';
    subFormId.value = null;
    subFormOpen.value = true;
}

function openSubEdit(sub: Subscription) {
    subForm.service_type_id = sub.service_type_id;
    subForm.assigned_agent_id = sub.agent_id ?? '';
    subForm.frequency = sub.frequency;
    subForm.preferred_day = sub.preferred_day ?? '';
    subForm.day_of_month = sub.day_of_month ?? '';
    subForm.starts_on = sub.starts_on ?? '';
    subForm.ends_on = sub.ends_on ?? '';
    subForm.status = sub.status;
    subForm.hold_starts_at = sub.hold_starts_at ?? '';
    subForm.hold_ends_at = sub.hold_ends_at ?? '';
    subForm.clearErrors();
    subFormMode.value = 'edit';
    subFormId.value = sub.id;
    subFormOpen.value = true;
}

function submitSub() {
    if (subFormMode.value === 'create') {
        subForm.post('/subscriptions', {
            preserveScroll: true,
            onSuccess: () => {
                subFormOpen.value = false;
                subForm.reset();
            },
        });
    } else if (subFormId.value !== null) {
        subForm.patch(`/subscriptions/${subFormId.value}`, {
            preserveScroll: true,
            onSuccess: () => {
                subFormOpen.value = false;
            },
        });
    }
}

function toggleSub(sub: Subscription) {
    router.patch(
        `/subscriptions/${sub.id}`,
        {
            service_type_id: sub.service_type_id,
            assigned_agent_id: sub.agent_id,
            frequency: sub.frequency,
            preferred_day: sub.preferred_day,
            day_of_month: sub.day_of_month,
            starts_on: sub.starts_on,
            ends_on: sub.ends_on,
            status: sub.status === 'active' ? 'paused' : 'active',
        },
        { preserveScroll: true },
    );
}

function removeSub(sub: Subscription) {
    if (!confirm('Remove this service plan? (Only for plans added by mistake — a plan with completed visits must be ended instead.)')) return;
    router.delete(`/subscriptions/${sub.id}`, { preserveScroll: true });
}

// --- equipment ---
type Equipment = PoolDetail['equipment_items'][number];
const equipmentTypes = ['pump', 'filter', 'heater', 'salt_cell', 'cleaner', 'automation', 'other'];

const equipOpen = ref(false);
const equipForm = useForm({ pool_id: 0, type: 'pump', make: '', model: '', serial: '', installed_on: '', warranty_until: '', notes: '' });
function openEquip() {
    if (!props.selected) return;
    equipForm.reset();
    equipForm.pool_id = props.selected.id;
    equipForm.clearErrors();
    equipOpen.value = true;
}
function submitEquip() {
    equipForm.post('/equipment', {
        preserveScroll: true,
        onSuccess: () => {
            equipOpen.value = false;
            equipForm.reset();
        },
    });
}
function removeEquip(item: Equipment) {
    if (!confirm('Remove this equipment? Its service history is kept.')) return;
    router.delete(`/equipment/${item.id}`, { preserveScroll: true });
}

const serviceOpen = ref(false);
const serviceId = ref<number | null>(null);
const serviceForm = useForm({ serviced_on: '', description: '', cost: '', bill: false });
function openService(item: Equipment) {
    serviceForm.reset();
    serviceForm.clearErrors();
    serviceId.value = item.id;
    serviceOpen.value = true;
}
function submitService() {
    if (serviceId.value === null) return;
    serviceForm.post(`/equipment/${serviceId.value}/service`, {
        preserveScroll: true,
        onSuccess: () => {
            serviceOpen.value = false;
            serviceForm.reset();
        },
    });
}
const labelize = (s: string) => s.replace('_', ' ');

// --- per-pool chemistry targets ---
const chemParams = [
    { key: 'free_chlorine', label: 'Free chlorine' },
    { key: 'ph', label: 'pH' },
    { key: 'alkalinity', label: 'Alkalinity' },
    { key: 'calcium_hardness', label: 'Calcium' },
    { key: 'cyanuric_acid', label: 'CYA' },
    { key: 'salt', label: 'Salt' },
];
const targetsOpen = ref(false);
const targetsForm = useForm<{ targets: Record<string, { min: string; max: string }> }>({ targets: {} });
function openTargets() {
    if (!props.selected) return;
    const t: Record<string, { min: string; max: string }> = {};
    for (const p of chemParams) {
        const cur = props.selected.targets[p.key];
        t[p.key] = { min: cur?.min != null ? String(cur.min) : '', max: cur?.max != null ? String(cur.max) : '' };
    }
    targetsForm.targets = t;
    targetsForm.clearErrors();
    targetsOpen.value = true;
}
function submitTargets() {
    if (!props.selected) return;
    targetsForm.post(`/pools/${props.selected.id}/targets`, { preserveScroll: true, onSuccess: () => (targetsOpen.value = false) });
}
</script>

<template>
    <Head title="Pools" />

    <AppLayout :breadcrumbs="breadcrumbs" :meta="`${props.pools.total} total`">
        <template #actions>
            <Input v-model="search" type="search" placeholder="Search pools…" class="h-9 min-w-0 flex-1 sm:w-44 sm:flex-none lg:w-56" />
            <Button v-if="props.canManage" size="sm" title="New pool" @click="openCreate">
                <Plus class="size-4 sm:mr-1" /><span class="hidden sm:inline">Pool</span>
            </Button>
        </template>

        <div class="flex h-full flex-1 flex-col gap-4 p-4">
            <MasterDetail
                :has-selection="anyFormOpen || props.selected !== null"
                :selection-key="anyFormOpen ? formKey : (props.selected?.id ?? null)"
                empty-text="Select a pool to see details."
                @close="closePane"
            >
                <template #list>
                    <ListTable
                        :meta="props.pools"
                        :columns="6"
                        :row-key="(p) => p.id"
                        :selected-key="props.selected?.id ?? null"
                        @select="(p) => openPool(p.id)"
                    >
                        <template #head>
                            <th class="w-8 px-4 py-2"><span class="sr-only">Health</span></th>
                            <SortableTh sort-key="name" :active="props.sort">Name</SortableTh>
                            <SortableTh sort-key="customer" :active="props.sort">Customer</SortableTh>
                            <SortableTh sort-key="type" :active="props.sort" class="hidden min-[1440px]:table-cell md:table-cell xl:hidden"
                                >Type</SortableTh
                            >
                            <th class="hidden px-4 py-2 font-medium md:table-cell">Cadence</th>
                            <th class="hidden px-4 py-2 font-medium lg:table-cell">Agent</th>
                        </template>
                        <template #row="{ item }">
                            <td class="px-4 py-2.5">
                                <span
                                    class="inline-block size-2.5 rounded-full"
                                    :class="
                                        item.health
                                            ? {
                                                  'bg-emerald-500': item.health.color === 'green',
                                                  'bg-amber-500': item.health.color === 'amber',
                                                  'bg-red-500': item.health.color === 'red',
                                              }
                                            : 'bg-muted'
                                    "
                                    :title="item.health?.label ?? 'No readings yet'"
                                />
                            </td>
                            <td class="px-4 py-2.5">
                                <div class="flex items-center gap-2.5">
                                    <EntityAvatar :src="item.photo_url" type="pool" :name="item.name" size="sm" />
                                    <span class="font-medium">{{ item.name }}</span>
                                </div>
                            </td>
                            <td class="text-muted-foreground px-4 py-2.5">
                                <div class="flex items-center gap-2">
                                    <EntityAvatar :src="item.customer_photo" type="person" :name="item.customer" size="sm" shape="circle" />
                                    <span>{{ item.customer }}</span>
                                </div>
                            </td>
                            <td class="text-muted-foreground hidden px-4 py-2.5 capitalize min-[1440px]:table-cell md:table-cell xl:hidden">
                                {{ item.type.replace('_', ' ') }}
                            </td>
                            <td class="text-muted-foreground hidden px-4 py-2.5 md:table-cell">{{ item.cadence ?? '—' }}</td>
                            <td class="text-muted-foreground hidden px-4 py-2.5 lg:table-cell">
                                <div v-if="item.agent" class="flex items-center gap-2">
                                    <EntityAvatar :src="item.agent_photo" type="person" :name="item.agent" size="sm" shape="circle" />
                                    <span>{{ item.agent }}</span>
                                </div>
                                <span v-else>—</span>
                            </td>
                        </template>
                        <template #empty>
                            <Waves class="mx-auto mb-2 size-6 opacity-50" />
                            No pools yet.
                        </template>
                    </ListTable>
                </template>

                <template #detail>
                    <!-- create / edit pool: hosted in the docked pane, not an overlay -->
                    <form v-if="formOpen" class="space-y-4 text-sm" @submit.prevent="submitForm">
                        <PaneHeader :title="formMode === 'create' ? 'New pool' : 'Edit pool'" summary="What it is, its equipment, and where it is." />
                        <ImageUpload
                            :model-value="photoFile"
                            :current="formMode === 'edit' ? (props.selected?.photo_url ?? null) : null"
                            @update:model-value="(f) => (form.photo = f)"
                        />
                        <div class="grid gap-1.5">
                            <Label for="customer_id">Customer</Label>
                            <Combobox
                                id="customer_id"
                                v-model="form.customer_id"
                                :options="props.customers"
                                :disabled="formMode === 'edit'"
                                placeholder="Search customers…"
                            />
                            <p v-if="form.errors.customer_id" class="text-xs text-red-600">{{ form.errors.customer_id }}</p>
                        </div>
                        <div class="grid gap-1.5">
                            <Label for="name">Pool name</Label>
                            <Input id="name" v-model="form.name" />
                            <p v-if="form.errors.name" class="text-xs text-red-600">{{ form.errors.name }}</p>
                        </div>
                        <div class="grid grid-cols-2 gap-3">
                            <div class="grid gap-1.5">
                                <Label for="type">Type</Label>
                                <select id="type" v-model="form.type" class="border-input bg-background h-9 rounded-md border px-2 text-sm">
                                    <option value="inground">In-ground</option>
                                    <option value="above_ground">Above ground</option>
                                    <option value="spa">Spa</option>
                                    <option value="indoor">Indoor</option>
                                    <option value="infinity">Infinity</option>
                                    <option value="other">Other</option>
                                </select>
                            </div>
                            <div class="grid gap-1.5">
                                <Label for="volume">Volume (gal)</Label>
                                <Input id="volume" v-model="form.volume_gallons" type="number" min="0" />
                            </div>
                        </div>
                        <div class="grid grid-cols-2 gap-3">
                            <div class="grid gap-1.5">
                                <Label for="sanitizer">Sanitizer</Label>
                                <select
                                    id="sanitizer"
                                    v-model="form.sanitizer_type"
                                    class="border-input bg-background h-9 rounded-md border px-2 text-sm"
                                >
                                    <option value="chlorine">Chlorine</option>
                                    <option value="salt">Salt</option>
                                    <option value="bromine">Bromine</option>
                                    <option value="biguanide">Biguanide</option>
                                    <option value="ozone">Ozone</option>
                                    <option value="uv">UV</option>
                                    <option value="other">Other</option>
                                </select>
                            </div>
                            <div class="grid gap-1.5">
                                <Label for="filter">Filter</Label>
                                <select id="filter" v-model="form.filter_type" class="border-input bg-background h-9 rounded-md border px-2 text-sm">
                                    <option value="">—</option>
                                    <option value="cartridge">Cartridge</option>
                                    <option value="sand">Sand</option>
                                    <option value="de">DE</option>
                                </select>
                            </div>
                        </div>

                        <div>
                            <span class="text-muted-foreground text-xs font-medium">Equipment</span>
                            <div class="mt-1 grid grid-cols-2 gap-1">
                                <label v-for="e in equipmentToggles" :key="e.key" class="flex items-center gap-2"
                                    ><input v-model="form[e.key]" type="checkbox" /> {{ e.label }}</label
                                >
                            </div>
                        </div>

                        <div class="grid gap-1.5">
                            <Label for="paddr">Service address</Label>
                            <Input id="paddr" v-model="form.address_line1" />
                        </div>
                        <div class="grid grid-cols-3 gap-3">
                            <div class="grid gap-1.5"><Label for="pcity">City</Label><Input id="pcity" v-model="form.city" /></div>
                            <div class="grid gap-1.5"><Label for="pstate">State</Label><Input id="pstate" v-model="form.state" maxlength="2" /></div>
                            <div class="grid gap-1.5"><Label for="pzip">ZIP</Label><Input id="pzip" v-model="form.zip" /></div>
                        </div>
                        <div class="grid grid-cols-2 gap-3">
                            <div class="grid gap-1.5"><Label for="gate">Gate code</Label><Input id="gate" v-model="form.gate_code" /></div>
                        </div>
                        <div class="grid gap-1.5">
                            <Label for="access">Access notes</Label>
                            <textarea
                                id="access"
                                v-model="form.access_notes"
                                rows="2"
                                class="border-input bg-background rounded-md border px-3 py-2 text-sm"
                            ></textarea>
                        </div>

                        <div class="flex justify-end gap-2 pt-2">
                            <Button type="button" variant="outline" @click="formOpen = false">Cancel</Button>
                            <Button type="submit" :disabled="form.processing">{{ formMode === 'create' ? 'Add pool' : 'Save' }}</Button>
                        </div>
                    </form>

                    <!-- create / edit service plan -->
                    <form v-else-if="subFormOpen" class="space-y-4 text-sm" @submit.prevent="submitSub">
                        <PaneHeader
                            :title="subFormMode === 'create' ? 'New service plan' : 'Edit service plan'"
                            summary="Which service, how often, and who does it. Its visits go on the routes as soon as you save."
                        />
                        <div class="grid gap-1.5">
                            <Label for="sub_service">Service type</Label>
                            <select
                                id="sub_service"
                                v-model="subForm.service_type_id"
                                class="border-input bg-background h-9 rounded-md border px-2 text-sm"
                            >
                                <option value="">Select…</option>
                                <option v-for="t in props.serviceTypes" :key="t.id" :value="t.id">{{ t.name }}</option>
                            </select>
                            <p v-if="subForm.errors.service_type_id" class="text-xs text-red-600">{{ subForm.errors.service_type_id }}</p>
                        </div>
                        <PlanScheduleFields v-model="subForm" :agents="props.agents" id-prefix="sub" />
                        <div v-if="subFormMode === 'edit'" class="grid gap-1.5">
                            <Label for="sub_status">Status</Label>
                            <select id="sub_status" v-model="subForm.status" class="border-input bg-background h-9 rounded-md border px-2 text-sm">
                                <option value="active">Active</option>
                                <option value="paused">Paused</option>
                                <option value="cancelled">Ended</option>
                            </select>
                        </div>
                        <div v-if="subFormMode === 'edit'" class="grid grid-cols-2 gap-3">
                            <div class="grid gap-1.5">
                                <Label for="hold_start">Vacation hold from</Label
                                ><Input id="hold_start" v-model="subForm.hold_starts_at" type="date" />
                            </div>
                            <div class="grid gap-1.5">
                                <Label for="hold_end">to</Label>
                                <Input id="hold_end" v-model="subForm.hold_ends_at" type="date" />
                                <p v-if="subForm.errors.hold_ends_at" class="text-xs text-red-600">{{ subForm.errors.hold_ends_at }}</p>
                            </div>
                        </div>
                        <div class="flex justify-end gap-2 pt-2">
                            <Button type="button" variant="outline" @click="subFormOpen = false">Cancel</Button>
                            <Button type="submit" :disabled="subForm.processing">{{ subFormMode === 'create' ? 'Add plan' : 'Save' }}</Button>
                        </div>
                    </form>

                    <!-- add equipment -->
                    <form v-else-if="equipOpen" class="space-y-4 text-sm" @submit.prevent="submitEquip">
                        <PaneHeader title="Add equipment" summary="Keep track of a pump, filter, heater, salt cell and so on." />
                        <div class="grid gap-1.5">
                            <Label for="eq_type">Type</Label>
                            <select
                                id="eq_type"
                                v-model="equipForm.type"
                                class="border-input bg-background h-9 rounded-md border px-2 text-sm capitalize"
                            >
                                <option v-for="t in equipmentTypes" :key="t" :value="t">{{ labelize(t) }}</option>
                            </select>
                        </div>
                        <div class="grid grid-cols-2 gap-3">
                            <div class="grid gap-1.5"><Label for="eq_make">Make</Label><Input id="eq_make" v-model="equipForm.make" /></div>
                            <div class="grid gap-1.5"><Label for="eq_model">Model</Label><Input id="eq_model" v-model="equipForm.model" /></div>
                        </div>
                        <div class="grid gap-1.5"><Label for="eq_serial">Serial</Label><Input id="eq_serial" v-model="equipForm.serial" /></div>
                        <div class="grid grid-cols-2 gap-3">
                            <div class="grid gap-1.5">
                                <Label for="eq_inst">Installed</Label><Input id="eq_inst" v-model="equipForm.installed_on" type="date" />
                            </div>
                            <div class="grid gap-1.5">
                                <Label for="eq_warr">Warranty until</Label><Input id="eq_warr" v-model="equipForm.warranty_until" type="date" />
                            </div>
                        </div>
                        <div class="grid gap-1.5"><Label for="eq_notes">Notes</Label><Input id="eq_notes" v-model="equipForm.notes" /></div>
                        <div class="flex justify-end gap-2 pt-2">
                            <Button type="button" variant="outline" @click="equipOpen = false">Cancel</Button>
                            <Button type="submit" :disabled="equipForm.processing">Add</Button>
                        </div>
                    </form>

                    <!-- log service -->
                    <form v-else-if="serviceOpen" class="space-y-4 text-sm" @submit.prevent="submitService">
                        <PaneHeader title="Log service" summary="Record a repair or maintenance, and bill it if you like." />
                        <div class="grid grid-cols-2 gap-3">
                            <div class="grid gap-1.5">
                                <Label for="sv_on">Date</Label><Input id="sv_on" v-model="serviceForm.serviced_on" type="date" />
                            </div>
                            <div class="grid gap-1.5">
                                <Label for="sv_cost">Cost ($)</Label
                                ><Input id="sv_cost" v-model="serviceForm.cost" type="number" step="0.01" min="0" />
                            </div>
                        </div>
                        <div class="grid gap-1.5">
                            <Label for="sv_desc">Description</Label>
                            <Input id="sv_desc" v-model="serviceForm.description" />
                            <p v-if="serviceForm.errors.description" class="text-xs text-red-600">{{ serviceForm.errors.description }}</p>
                        </div>
                        <label class="flex items-center gap-2"
                            ><input v-model="serviceForm.bill" type="checkbox" /> Bill this repair to the customer</label
                        >
                        <div class="flex justify-end gap-2 pt-2">
                            <Button type="button" variant="outline" @click="serviceOpen = false">Cancel</Button>
                            <Button type="submit" :disabled="serviceForm.processing">Save</Button>
                        </div>
                    </form>

                    <!-- per-pool chemistry targets -->
                    <form v-else-if="targetsOpen" class="space-y-3 text-sm" @submit.prevent="submitTargets">
                        <PaneHeader title="Water targets" summary="Set this pool’s own healthy ranges. Leave a box blank to use the usual range." />
                        <div v-for="p in chemParams" :key="p.key" class="grid grid-cols-3 items-center gap-3">
                            <Label class="text-muted-foreground">{{ p.label }}</Label>
                            <Input v-model="targetsForm.targets[p.key].min" type="number" step="0.1" placeholder="min" />
                            <Input v-model="targetsForm.targets[p.key].max" type="number" step="0.1" placeholder="max" />
                        </div>
                        <div class="flex justify-end gap-2 pt-2">
                            <Button type="button" variant="outline" @click="targetsOpen = false">Cancel</Button>
                            <Button type="submit" :disabled="targetsForm.processing">Save targets</Button>
                        </div>
                    </form>

                    <div v-else-if="pool" class="space-y-6">
                        <PaneHeader
                            :title="pool.name"
                            :summary="
                                describePool({ volume: pool.volume_gallons, type: pool.type, sanitizer: pool.sanitizer, features: pool.features })
                            "
                        >
                            <template #media>
                                <img v-if="pool.photo_url" :src="pool.photo_url" :alt="pool.name" class="size-16 shrink-0 rounded-2xl object-cover" />
                                <IconTile v-else :icon="Waves" size="lg" />
                            </template>
                            <template v-if="pool.latest_reading?.health" #badge>
                                <Chip :tone="healthTone[pool.latest_reading.health.color]">{{ pool.latest_reading.health.label }}</Chip>
                            </template>
                            <p v-if="pool.customer.id" class="mt-2 text-[15px]">
                                <Link :href="customerLink(pool.customer.id)" class="text-primary font-medium underline-offset-4 hover:underline">{{
                                    pool.customer.name
                                }}</Link
                                ><template v-if="pool.location?.city">, {{ pool.location.city }}</template>
                            </p>
                            <template v-if="props.canManage" #actions>
                                <Button size="sm" variant="outline" @click="openEdit"><Pencil class="mr-1 size-3.5" /> Edit</Button>
                                <Button size="sm" variant="outline" @click="openTargets">Water targets</Button>
                                <Button size="sm" variant="outline" class="text-red-600 hover:text-red-600 dark:text-red-400" @click="destroyPool"
                                    ><Trash2 class="mr-1 size-3.5" /> Remove</Button
                                >
                            </template>
                        </PaneHeader>

                        <PaneSection :title="pool.latest_reading?.taken_on ? `Water on ${formatDay(pool.latest_reading.taken_on)}` : 'Water'">
                            <template v-if="pool.latest_reading?.last_report_id" #action>
                                <PaneAction :href="reportLink(pool.latest_reading.last_report_id)" :icon="FileText">Last report</PaneAction>
                            </template>
                            <template v-if="waterChecks.length">
                                <p class="text-muted-foreground mb-3 text-sm">{{ waterSummary(waterChecks, false) }}</p>
                                <div class="border-border rounded-2xl border px-4">
                                    <WaterChecks :checks="waterChecks" :explain="false" />
                                </div>
                            </template>
                            <p v-else class="text-muted-foreground text-sm">No water test yet.</p>
                        </PaneSection>

                        <PaneSection title="Service plans">
                            <template v-if="props.canManage" #action>
                                <PaneAction :icon="Plus" @click="openSubCreate">Add a plan</PaneAction>
                            </template>
                            <PaneList v-if="pool.subscriptions.length">
                                <PaneItem v-for="sub in pool.subscriptions" :key="sub.id" :title="sub.service">
                                    <template #icon><IconTile :icon="CalendarDays" size="sm" /></template>
                                    <template #sub>
                                        {{ sub.schedule
                                        }}<template v-if="sub.agent_id">
                                            with
                                            <Link :href="agentLink(sub.agent_id)" class="text-primary underline-offset-4 hover:underline">{{
                                                sub.agent
                                            }}</Link></template
                                        >
                                    </template>
                                    <p
                                        v-if="sub.hold_label && sub.status !== 'cancelled'"
                                        class="mt-0.5 font-medium text-amber-700 dark:text-amber-400"
                                    >
                                        {{ sub.hold_label }}
                                    </p>
                                    <div v-if="props.canManage" class="mt-1.5 flex gap-4">
                                        <PaneAction @click="openSubEdit(sub)">Edit</PaneAction>
                                        <PaneAction @click="toggleSub(sub)">
                                            {{ sub.status === 'active' ? 'Pause' : sub.status === 'paused' ? 'Resume' : 'Restart' }}
                                        </PaneAction>
                                        <PaneAction danger @click="removeSub(sub)">Remove</PaneAction>
                                    </div>
                                    <template #trailing>
                                        <Chip :tone="sub.status === 'active' ? 'good' : sub.status === 'paused' ? 'warn' : 'muted'">{{
                                            planStatus[sub.status] ?? sub.status
                                        }}</Chip>
                                    </template>
                                </PaneItem>
                            </PaneList>
                            <p v-else class="text-muted-foreground text-sm">No service plans yet.</p>
                        </PaneSection>

                        <PaneSection v-if="pool.location" title="Getting there">
                            <PaneFacts
                                :facts="[
                                    { label: 'Address', value: [pool.location.address, pool.location.city].filter(Boolean).join(', ') },
                                    { label: 'Gate code', value: pool.location.gate_code },
                                ]"
                            />
                            <p v-if="pool.location.access_notes" class="text-muted-foreground mt-2 text-sm">{{ pool.location.access_notes }}</p>
                        </PaneSection>

                        <PaneSection title="Equipment">
                            <template v-if="props.canManage" #action>
                                <PaneAction :icon="Plus" @click="openEquip">Add equipment</PaneAction>
                            </template>
                            <PaneList v-if="pool.equipment_items.length">
                                <PaneItem
                                    v-for="item in pool.equipment_items"
                                    :key="item.id"
                                    :title="cap(labelize(item.type))"
                                    :sub="[item.make, item.model].filter(Boolean).join(' ') || null"
                                >
                                    <template #icon><IconTile :icon="Wrench" size="sm" /></template>
                                    <ul v-if="item.service_log.length" class="text-muted-foreground mt-1 space-y-0.5">
                                        <li v-for="log in item.service_log" :key="log.id" class="flex justify-between gap-3">
                                            <span class="min-w-0 truncate">{{ formatDay(log.on) }}: {{ log.description }}</span>
                                            <span v-if="log.cost > 0" class="shrink-0 tabular-nums">{{ formatMoney(log.cost) }}</span>
                                        </li>
                                    </ul>
                                    <div v-if="props.canManage" class="mt-1.5 flex gap-4">
                                        <PaneAction @click="openService(item)">Log service</PaneAction>
                                        <PaneAction danger @click="removeEquip(item)">Remove</PaneAction>
                                    </div>
                                </PaneItem>
                            </PaneList>
                            <p v-else class="text-muted-foreground text-sm">No equipment tracked yet.</p>
                        </PaneSection>
                    </div>
                </template>
            </MasterDetail>
        </div>
    </AppLayout>
</template>
