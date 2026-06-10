<script setup lang="ts">
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Sheet, SheetContent, SheetDescription, SheetHeader, SheetTitle } from '@/components/ui/sheet';
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem } from '@/types';
import { Head, router, useForm } from '@inertiajs/vue3';
import { Pencil, Plus, Trash2, Waves } from 'lucide-vue-next';
import { ref, watch } from 'vue';

interface PoolRow {
    id: number;
    name: string;
    type: string;
    sanitizer: string;
    customer: string;
    city: string | null;
    cadence: string | null;
    agent: string | null;
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
    type: string;
    volume_gallons: number | null;
    sanitizer: string;
    filter: string | null;
    equipment: string[];
    customer: { name: string; email: string | null; phone: string | null };
    location: { city: string | null; gate_code: string | null; access_notes: string | null } | null;
    subscriptions: { id: number; schedule: string; agent: string }[];
    latest_reading: { taken_on: string | null; free_chlorine: number | null; ph: number | null; alkalinity: number | null; health: Health | null } | null;
    fields: PoolFields;
}

const props = defineProps<{
    pools: { data: PoolRow[]; total: number };
    selected: PoolDetail | null;
    filters: { search: string };
    customers: { id: number; name: string }[];
    canManage: boolean;
}>();

const breadcrumbs: BreadcrumbItem[] = [{ title: 'Pools', href: '/pools' }];

const search = ref(props.filters.search);
let searchTimer: ReturnType<typeof setTimeout> | undefined;
watch(search, (value) => {
    clearTimeout(searchTimer);
    searchTimer = setTimeout(() => router.get('/pools', { search: value || undefined }, { preserveState: true, replace: true, preserveScroll: true }), 300);
});

const openPool = (id: number) => router.get('/pools', { search: search.value || undefined, selected: id }, { preserveState: true, preserveScroll: true });
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
});

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
    form.clearErrors();
    formMode.value = 'edit';
    formId.value = props.selected.id;
    formOpen.value = true;
}

function submitForm() {
    if (formMode.value === 'create') {
        form.post('/pools', { preserveScroll: true, onSuccess: () => { formOpen.value = false; form.reset(); } });
    } else if (formId.value !== null) {
        form.patch(`/pools/${formId.value}`, { preserveScroll: true, onSuccess: () => { formOpen.value = false; } });
    }
}

function destroyPool() {
    if (!props.selected) return;
    if (!confirm('Remove this pool? Its service history is preserved.')) return;
    router.delete(`/pools/${props.selected.id}`, { preserveScroll: true, onSuccess: () => closeDrawer() });
}
</script>

<template>
    <Head title="Pools" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex h-full flex-1 flex-col gap-4 p-4">
            <div class="flex items-center justify-between gap-4">
                <div>
                    <h1 class="text-xl font-semibold">Pools</h1>
                    <p class="text-sm text-muted-foreground">{{ props.pools.total }} total</p>
                </div>
                <div class="flex items-center gap-2">
                    <Input v-model="search" type="search" placeholder="Search pools…" class="max-w-xs" />
                    <Button v-if="props.canManage" size="sm" @click="openCreate"><Plus class="mr-1 size-4" /> Pool</Button>
                </div>
            </div>

            <div class="overflow-hidden rounded-xl border border-border">
                <table class="w-full text-sm">
                    <thead class="bg-muted/50 text-left text-muted-foreground">
                        <tr>
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
                            <td class="px-4 py-2.5 font-medium">{{ pool.name }}</td>
                            <td class="px-4 py-2.5 text-muted-foreground">{{ pool.customer }}</td>
                            <td class="hidden px-4 py-2.5 capitalize text-muted-foreground md:table-cell">{{ pool.type.replace('_', ' ') }}</td>
                            <td class="hidden px-4 py-2.5 text-muted-foreground md:table-cell">{{ pool.cadence ?? '—' }}</td>
                            <td class="hidden px-4 py-2.5 text-muted-foreground lg:table-cell">{{ pool.agent ?? '—' }}</td>
                        </tr>
                        <tr v-if="props.pools.data.length === 0">
                            <td colspan="5" class="px-4 py-10 text-center text-muted-foreground">
                                <Waves class="mx-auto mb-2 size-6 opacity-50" />
                                No pools yet.
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <Sheet :open="props.selected !== null && !formOpen" @update:open="(open: boolean) => !open && closeDrawer()">
                <SheetContent class="w-full overflow-y-auto sm:max-w-md">
                    <template v-if="props.selected">
                        <SheetHeader>
                            <SheetTitle>{{ props.selected.name }}</SheetTitle>
                            <SheetDescription>{{ props.selected.customer.name }}</SheetDescription>
                        </SheetHeader>

                        <div class="mt-4 space-y-5 text-sm">
                            <div v-if="props.canManage" class="flex gap-2">
                                <Button size="sm" variant="outline" @click="openEdit"><Pencil class="mr-1 size-3.5" /> Edit</Button>
                                <Button size="sm" variant="outline" class="text-red-600 hover:text-red-600" @click="destroyPool"><Trash2 class="mr-1 size-3.5" /> Remove</Button>
                            </div>

                            <section v-if="props.selected.latest_reading?.health" class="rounded-lg border border-border p-3">
                                <span class="inline-flex rounded-full px-2 py-0.5 text-xs font-medium" :class="healthClasses[props.selected.latest_reading.health.color]">
                                    {{ props.selected.latest_reading.health.label }}
                                </span>
                                <dl class="mt-2 grid grid-cols-3 gap-2 text-muted-foreground">
                                    <div><dt class="text-xs">Free Cl</dt><dd>{{ props.selected.latest_reading.free_chlorine ?? '—' }}</dd></div>
                                    <div><dt class="text-xs">pH</dt><dd>{{ props.selected.latest_reading.ph ?? '—' }}</dd></div>
                                    <div><dt class="text-xs">Alk</dt><dd>{{ props.selected.latest_reading.alkalinity ?? '—' }}</dd></div>
                                </dl>
                            </section>

                            <section>
                                <h3 class="mb-1 font-medium">Specs</h3>
                                <dl class="space-y-1 text-muted-foreground">
                                    <div class="flex justify-between"><dt>Type</dt><dd class="capitalize">{{ props.selected.type.replace('_', ' ') }}</dd></div>
                                    <div class="flex justify-between"><dt>Volume</dt><dd>{{ props.selected.volume_gallons ? props.selected.volume_gallons.toLocaleString() + ' gal' : '—' }}</dd></div>
                                    <div class="flex justify-between"><dt>Sanitizer</dt><dd class="capitalize">{{ props.selected.sanitizer }}</dd></div>
                                    <div v-if="props.selected.equipment.length" class="flex justify-between"><dt>Equipment</dt><dd>{{ props.selected.equipment.join(', ') }}</dd></div>
                                </dl>
                            </section>

                            <section v-if="props.selected.location">
                                <h3 class="mb-1 font-medium">Location</h3>
                                <dl class="space-y-1 text-muted-foreground">
                                    <div class="flex justify-between"><dt>City</dt><dd>{{ props.selected.location.city ?? '—' }}</dd></div>
                                    <div v-if="props.selected.location.gate_code" class="flex justify-between"><dt>Gate code</dt><dd class="font-mono">{{ props.selected.location.gate_code }}</dd></div>
                                    <p v-if="props.selected.location.access_notes" class="pt-1 text-xs italic">{{ props.selected.location.access_notes }}</p>
                                </dl>
                            </section>

                            <section v-if="props.selected.subscriptions.length">
                                <h3 class="mb-1 font-medium">Service</h3>
                                <ul class="space-y-1 text-muted-foreground">
                                    <li v-for="sub in props.selected.subscriptions" :key="sub.id" class="flex justify-between"><span>{{ sub.schedule }}</span><span>{{ sub.agent }}</span></li>
                                </ul>
                            </section>
                        </div>
                    </template>
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
                        <div class="grid gap-1.5">
                            <Label for="customer_id">Customer</Label>
                            <select id="customer_id" v-model="form.customer_id" :disabled="formMode === 'edit'" class="h-9 rounded-md border border-input bg-background px-2 text-sm disabled:opacity-60">
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
                                <select id="sanitizer" v-model="form.sanitizer_type" class="h-9 rounded-md border border-input bg-background px-2 text-sm">
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
                                <label v-for="e in equipmentToggles" :key="e.key" class="flex items-center gap-2"><input v-model="form[e.key]" type="checkbox" /> {{ e.label }}</label>
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
                            <textarea id="access" v-model="form.access_notes" rows="2" class="rounded-md border border-input bg-background px-3 py-2 text-sm"></textarea>
                        </div>

                        <div class="flex justify-end gap-2 pt-2">
                            <Button type="button" variant="outline" @click="formOpen = false">Cancel</Button>
                            <Button type="submit" :disabled="form.processing">{{ formMode === 'create' ? 'Add pool' : 'Save' }}</Button>
                        </div>
                    </form>
                </SheetContent>
            </Sheet>
        </div>
    </AppLayout>
</template>
