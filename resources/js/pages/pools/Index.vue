<script setup lang="ts">
import EntityAvatar from '@/components/EntityAvatar.vue';
import ImageUpload from '@/components/ImageUpload.vue';
import MasterDetail from '@/components/MasterDetail.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Sheet, SheetContent, SheetDescription, SheetHeader, SheetTitle } from '@/components/ui/sheet';
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem } from '@/types';
import { Head, router, useForm } from '@inertiajs/vue3';
import { Pencil, Plus, Trash2, Waves } from 'lucide-vue-next';
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
    customer: { name: string; email: string | null; phone: string | null };
    location: { city: string | null; gate_code: string | null; access_notes: string | null } | null;
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
        health: Health | null;
    } | null;
    fields: PoolFields;
}

const props = defineProps<{
    pools: { data: PoolRow[]; total: number };
    selected: PoolDetail | null;
    filters: { search: string };
    customers: { id: number; name: string }[];
    serviceTypes: { id: number; name: string }[];
    agents: { id: number; name: string }[];
    canManage: boolean;
}>();

type Subscription = PoolDetail['subscriptions'][number];

const breadcrumbs: BreadcrumbItem[] = [{ title: 'Pools', href: '/pools' }];

const search = ref(props.filters.search);
let searchTimer: ReturnType<typeof setTimeout> | undefined;
watch(search, (value) => {
    clearTimeout(searchTimer);
    searchTimer = setTimeout(
        () => router.get('/pools', { search: value || undefined }, { preserveState: true, replace: true, preserveScroll: true }),
        300,
    );
});

const openPool = (id: number) =>
    router.get('/pools', { search: search.value || undefined, selected: id }, { preserveState: true, preserveScroll: true });
const closeDrawer = () => router.get('/pools', { search: search.value || undefined }, { preserveState: true, preserveScroll: true });

const healthClasses: Record<Health['color'], string> = {
    green: 'bg-emerald-500/15 text-emerald-600 dark:text-emerald-400',
    amber: 'bg-amber-500/15 text-amber-600 dark:text-amber-400',
    red: 'bg-red-500/15 text-red-600 dark:text-red-400',
};

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
const days = ['monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday'];

const subForm = useForm<{
    pool_id: number | null;
    service_type_id: number | string;
    assigned_agent_id: number | string;
    frequency: string;
    preferred_day: string;
    status: string;
    hold_starts_at: string;
    hold_ends_at: string;
}>({
    pool_id: null,
    service_type_id: '',
    assigned_agent_id: '',
    frequency: 'weekly',
    preferred_day: '',
    status: 'active',
    hold_starts_at: '',
    hold_ends_at: '',
});

function openSubCreate() {
    if (!props.selected) return;
    subForm.reset();
    subForm.pool_id = props.selected.id;
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
            status: sub.status === 'active' ? 'paused' : 'active',
        },
        { preserveScroll: true },
    );
}

function removeSub(sub: Subscription) {
    if (!confirm('Remove this service plan?')) return;
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
            <Input v-model="search" type="search" placeholder="Search pools…" class="h-9 w-44 lg:w-56" />
            <Button v-if="props.canManage" size="sm" @click="openCreate"><Plus class="mr-1 size-4" /> Pool</Button>
        </template>

        <div class="flex h-full flex-1 flex-col gap-4 p-4">
            <MasterDetail
                :has-selection="props.selected !== null"
                :selection-key="props.selected?.id ?? null"
                :pane-open="!formOpen && !subFormOpen && !equipOpen && !serviceOpen && !targetsOpen"
                empty-text="Select a pool to see details."
                @close="closeDrawer"
            >
                <template #list>
                    <div class="overflow-hidden rounded-xl border border-border">
                        <table class="w-full text-sm">
                            <thead class="bg-muted/50 text-left text-muted-foreground">
                                <tr>
                                    <th class="w-8 px-4 py-2"><span class="sr-only">Health</span></th>
                                    <th class="px-4 py-2 font-medium">Name</th>
                                    <th class="px-4 py-2 font-medium">Customer</th>
                                    <th class="hidden px-4 py-2 font-medium md:table-cell">Type</th>
                                    <th class="hidden px-4 py-2 font-medium md:table-cell">Cadence</th>
                                    <th class="hidden px-4 py-2 font-medium lg:table-cell">Agent</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr
                                    v-for="pool in props.pools.data"
                                    :key="pool.id"
                                    class="cursor-pointer border-t border-border transition-colors hover:bg-muted/40"
                                    :class="{ 'bg-muted/60': props.selected?.id === pool.id }"
                                    @click="openPool(pool.id)"
                                >
                                    <td class="px-4 py-2.5">
                                        <span
                                            class="inline-block size-2.5 rounded-full"
                                            :class="
                                                pool.health
                                                    ? {
                                                          'bg-emerald-500': pool.health.color === 'green',
                                                          'bg-amber-500': pool.health.color === 'amber',
                                                          'bg-red-500': pool.health.color === 'red',
                                                      }
                                                    : 'bg-muted'
                                            "
                                            :title="pool.health?.label ?? 'No readings yet'"
                                        />
                                    </td>
                                    <td class="px-4 py-2.5">
                                        <div class="flex items-center gap-2.5">
                                            <EntityAvatar :src="pool.photo_url" type="pool" :name="pool.name" size="sm" />
                                            <span class="font-medium">{{ pool.name }}</span>
                                        </div>
                                    </td>
                                    <td class="px-4 py-2.5 text-muted-foreground">
                                        <div class="flex items-center gap-2">
                                            <EntityAvatar :src="pool.customer_photo" type="person" :name="pool.customer" size="sm" shape="circle" />
                                            <span>{{ pool.customer }}</span>
                                        </div>
                                    </td>
                                    <td class="hidden px-4 py-2.5 capitalize text-muted-foreground md:table-cell">
                                        {{ pool.type.replace('_', ' ') }}
                                    </td>
                                    <td class="hidden px-4 py-2.5 text-muted-foreground md:table-cell">{{ pool.cadence ?? '—' }}</td>
                                    <td class="hidden px-4 py-2.5 text-muted-foreground lg:table-cell">
                                        <div v-if="pool.agent" class="flex items-center gap-2">
                                            <EntityAvatar :src="pool.agent_photo" type="person" :name="pool.agent" size="sm" shape="circle" />
                                            <span>{{ pool.agent }}</span>
                                        </div>
                                        <span v-else>—</span>
                                    </td>
                                </tr>
                                <tr v-if="props.pools.data.length === 0">
                                    <td colspan="6" class="px-4 py-10 text-center text-muted-foreground">
                                        <Waves class="mx-auto mb-2 size-6 opacity-50" />
                                        No pools yet.
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </template>

                <template #detail>
                    <div v-if="props.selected">
                        <div class="mb-4 flex items-center gap-3">
                            <EntityAvatar :src="props.selected.photo_url" type="pool" :name="props.selected.name" size="lg" />
                            <div>
                                <h2 class="text-lg font-semibold">{{ props.selected.name }}</h2>
                                <p class="text-sm text-muted-foreground">{{ props.selected.customer.name }}</p>
                            </div>
                        </div>

                        <div class="space-y-5 text-sm">
                            <div v-if="props.canManage" class="flex flex-wrap gap-2">
                                <Button size="sm" variant="outline" @click="openEdit"><Pencil class="mr-1 size-3.5" /> Edit</Button>
                                <Button size="sm" variant="outline" @click="openTargets">Targets</Button>
                                <Button size="sm" variant="outline" class="text-red-600 hover:text-red-600" @click="destroyPool"
                                    ><Trash2 class="mr-1 size-3.5" /> Remove</Button
                                >
                            </div>

                            <section v-if="props.selected.latest_reading?.health" class="rounded-lg border border-border p-3">
                                <span
                                    class="inline-flex rounded-full px-2 py-0.5 text-xs font-medium"
                                    :class="healthClasses[props.selected.latest_reading.health.color]"
                                >
                                    {{ props.selected.latest_reading.health.label }}
                                </span>
                                <dl class="mt-2 grid grid-cols-3 gap-2 text-muted-foreground">
                                    <div>
                                        <dt class="text-xs">Free Cl</dt>
                                        <dd>{{ props.selected.latest_reading.free_chlorine ?? '—' }}</dd>
                                    </div>
                                    <div>
                                        <dt class="text-xs">pH</dt>
                                        <dd>{{ props.selected.latest_reading.ph ?? '—' }}</dd>
                                    </div>
                                    <div>
                                        <dt class="text-xs">Alk</dt>
                                        <dd>{{ props.selected.latest_reading.alkalinity ?? '—' }}</dd>
                                    </div>
                                </dl>
                            </section>

                            <section>
                                <h3 class="mb-1 font-medium">Specs</h3>
                                <dl class="space-y-1 text-muted-foreground">
                                    <div class="flex justify-between">
                                        <dt>Type</dt>
                                        <dd class="capitalize">{{ props.selected.type.replace('_', ' ') }}</dd>
                                    </div>
                                    <div class="flex justify-between">
                                        <dt>Volume</dt>
                                        <dd>{{ props.selected.volume_gallons ? props.selected.volume_gallons.toLocaleString() + ' gal' : '—' }}</dd>
                                    </div>
                                    <div class="flex justify-between">
                                        <dt>Sanitizer</dt>
                                        <dd class="capitalize">{{ props.selected.sanitizer }}</dd>
                                    </div>
                                    <div v-if="props.selected.equipment.length" class="flex justify-between">
                                        <dt>Equipment</dt>
                                        <dd>{{ props.selected.equipment.join(', ') }}</dd>
                                    </div>
                                </dl>
                            </section>

                            <section v-if="props.selected.location">
                                <h3 class="mb-1 font-medium">Location</h3>
                                <dl class="space-y-1 text-muted-foreground">
                                    <div class="flex justify-between">
                                        <dt>City</dt>
                                        <dd>{{ props.selected.location.city ?? '—' }}</dd>
                                    </div>
                                    <div v-if="props.selected.location.gate_code" class="flex justify-between">
                                        <dt>Gate code</dt>
                                        <dd class="font-mono">{{ props.selected.location.gate_code }}</dd>
                                    </div>
                                    <p v-if="props.selected.location.access_notes" class="pt-1 text-xs italic">
                                        {{ props.selected.location.access_notes }}
                                    </p>
                                </dl>
                            </section>

                            <section>
                                <div class="mb-1 flex items-center justify-between">
                                    <h3 class="font-medium">Service plans</h3>
                                    <Button v-if="props.canManage" size="sm" variant="outline" @click="openSubCreate"
                                        ><Plus class="mr-1 size-3.5" /> Plan</Button
                                    >
                                </div>
                                <ul class="space-y-2">
                                    <li
                                        v-for="sub in props.selected.subscriptions"
                                        :key="sub.id"
                                        class="rounded-md border border-border p-2 text-muted-foreground"
                                    >
                                        <div class="flex items-center justify-between">
                                            <span class="font-medium text-foreground">{{ sub.service }}</span>
                                            <span
                                                class="rounded-full px-2 py-0.5 text-xs font-medium capitalize"
                                                :class="
                                                    sub.status === 'active'
                                                        ? 'bg-emerald-500/15 text-emerald-600 dark:text-emerald-400'
                                                        : 'bg-amber-500/15 text-amber-600 dark:text-amber-400'
                                                "
                                                >{{ sub.status }}</span
                                            >
                                        </div>
                                        <div class="text-xs">{{ sub.schedule }} · {{ sub.agent }}</div>
                                        <div v-if="props.canManage" class="mt-1.5 flex gap-3 text-xs">
                                            <button class="hover:text-foreground" @click="openSubEdit(sub)">Edit</button>
                                            <button class="hover:text-foreground" @click="toggleSub(sub)">
                                                {{ sub.status === 'active' ? 'Pause' : 'Resume' }}
                                            </button>
                                            <button class="text-red-600 hover:text-red-700" @click="removeSub(sub)">Remove</button>
                                        </div>
                                    </li>
                                    <li v-if="props.selected.subscriptions.length === 0" class="text-sm text-muted-foreground">
                                        No service plans yet.
                                    </li>
                                </ul>
                            </section>

                            <section>
                                <div class="mb-1 flex items-center justify-between">
                                    <h3 class="font-medium">Equipment</h3>
                                    <Button v-if="props.canManage" size="sm" variant="outline" @click="openEquip"
                                        ><Plus class="mr-1 size-3.5" /> Equipment</Button
                                    >
                                </div>
                                <ul class="space-y-2">
                                    <li
                                        v-for="item in props.selected.equipment_items"
                                        :key="item.id"
                                        class="rounded-md border border-border p-2 text-muted-foreground"
                                    >
                                        <div class="flex items-center justify-between">
                                            <span class="font-medium capitalize text-foreground">{{ labelize(item.type) }}</span>
                                            <span v-if="item.make || item.model" class="text-xs">{{
                                                [item.make, item.model].filter(Boolean).join(' ')
                                            }}</span>
                                        </div>
                                        <ul v-if="item.service_log.length" class="mt-1 space-y-0.5 text-xs">
                                            <li v-for="log in item.service_log" :key="log.id" class="flex justify-between gap-2">
                                                <span class="truncate">{{ log.on }} · {{ log.description }}</span>
                                                <span v-if="log.cost > 0" class="shrink-0">${{ log.cost.toFixed(2) }}</span>
                                            </li>
                                        </ul>
                                        <div v-if="props.canManage" class="mt-1.5 flex gap-3 text-xs">
                                            <button class="hover:text-foreground" @click="openService(item)">Log service</button>
                                            <button class="text-red-600 hover:text-red-700" @click="removeEquip(item)">Remove</button>
                                        </div>
                                    </li>
                                    <li v-if="props.selected.equipment_items.length === 0" class="text-sm text-muted-foreground">
                                        No equipment tracked yet.
                                    </li>
                                </ul>
                            </section>
                        </div>
                    </div>
                </template>
            </MasterDetail>

            <!-- add equipment -->
            <Sheet v-model:open="equipOpen">
                <SheetContent class="w-full overflow-y-auto sm:max-w-md">
                    <SheetHeader>
                        <SheetTitle>Add equipment</SheetTitle>
                        <SheetDescription>Track a pump, filter, heater, salt cell, etc.</SheetDescription>
                    </SheetHeader>
                    <form class="mt-4 space-y-4 text-sm" @submit.prevent="submitEquip">
                        <div class="grid gap-1.5">
                            <Label for="eq_type">Type</Label>
                            <select
                                id="eq_type"
                                v-model="equipForm.type"
                                class="h-9 rounded-md border border-input bg-background px-2 text-sm capitalize"
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
                </SheetContent>
            </Sheet>

            <!-- log service -->
            <Sheet v-model:open="serviceOpen">
                <SheetContent class="w-full overflow-y-auto sm:max-w-md">
                    <SheetHeader>
                        <SheetTitle>Log service</SheetTitle>
                        <SheetDescription>Record a repair or maintenance — optionally bill it.</SheetDescription>
                    </SheetHeader>
                    <form class="mt-4 space-y-4 text-sm" @submit.prevent="submitService">
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
                </SheetContent>
            </Sheet>

            <!-- per-pool chemistry targets -->
            <Sheet v-model:open="targetsOpen">
                <SheetContent class="w-full overflow-y-auto sm:max-w-md">
                    <SheetHeader>
                        <SheetTitle>Chemistry targets</SheetTitle>
                        <SheetDescription>Override the default target ranges for this pool. Blank = use defaults.</SheetDescription>
                    </SheetHeader>
                    <form class="mt-4 space-y-3 text-sm" @submit.prevent="submitTargets">
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
                </SheetContent>
            </Sheet>

            <!-- create / edit pool -->
            <Sheet v-model:open="formOpen">
                <SheetContent class="w-full overflow-y-auto sm:max-w-md">
                    <SheetHeader>
                        <SheetTitle>{{ formMode === 'create' ? 'New pool' : 'Edit pool' }}</SheetTitle>
                        <SheetDescription>Specs, equipment, and the service location.</SheetDescription>
                    </SheetHeader>

                    <form class="mt-4 space-y-4 text-sm" @submit.prevent="submitForm">
                        <ImageUpload
                            :model-value="photoFile"
                            :current="formMode === 'edit' ? (props.selected?.photo_url ?? null) : null"
                            @update:model-value="(f) => (form.photo = f)"
                        />
                        <div class="grid gap-1.5">
                            <Label for="customer_id">Customer</Label>
                            <select
                                id="customer_id"
                                v-model="form.customer_id"
                                :disabled="formMode === 'edit'"
                                class="h-9 rounded-md border border-input bg-background px-2 text-sm disabled:opacity-60"
                            >
                                <option value="">Select a customer…</option>
                                <option v-for="c in props.customers" :key="c.id" :value="c.id">{{ c.name }}</option>
                            </select>
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
                                <select id="type" v-model="form.type" class="h-9 rounded-md border border-input bg-background px-2 text-sm">
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
                                    class="h-9 rounded-md border border-input bg-background px-2 text-sm"
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
                                <select id="filter" v-model="form.filter_type" class="h-9 rounded-md border border-input bg-background px-2 text-sm">
                                    <option value="">—</option>
                                    <option value="cartridge">Cartridge</option>
                                    <option value="sand">Sand</option>
                                    <option value="de">DE</option>
                                </select>
                            </div>
                        </div>

                        <div>
                            <span class="text-xs font-medium text-muted-foreground">Equipment</span>
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
                                class="rounded-md border border-input bg-background px-3 py-2 text-sm"
                            ></textarea>
                        </div>

                        <div class="flex justify-end gap-2 pt-2">
                            <Button type="button" variant="outline" @click="formOpen = false">Cancel</Button>
                            <Button type="submit" :disabled="form.processing">{{ formMode === 'create' ? 'Add pool' : 'Save' }}</Button>
                        </div>
                    </form>
                </SheetContent>
            </Sheet>

            <!-- create / edit service plan -->
            <Sheet v-model:open="subFormOpen">
                <SheetContent class="w-full overflow-y-auto sm:max-w-md">
                    <SheetHeader>
                        <SheetTitle>{{ subFormMode === 'create' ? 'New service plan' : 'Edit service plan' }}</SheetTitle>
                        <SheetDescription>The cadence + agent the materializer schedules.</SheetDescription>
                    </SheetHeader>
                    <form class="mt-4 space-y-4 text-sm" @submit.prevent="submitSub">
                        <div class="grid gap-1.5">
                            <Label for="sub_service">Service type</Label>
                            <select
                                id="sub_service"
                                v-model="subForm.service_type_id"
                                class="h-9 rounded-md border border-input bg-background px-2 text-sm"
                            >
                                <option value="">Select…</option>
                                <option v-for="t in props.serviceTypes" :key="t.id" :value="t.id">{{ t.name }}</option>
                            </select>
                            <p v-if="subForm.errors.service_type_id" class="text-xs text-red-600">{{ subForm.errors.service_type_id }}</p>
                        </div>
                        <div class="grid gap-1.5">
                            <Label for="sub_agent">Agent</Label>
                            <select
                                id="sub_agent"
                                v-model="subForm.assigned_agent_id"
                                class="h-9 rounded-md border border-input bg-background px-2 text-sm"
                            >
                                <option value="">Unassigned</option>
                                <option v-for="a in props.agents" :key="a.id" :value="a.id">{{ a.name }}</option>
                            </select>
                        </div>
                        <div class="grid grid-cols-2 gap-3">
                            <div class="grid gap-1.5">
                                <Label for="sub_freq">Frequency</Label>
                                <select
                                    id="sub_freq"
                                    v-model="subForm.frequency"
                                    class="h-9 rounded-md border border-input bg-background px-2 text-sm"
                                >
                                    <option value="weekly">Weekly</option>
                                    <option value="biweekly">Biweekly</option>
                                    <option value="monthly">Monthly</option>
                                    <option value="one_time">One-time</option>
                                    <option value="seasonal">Seasonal</option>
                                </select>
                            </div>
                            <div class="grid gap-1.5">
                                <Label for="sub_day">Preferred day</Label>
                                <select
                                    id="sub_day"
                                    v-model="subForm.preferred_day"
                                    class="h-9 rounded-md border border-input bg-background px-2 text-sm capitalize"
                                >
                                    <option value="">Any</option>
                                    <option v-for="d in days" :key="d" :value="d" class="capitalize">{{ d }}</option>
                                </select>
                            </div>
                        </div>
                        <div v-if="subFormMode === 'edit'" class="grid gap-1.5">
                            <Label for="sub_status">Status</Label>
                            <select id="sub_status" v-model="subForm.status" class="h-9 rounded-md border border-input bg-background px-2 text-sm">
                                <option value="active">Active</option>
                                <option value="paused">Paused</option>
                                <option value="cancelled">Cancelled</option>
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
                </SheetContent>
            </Sheet>
        </div>
    </AppLayout>
</template>
