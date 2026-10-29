<script setup lang="ts">
import ListTable from '@/components/ListTable.vue';
import MasterDetail from '@/components/MasterDetail.vue';
import SortableTh from '@/components/SortableTh.vue';
import Chip from '@/components/pane/Chip.vue';
import IconTile from '@/components/pane/IconTile.vue';
import PaneHeader from '@/components/pane/PaneHeader.vue';
import PaneItem from '@/components/pane/PaneItem.vue';
import PaneList from '@/components/pane/PaneList.vue';
import PaneSection from '@/components/pane/PaneSection.vue';
import ServiceOffer, { type Offer } from '@/components/portal/ServiceOffer.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { useListSearch } from '@/composables/useListSearch';
import AppLayout from '@/layouts/AppLayout.vue';
import { visitQuery } from '@/lib/listQuery';
import { andList, iconFor, soldAs as portalSoldAs, price } from '@/lib/portal';
import { SERVICE_TEMPLATES as templates } from '@/lib/serviceTemplates';
import { formatMoney } from '@/lib/utils';
import { type BreadcrumbItem } from '@/types';
import { type Paginated } from '@/types/pagination';
import { Head, router, useForm } from '@inertiajs/vue3';
import { Check, ClipboardList, Eye, EyeOff, Pencil, Plus, Trash2, X } from 'lucide-vue-next';
import { computed, ref } from 'vue';

interface ServiceRow {
    id: number;
    name: string;
    frequency: string;
    price: string;
    pools: number;
    is_active: boolean;
    requestable: boolean;
}

interface ServiceFields {
    name: string;
    description: string | null;
    frequency: string;
    price: string;
    estimated_duration_minutes: number;
    tasks: string[];
    field_modules: Record<string, boolean>;
    is_active: boolean;
    requestable: boolean;
}

interface ServiceDetail {
    id: number;
    name: string;
    description: string | null;
    frequency: string;
    price: string;
    offer: string;
    duration_minutes: number;
    modules: string[];
    tasks: string[];
    pools: number;
    is_active: boolean;
    requestable: boolean;
    fields: ServiceFields;
}

const props = defineProps<{
    services: Paginated<ServiceRow>;
    selected: ServiceDetail | null;
    filters: { search: string };
    sort: { key: string; dir: string };
    canManage: boolean;
}>();

const breadcrumbs: BreadcrumbItem[] = [{ title: 'Services', href: '/services' }];
const { search } = useListSearch(props.filters.search);

const open = (id: number) => visitQuery({ search: search.value || undefined, selected: id });
const closeDrawer = () => visitQuery({ search: search.value || undefined, selected: undefined });
const money = formatMoney;

// How a service is sold, in plain words.
const schedules: Record<string, string> = { weekly: 'Weekly', biweekly: 'Every 2 weeks', monthly: 'Monthly' };
const soldAs = (frequency: string) => (frequency === 'one_time' ? 'One-time job' : (schedules[frequency] ?? frequency));
const priceUnit = (frequency: string) => (frequency === 'one_time' ? 'for the job' : 'per visit');

// The visit sections the tech sees in the field app (same names they show there).
const sections: Record<string, string> = { tasks: 'Checklist', chemistry: 'Water test', treatments: 'Treatments', photos: 'Photos' };
const allSections = (): Record<string, boolean> => ({ tasks: true, chemistry: true, treatments: true, photos: true });

// The detail pane is shared: it hosts the form when one is open, otherwise the
// selected service. Closing the pane cancels the form first.
function closePane() {
    if (formOpen.value) {
        formOpen.value = false;
    } else {
        closeDrawer();
    }
}

// --- create / edit ---
const formOpen = ref(false);
const formMode = ref<'create' | 'edit'>('create');
const formId = ref<number | null>(null);

const form = useForm<{
    name: string;
    description: string;
    frequency: string;
    price: string;
    estimated_duration_minutes: number;
    tasks: string[];
    field_modules: Record<string, boolean>;
    is_active: boolean;
    requestable: boolean;
}>({
    name: '',
    description: '',
    frequency: 'weekly',
    price: '',
    estimated_duration_minutes: 30,
    tasks: [],
    field_modules: allSections(),
    is_active: true,
    requestable: true,
});

// Recurring vs one-time is the first choice; recurring then picks its default schedule.
const recurring = computed({
    get: () => form.frequency !== 'one_time',
    set: (yes: boolean) => (form.frequency = yes ? 'weekly' : 'one_time'),
});
// The form as the customer's card, updating as they type.
const previewOffer = computed<Offer>(() => ({
    name: form.name || 'Service name',
    description: form.description || 'Your short description will appear here.',
    frequency: form.frequency,
    price: Number(form.price) || 0,
    minutes: Number(form.estimated_duration_minutes) || null,
    tasks: form.field_modules.tasks ? form.tasks.filter((t) => t.trim() !== '') : [],
    modules: Object.keys(form.field_modules).filter((k) => form.field_modules[k]),
}));

// --- the selected service ---
const detail = computed(() => props.selected);
/** The saved service as the customer's card. */
const offerOf = (d: ServiceDetail): Offer => ({
    name: d.name,
    description: d.description,
    frequency: d.frequency,
    price: Number(d.price),
    minutes: d.duration_minutes,
    tasks: d.fields.field_modules.tasks ? d.tasks : [],
    modules: Object.keys(d.fields.field_modules).filter((k) => d.fields.field_modules[k]),
});
/** "$50 per visit, every week. About 30 minutes on site. On 9 pools." */
const serviceSummary = (d: ServiceDetail) =>
    `${price(Number(d.price))} ${d.frequency === 'one_time' ? 'for the job' : `per visit, ${portalSoldAs(d.frequency).toLowerCase()}`}. ` +
    `About ${d.duration_minutes} minutes on site. ${d.pools ? `On ${d.pools} pool${d.pools === 1 ? '' : 's'}.` : 'Not on any pools yet.'}`;

function applyTemplate(event: Event) {
    const t = templates[Number((event.target as HTMLSelectElement).value)];
    if (!t) return;
    form.name = t.name;
    form.description = t.description;
    form.frequency = t.frequency;
    form.price = t.price;
    form.estimated_duration_minutes = t.minutes;
    form.tasks = [...t.tasks];
    form.field_modules = { ...allSections(), ...t.sections };
}

function openCreate() {
    form.reset();
    form.field_modules = allSections();
    form.tasks = [];
    form.clearErrors();
    formMode.value = 'create';
    formId.value = null;
    formOpen.value = true;
}

function openEdit() {
    if (!props.selected) return;
    const f = props.selected.fields;
    form.name = f.name;
    form.description = f.description ?? '';
    form.frequency = f.frequency;
    form.price = f.price;
    form.estimated_duration_minutes = f.estimated_duration_minutes;
    form.tasks = [...f.tasks];
    form.field_modules = { ...allSections(), ...f.field_modules };
    form.is_active = f.is_active;
    form.requestable = f.requestable;
    form.clearErrors();
    formMode.value = 'edit';
    formId.value = props.selected.id;
    formOpen.value = true;
}

function submitForm() {
    if (formMode.value === 'create') {
        form.post('/services', { preserveScroll: true, onSuccess: () => (formOpen.value = false) });
    } else if (formId.value !== null) {
        form.patch(`/services/${formId.value}`, { preserveScroll: true, onSuccess: () => (formOpen.value = false) });
    }
}

function destroyService() {
    if (!props.selected) return;
    if (!confirm('Remove this service? (Only possible if no pool has a plan for it — otherwise mark it inactive.)')) return;
    router.delete(`/services/${props.selected.id}`, { preserveScroll: true });
}
</script>

<template>
    <Head title="Services" />

    <AppLayout :breadcrumbs="breadcrumbs" :meta="`${props.services.total} services`">
        <template #actions>
            <Input v-model="search" type="search" placeholder="Search services…" class="h-9 min-w-0 flex-1 sm:w-44 sm:flex-none lg:w-56" />
            <Button v-if="props.canManage" size="sm" title="New service" @click="openCreate">
                <Plus class="size-4 sm:mr-1" /><span class="hidden sm:inline">Service</span>
            </Button>
        </template>

        <div class="flex h-full flex-1 flex-col gap-4 p-4">
            <MasterDetail
                :has-selection="formOpen || props.selected !== null"
                :selection-key="formOpen ? `form-${formMode}` : (props.selected?.id ?? null)"
                empty-text="Select a service to see it, or add one with + Service."
                @close="closePane"
            >
                <template #list>
                    <ListTable
                        :meta="props.services"
                        :columns="5"
                        :row-key="(s) => s.id"
                        :selected-key="props.selected?.id ?? null"
                        @select="(s) => open(s.id)"
                    >
                        <template #head>
                            <SortableTh sort-key="name" :active="props.sort">Service</SortableTh>
                            <th class="hidden px-4 py-2 text-left font-medium md:table-cell">How it's sold</th>
                            <SortableTh sort-key="price" :active="props.sort">Price</SortableTh>
                            <SortableTh sort-key="pools" :active="props.sort" class="hidden md:table-cell">Plans</SortableTh>
                            <SortableTh sort-key="status" :active="props.sort">Status</SortableTh>
                        </template>
                        <template #row="{ item: service }">
                            <td class="px-4 py-2.5 font-medium">{{ service.name }}</td>
                            <td class="text-muted-foreground hidden px-4 py-2.5 md:table-cell">{{ soldAs(service.frequency) }}</td>
                            <td class="text-muted-foreground px-4 py-2.5 whitespace-nowrap">
                                {{ money(service.price) }} <span class="text-xs">{{ service.frequency === 'one_time' ? '/ job' : '/ visit' }}</span>
                            </td>
                            <td class="text-muted-foreground hidden px-4 py-2.5 md:table-cell">{{ service.pools }}</td>
                            <td class="px-4 py-2.5">
                                <span
                                    class="inline-flex rounded-full px-2 py-0.5 text-xs font-medium"
                                    :class="
                                        service.is_active
                                            ? 'bg-emerald-500/15 text-emerald-600 dark:text-emerald-400'
                                            : 'bg-muted text-muted-foreground'
                                    "
                                    >{{ service.is_active ? 'Active' : 'Inactive' }}</span
                                >
                                <span
                                    v-if="service.is_active && !service.requestable"
                                    class="text-muted-foreground ml-1 inline-flex items-center gap-1 text-xs"
                                    title="Customers can't see or request this"
                                    ><EyeOff class="size-3" /> Internal</span
                                >
                            </td>
                        </template>
                        <template #empty>
                            <ClipboardList class="mx-auto mb-2 size-6 opacity-50" />
                            No services yet — add one with <strong>+ Service</strong> (templates make it quick).
                        </template>
                    </ListTable>
                </template>

                <template #detail>
                    <!-- add / edit: in the order an owner thinks about a service -->
                    <form v-if="formOpen" class="space-y-5 text-sm" @submit.prevent="submitForm">
                        <PaneHeader
                            :title="formMode === 'create' ? 'New service' : 'Edit service'"
                            summary="Set it up once. You’ll pick it when you add a plan to a pool."
                        />

                        <div v-if="formMode === 'create'" class="grid gap-1.5">
                            <Label for="template">Start from a template</Label>
                            <select id="template" class="border-input bg-background h-9 w-64 rounded-md border px-2 text-sm" @change="applyTemplate">
                                <option value="">Blank service</option>
                                <option v-for="(t, i) in templates" :key="t.name" :value="i">{{ t.name }}</option>
                            </select>
                            <p class="text-muted-foreground text-xs">Fills in everything below — change whatever you like.</p>
                        </div>

                        <!-- first: these two decide whether customers see it at all (the preview below follows them) -->
                        <section class="space-y-2">
                            <h3 class="font-display text-base font-semibold tracking-tight">1. Availability</h3>
                            <label class="flex items-start gap-2"
                                ><input v-model="form.is_active" type="checkbox" class="mt-0.5" />
                                <span>Active <span class="text-muted-foreground">— can be added to pools</span></span></label
                            >
                            <label class="flex items-start gap-2" :class="form.is_active ? '' : 'opacity-50'"
                                ><input v-model="form.requestable" type="checkbox" class="mt-0.5" :disabled="!form.is_active" />
                                <span
                                    >Customers can request this
                                    <span class="text-muted-foreground">— shown in their portal and on your website</span></span
                                ></label
                            >
                        </section>

                        <!-- live preview: the same card customers see in their portal -->
                        <div class="border-border rounded-2xl border p-5">
                            <p class="text-muted-foreground mb-3 flex items-center gap-1.5 text-xs font-medium">
                                <Eye class="size-3.5" /> How customers see it
                            </p>
                            <ServiceOffer v-if="form.is_active && form.requestable" :service="previewOffer" />
                            <p v-else class="text-muted-foreground">Hidden. Customers won’t see this service.</p>
                        </div>

                        <section class="space-y-3">
                            <h3 class="font-display text-base font-semibold tracking-tight">2. What it is</h3>
                            <div class="grid gap-1.5">
                                <Label for="name">Name</Label>
                                <Input id="name" v-model="form.name" placeholder="e.g. Weekly pool service" />
                                <p v-if="form.errors.name" class="text-xs text-red-600">{{ form.errors.name }}</p>
                            </div>
                            <div class="grid gap-1.5">
                                <Label for="desc"
                                    >Short description <span class="text-muted-foreground font-normal">(customers read this)</span></Label
                                >
                                <textarea
                                    id="desc"
                                    v-model="form.description"
                                    rows="2"
                                    maxlength="500"
                                    placeholder="What's included, in a sentence or two"
                                    class="border-input bg-background rounded-md border px-3 py-2 text-sm"
                                ></textarea>
                            </div>
                        </section>

                        <section class="space-y-3">
                            <h3 class="font-display text-base font-semibold tracking-tight">3. How it's sold</h3>
                            <div class="flex gap-2">
                                <Button type="button" size="sm" :variant="recurring ? 'default' : 'outline'" @click="recurring = true"
                                    >Recurring</Button
                                >
                                <Button type="button" size="sm" :variant="recurring ? 'outline' : 'default'" @click="recurring = false"
                                    >One-time job</Button
                                >
                            </div>
                            <div v-if="recurring" class="grid gap-1.5">
                                <Label for="frequency">Usual schedule</Label>
                                <select
                                    id="frequency"
                                    v-model="form.frequency"
                                    class="border-input bg-background h-9 w-40 rounded-md border px-2 text-sm"
                                >
                                    <option v-for="(label, key) in schedules" :key="key" :value="key">{{ label }}</option>
                                </select>
                                <p class="text-muted-foreground text-xs">New plans start with this — you can change it per pool.</p>
                            </div>
                        </section>

                        <section class="space-y-3">
                            <h3 class="font-display text-base font-semibold tracking-tight">4. Price</h3>
                            <div class="flex items-center gap-2">
                                <span class="text-muted-foreground">$</span>
                                <Input id="price" v-model="form.price" type="number" step="0.01" min="0" class="w-28" />
                                <span class="text-muted-foreground">{{ priceUnit(form.frequency) }}</span>
                            </div>
                            <p v-if="form.errors.price" class="text-xs text-red-600">{{ form.errors.price }}</p>
                            <p v-else class="text-muted-foreground text-xs">The same price shows on your website and is what each visit is billed.</p>
                        </section>

                        <section class="space-y-3">
                            <h3 class="font-display text-base font-semibold tracking-tight">5. What the tech does</h3>
                            <div>
                                <span class="text-muted-foreground text-xs">Sections on the tech's visit screen</span>
                                <div class="mt-1 grid grid-cols-2 gap-1">
                                    <label v-for="(label, key) in sections" :key="key" class="flex items-center gap-2"
                                        ><input v-model="form.field_modules[key]" type="checkbox" /> {{ label }}</label
                                    >
                                </div>
                            </div>
                            <div v-if="form.field_modules.tasks">
                                <div class="mb-1 flex items-center justify-between">
                                    <span class="text-muted-foreground text-xs">Checklist</span>
                                    <Button type="button" size="sm" variant="outline" @click="form.tasks.push('')"
                                        ><Plus class="mr-1 size-3.5" /> Item</Button
                                    >
                                </div>
                                <p v-if="!form.tasks.length" class="text-muted-foreground text-xs">No checklist items yet.</p>
                                <div class="space-y-1.5">
                                    <div v-for="(task, i) in form.tasks" :key="i" class="flex gap-2">
                                        <Input v-model="form.tasks[i]" placeholder="e.g. Brush walls" />
                                        <Button type="button" size="icon" variant="outline" title="Remove" @click="form.tasks.splice(i, 1)"
                                            ><X class="size-4"
                                        /></Button>
                                    </div>
                                </div>
                            </div>
                        </section>

                        <section class="space-y-3">
                            <h3 class="font-display text-base font-semibold tracking-tight">6. Time on site</h3>
                            <div class="flex items-center gap-2">
                                <Input id="duration" v-model="form.estimated_duration_minutes" type="number" min="5" max="600" class="w-24" />
                                <span class="text-muted-foreground">minutes</span>
                            </div>
                            <p v-if="form.errors.estimated_duration_minutes" class="text-xs text-red-600">
                                {{ form.errors.estimated_duration_minutes }}
                            </p>
                            <p v-else class="text-muted-foreground text-xs">Customers see it as how long a visit takes.</p>
                        </section>

                        <div class="flex justify-end gap-2 pt-1">
                            <Button type="button" variant="outline" @click="formOpen = false">Cancel</Button>
                            <Button type="submit" :disabled="form.processing">{{ formMode === 'create' ? 'Add service' : 'Save' }}</Button>
                        </div>
                    </form>

                    <div v-else-if="detail" class="space-y-6">
                        <PaneHeader :title="detail.name" :summary="serviceSummary(detail)">
                            <template #media><IconTile :icon="iconFor(offerOf(detail).modules, detail.frequency)" size="lg" /></template>
                            <template #badge>
                                <Chip :tone="!detail.is_active ? 'muted' : detail.requestable ? 'good' : 'info'">{{
                                    !detail.is_active ? 'Inactive' : detail.requestable ? 'Offered to customers' : 'Staff only'
                                }}</Chip>
                            </template>
                            <template v-if="props.canManage" #actions>
                                <Button size="sm" variant="outline" @click="openEdit"><Pencil class="mr-1 size-3.5" /> Edit</Button>
                                <Button size="sm" variant="outline" class="text-red-600 hover:text-red-600 dark:text-red-400" @click="destroyService"
                                    ><Trash2 class="mr-1 size-3.5" /> Remove</Button
                                >
                            </template>
                        </PaneHeader>

                        <PaneSection title="How customers see it">
                            <div v-if="detail.is_active && detail.requestable" class="border-border rounded-2xl border p-5">
                                <ServiceOffer :service="offerOf(detail)" />
                            </div>
                            <p v-else class="text-muted-foreground text-sm">
                                {{
                                    detail.is_active
                                        ? 'Customers don’t see this one. Only your team can add it to a pool.'
                                        : 'It’s turned off, so nobody can add it to a pool.'
                                }}
                            </p>
                        </PaneSection>

                        <PaneSection title="On the visit">
                            <p class="text-muted-foreground mb-3 text-sm">
                                {{
                                    detail.modules.length
                                        ? `The tech’s visit screen has ${andList(detail.modules.map((m) => m.toLowerCase()))}.`
                                        : 'The tech’s visit screen has notes only.'
                                }}
                            </p>
                            <PaneList v-if="detail.tasks.length && !(detail.is_active && detail.requestable)">
                                <PaneItem v-for="(task, i) in detail.tasks" :key="i" :title="task">
                                    <template #icon><Check class="text-primary size-4 shrink-0" /></template>
                                </PaneItem>
                            </PaneList>
                        </PaneSection>
                    </div>
                </template>
            </MasterDetail>
        </div>
    </AppLayout>
</template>
