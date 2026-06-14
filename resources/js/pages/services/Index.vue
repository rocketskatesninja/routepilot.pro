<script setup lang="ts">
import MasterDetail from '@/components/MasterDetail.vue';
import Pagination from '@/components/Pagination.vue';
import SortableTh from '@/components/SortableTh.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { useFitRows } from '@/composables/useFitRows';
import AppLayout from '@/layouts/AppLayout.vue';
import { formatMoney } from '@/lib/utils';
import { type BreadcrumbItem } from '@/types';
import { type Paginated } from '@/types/pagination';
import { Head, router, useForm } from '@inertiajs/vue3';
import { ClipboardList, Pencil, Plus, Trash2, X } from 'lucide-vue-next';
import { ref, watch } from 'vue';

interface ServiceRow {
    id: number;
    name: string;
    category: string | null;
    frequency: string;
    price: string;
    pools: number;
    is_active: boolean;
}

interface ServiceFields {
    name: string;
    category: string | null;
    frequency: string;
    estimated_duration_minutes: number;
    price: string;
    chemicals_included: boolean;
    description: string | null;
    tasks: string[];
    field_modules: Record<string, boolean>;
    is_active: boolean;
}

interface ServiceDetail {
    id: number;
    name: string;
    category: string | null;
    frequency: string;
    duration_minutes: number;
    price: string;
    chemicals_included: boolean;
    description: string | null;
    modules: string[];
    tasks: string[];
    pools: number;
    is_active: boolean;
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
const { listRef } = useFitRows(
    () => props.services.per_page,
    () => props.services.total,
);

const search = ref(props.filters.search);
let timer: ReturnType<typeof setTimeout> | undefined;
watch(search, (value) => {
    clearTimeout(timer);
    timer = setTimeout(
        () => router.get('/services', { search: value || undefined }, { preserveState: true, replace: true, preserveScroll: true }),
        300,
    );
});

const open = (id: number) =>
    router.get('/services', { search: search.value || undefined, selected: id }, { preserveState: true, preserveScroll: true });
const closeDrawer = () => router.get('/services', { search: search.value || undefined }, { preserveState: true, preserveScroll: true });
const money = formatMoney;

// The detail pane is shared: it hosts the create/edit form when one is open,
// otherwise the selected item's detail. Closing the pane cancels the form first.
function closePane() {
    if (formOpen.value) {
        formOpen.value = false;
    } else {
        closeDrawer();
    }
}

const moduleLabels: Record<string, string> = { tasks: 'Tasks', chemistry: 'Chemistry', treatments: 'Treatments', photos: 'Photos' };

// --- create / edit ---
const formOpen = ref(false);
const formMode = ref<'create' | 'edit'>('create');
const formId = ref<number | null>(null);

const blankModules = (): Record<string, boolean> => ({ tasks: true, chemistry: true, treatments: true, photos: true });
const form = useForm<{
    name: string;
    category: string;
    frequency: string;
    estimated_duration_minutes: number;
    price: string;
    chemicals_included: boolean;
    description: string;
    tasks: string[];
    field_modules: Record<string, boolean>;
    is_active: boolean;
}>({
    name: '',
    category: '',
    frequency: 'weekly',
    estimated_duration_minutes: 30,
    price: '0',
    chemicals_included: true,
    description: '',
    tasks: [],
    field_modules: blankModules(),
    is_active: true,
});

function openCreate() {
    form.reset();
    form.field_modules = blankModules();
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
    form.category = f.category ?? '';
    form.frequency = f.frequency;
    form.estimated_duration_minutes = f.estimated_duration_minutes;
    form.price = f.price;
    form.chemicals_included = f.chemicals_included;
    form.description = f.description ?? '';
    form.tasks = [...f.tasks];
    form.field_modules = { ...blankModules(), ...f.field_modules };
    form.is_active = f.is_active;
    form.clearErrors();
    formMode.value = 'edit';
    formId.value = props.selected.id;
    formOpen.value = true;
}

function submitForm() {
    if (formMode.value === 'create') {
        form.post('/services', {
            preserveScroll: true,
            onSuccess: () => {
                formOpen.value = false;
                form.reset();
            },
        });
    } else if (formId.value !== null) {
        form.patch(`/services/${formId.value}`, {
            preserveScroll: true,
            onSuccess: () => {
                formOpen.value = false;
            },
        });
    }
}

function destroyService() {
    if (!props.selected) return;
    if (!confirm('Remove this service type?')) return;
    router.delete(`/services/${props.selected.id}`, { preserveScroll: true, onSuccess: () => closeDrawer() });
}
</script>

<template>
    <Head title="Services" />

    <AppLayout :breadcrumbs="breadcrumbs" :meta="`${props.services.total} service types`">
        <template #actions>
            <Input v-model="search" type="search" placeholder="Search services…" class="h-9 w-44 lg:w-56" />
            <Button v-if="props.canManage" size="sm" @click="openCreate"><Plus class="mr-1 size-4" /> Service</Button>
        </template>

        <div class="flex h-full flex-1 flex-col gap-4 p-4">
            <MasterDetail
                :has-selection="formOpen || props.selected !== null"
                :selection-key="formOpen ? `form-${formMode}` : (props.selected?.id ?? null)"
                empty-text="Select a service type to see details."
                @close="closePane"
            >
                <template #list>
                    <div class="flex min-h-0 flex-col gap-3">
                        <div ref="listRef" class="overflow-hidden rounded-xl border border-border">
                            <table class="w-full text-sm">
                                <thead class="bg-muted/50 text-left text-muted-foreground">
                                    <tr>
                                        <SortableTh sort-key="name" :active="props.sort">Name</SortableTh>
                                        <SortableTh sort-key="category" :active="props.sort" class="hidden md:table-cell">Category</SortableTh>
                                        <SortableTh sort-key="price" :active="props.sort">Price</SortableTh>
                                        <SortableTh sort-key="pools" :active="props.sort" class="hidden md:table-cell">Pools</SortableTh>
                                        <SortableTh sort-key="status" :active="props.sort">Status</SortableTh>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr
                                        v-for="service in props.services.data"
                                        :key="service.id"
                                        class="cursor-pointer border-t border-border transition-colors hover:bg-muted/40"
                                        :class="{ 'bg-muted/60': props.selected?.id === service.id }"
                                        @click="open(service.id)"
                                    >
                                        <td class="px-4 py-2.5 font-medium">{{ service.name }}</td>
                                        <td class="hidden px-4 py-2.5 capitalize text-muted-foreground md:table-cell">
                                            {{ service.category ?? '—' }}
                                        </td>
                                        <td class="px-4 py-2.5 text-muted-foreground">{{ money(service.price) }}</td>
                                        <td class="hidden px-4 py-2.5 text-muted-foreground md:table-cell">{{ service.pools }}</td>
                                        <td class="px-4 py-2.5">
                                            <span
                                                class="inline-flex rounded-full px-2 py-0.5 text-xs font-medium"
                                                :class="
                                                    service.is_active
                                                        ? 'bg-emerald-500/15 text-emerald-600 dark:text-emerald-400'
                                                        : 'bg-muted text-muted-foreground'
                                                "
                                            >
                                                {{ service.is_active ? 'Active' : 'Inactive' }}
                                            </span>
                                        </td>
                                    </tr>
                                    <tr v-if="props.services.data.length === 0">
                                        <td colspan="5" class="px-4 py-10 text-center text-muted-foreground">
                                            <ClipboardList class="mx-auto mb-2 size-6 opacity-50" />
                                            No service types yet.
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                        <Pagination :meta="props.services" />
                    </div>
                </template>

                <template #detail>
                    <!-- create / edit form: hosted in the docked pane, not an overlay -->
                    <form v-if="formOpen" class="space-y-4 text-sm" @submit.prevent="submitForm">
                        <div class="mb-1">
                            <h2 class="text-lg font-semibold">{{ formMode === 'create' ? 'New service type' : 'Edit service type' }}</h2>
                            <p class="text-sm text-muted-foreground">A reusable visit template pools subscribe to.</p>
                        </div>
                        <div class="grid gap-1.5">
                            <Label for="name">Name</Label>
                            <Input id="name" v-model="form.name" />
                            <p v-if="form.errors.name" class="text-xs text-red-600">{{ form.errors.name }}</p>
                        </div>
                        <div class="grid grid-cols-2 gap-3">
                            <div class="grid gap-1.5">
                                <Label for="category">Category</Label>
                                <Input id="category" v-model="form.category" placeholder="routine, chemistry…" />
                            </div>
                            <div class="grid gap-1.5">
                                <Label for="frequency">Frequency</Label>
                                <select id="frequency" v-model="form.frequency" class="h-9 rounded-md border border-input bg-background px-2 text-sm">
                                    <option value="weekly">Weekly</option>
                                    <option value="biweekly">Biweekly</option>
                                    <option value="monthly">Monthly</option>
                                    <option value="one_time">One-time</option>
                                    <option value="seasonal">Seasonal</option>
                                </select>
                            </div>
                        </div>
                        <div class="grid grid-cols-2 gap-3">
                            <div class="grid gap-1.5">
                                <Label for="price">Price ($)</Label>
                                <Input id="price" v-model="form.price" type="number" step="0.01" min="0" />
                                <p v-if="form.errors.price" class="text-xs text-red-600">{{ form.errors.price }}</p>
                            </div>
                            <div class="grid gap-1.5">
                                <Label for="duration">Duration (min)</Label>
                                <Input id="duration" v-model="form.estimated_duration_minutes" type="number" min="5" max="600" />
                            </div>
                        </div>

                        <label class="flex items-center gap-2"
                            ><input v-model="form.chemicals_included" type="checkbox" /> Chemicals included in price</label
                        >

                        <div>
                            <span class="text-xs font-medium text-muted-foreground">At-pool field steps</span>
                            <div class="mt-1 grid grid-cols-2 gap-1">
                                <label v-for="(label, key) in moduleLabels" :key="key" class="flex items-center gap-2"
                                    ><input v-model="form.field_modules[key]" type="checkbox" /> {{ label }}</label
                                >
                            </div>
                        </div>

                        <div>
                            <div class="mb-1 flex items-center justify-between">
                                <span class="text-xs font-medium text-muted-foreground">Task checklist</span>
                                <Button type="button" size="sm" variant="outline" @click="form.tasks.push('')"
                                    ><Plus class="mr-1 size-3.5" /> Task</Button
                                >
                            </div>
                            <div class="space-y-1.5">
                                <div v-for="(task, i) in form.tasks" :key="i" class="flex gap-2">
                                    <Input v-model="form.tasks[i]" placeholder="e.g. Brush walls" />
                                    <Button type="button" size="icon" variant="outline" @click="form.tasks.splice(i, 1)"><X class="size-4" /></Button>
                                </div>
                            </div>
                        </div>

                        <div class="grid gap-1.5">
                            <Label for="desc">Description</Label>
                            <textarea
                                id="desc"
                                v-model="form.description"
                                rows="2"
                                class="rounded-md border border-input bg-background px-3 py-2 text-sm"
                            ></textarea>
                        </div>

                        <label class="flex items-center gap-2"
                            ><input v-model="form.is_active" type="checkbox" /> Active (offered to new pools)</label
                        >

                        <div class="flex justify-end gap-2 pt-2">
                            <Button type="button" variant="outline" @click="formOpen = false">Cancel</Button>
                            <Button type="submit" :disabled="form.processing">{{ formMode === 'create' ? 'Add service' : 'Save' }}</Button>
                        </div>
                    </form>

                    <div v-else-if="props.selected">
                        <div class="mb-4">
                            <h2 class="text-lg font-semibold">{{ props.selected.name }}</h2>
                            <p class="text-sm capitalize text-muted-foreground">
                                {{ props.selected.category ?? 'Service' }} · {{ props.selected.frequency }}
                            </p>
                        </div>

                        <div class="space-y-5 text-sm">
                            <div v-if="props.canManage" class="flex gap-2">
                                <Button size="sm" variant="outline" @click="openEdit"><Pencil class="mr-1 size-3.5" /> Edit</Button>
                                <Button size="sm" variant="outline" class="text-red-600 hover:text-red-600" @click="destroyService"
                                    ><Trash2 class="mr-1 size-3.5" /> Remove</Button
                                >
                            </div>

                            <section>
                                <dl class="space-y-1 text-muted-foreground">
                                    <div class="flex justify-between">
                                        <dt>Price</dt>
                                        <dd>{{ money(props.selected.price) }}</dd>
                                    </div>
                                    <div class="flex justify-between">
                                        <dt>Duration</dt>
                                        <dd>{{ props.selected.duration_minutes }} min</dd>
                                    </div>
                                    <div class="flex justify-between">
                                        <dt>Chemicals</dt>
                                        <dd>{{ props.selected.chemicals_included ? 'Included' : 'Billed separately' }}</dd>
                                    </div>
                                    <div class="flex justify-between">
                                        <dt>Active pools</dt>
                                        <dd>{{ props.selected.pools }}</dd>
                                    </div>
                                </dl>
                                <p v-if="props.selected.description" class="mt-2 text-muted-foreground">{{ props.selected.description }}</p>
                            </section>

                            <section>
                                <h3 class="mb-1 font-medium">Field steps</h3>
                                <div class="flex flex-wrap gap-1.5">
                                    <span v-for="m in props.selected.modules" :key="m" class="rounded-md bg-muted px-2 py-0.5 text-xs">{{ m }}</span>
                                </div>
                            </section>

                            <section v-if="props.selected.tasks.length">
                                <h3 class="mb-1 font-medium">Task checklist</h3>
                                <ul class="list-inside list-disc space-y-0.5 text-muted-foreground">
                                    <li v-for="(task, i) in props.selected.tasks" :key="i">{{ task }}</li>
                                </ul>
                            </section>
                        </div>
                    </div>
                </template>
            </MasterDetail>
        </div>
    </AppLayout>
</template>
