<script setup lang="ts">
import EntityAvatar from '@/components/EntityAvatar.vue';
import ImageUpload from '@/components/ImageUpload.vue';
import MasterDetail from '@/components/MasterDetail.vue';
import SortableTh from '@/components/SortableTh.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/AppLayout.vue';
import { agentLink } from '@/lib/links';
import { formatMoney } from '@/lib/utils';
import { type BreadcrumbItem } from '@/types';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { FlaskConical, Pencil, Plus, Trash2 } from 'lucide-vue-next';
import { ref, watch } from 'vue';

interface InventoryRow {
    id: number;
    name: string;
    photo_url: string | null;
    unit: string;
    stock: number;
    low: boolean;
    cost_per_unit: number | null;
}

interface InventoryFields {
    chemical_name: string;
    unit: string;
    reorder_threshold: number | null;
    cost_per_unit: number | null;
    sell_price: number | null;
    supplier: string | null;
    is_active: boolean;
}

interface InventoryDetail {
    id: number;
    name: string;
    photo_url: string | null;
    unit: string;
    stock: number;
    reorder_threshold: number | null;
    cost_per_unit: number | null;
    sell_price: number | null;
    supplier: string | null;
    value: number | null;
    low: boolean;
    transactions: { id: number; type: string; quantity: number; on: string | null; agent: string | null; agent_id: number | null }[];
    fields: InventoryFields;
}

const props = defineProps<{
    items: { data: InventoryRow[]; total: number };
    selected: InventoryDetail | null;
    filters: { search: string };
    sort: { key: string; dir: string };
    canManage: boolean;
}>();

const breadcrumbs: BreadcrumbItem[] = [{ title: 'Inventory', href: '/inventory' }];

const search = ref(props.filters.search);
let timer: ReturnType<typeof setTimeout> | undefined;
watch(search, (value) => {
    clearTimeout(timer);
    timer = setTimeout(
        () => router.get('/inventory', { search: value || undefined }, { preserveState: true, replace: true, preserveScroll: true }),
        300,
    );
});

const open = (id: number) =>
    router.get('/inventory', { search: search.value || undefined, selected: id }, { preserveState: true, preserveScroll: true });
const closeDrawer = () => router.get('/inventory', { search: search.value || undefined }, { preserveState: true, preserveScroll: true });
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

// --- create / edit chemical ---
const formOpen = ref(false);
const formMode = ref<'create' | 'edit'>('create');
const formId = ref<number | null>(null);
const form = useForm<{
    chemical_name: string;
    unit: string;
    current_stock: string;
    reorder_threshold: string;
    cost_per_unit: string;
    sell_price: string;
    supplier: string;
    is_active: boolean;
    photo: File | null;
}>({
    chemical_name: '',
    unit: 'gal',
    current_stock: '0',
    reorder_threshold: '',
    cost_per_unit: '',
    sell_price: '',
    supplier: '',
    is_active: true,
    photo: null,
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
    form.chemical_name = f.chemical_name;
    form.unit = f.unit;
    form.reorder_threshold = f.reorder_threshold !== null ? String(f.reorder_threshold) : '';
    form.cost_per_unit = f.cost_per_unit !== null ? String(f.cost_per_unit) : '';
    form.sell_price = f.sell_price !== null ? String(f.sell_price) : '';
    form.supplier = f.supplier ?? '';
    form.is_active = f.is_active;
    form.photo = null;
    form.clearErrors();
    formMode.value = 'edit';
    formId.value = props.selected.id;
    formOpen.value = true;
}

function submitForm() {
    if (formMode.value === 'create') {
        form.post('/inventory', {
            preserveScroll: true,
            onSuccess: () => {
                formOpen.value = false;
                form.reset();
            },
        });
    } else if (formId.value !== null) {
        form.patch(`/inventory/${formId.value}`, {
            preserveScroll: true,
            onSuccess: () => {
                formOpen.value = false;
            },
        });
    }
}

function destroyChemical() {
    if (!props.selected) return;
    if (!confirm('Remove this chemical?')) return;
    router.delete(`/inventory/${props.selected.id}`, { preserveScroll: true, onSuccess: () => closeDrawer() });
}

// --- adjust stock (inline in the drawer) ---
const adjustForm = useForm({ type: 'restock', quantity: '', notes: '' });
function submitAdjust() {
    if (!props.selected || adjustForm.quantity === '') return;
    adjustForm.post(`/inventory/${props.selected.id}/adjust`, { preserveScroll: true, onSuccess: () => adjustForm.reset() });
}
</script>

<template>
    <Head title="Inventory" />

    <AppLayout :breadcrumbs="breadcrumbs" :meta="`${props.items.total} chemicals`">
        <template #actions>
            <Input v-model="search" type="search" placeholder="Search chemicals…" class="h-9 w-44 lg:w-56" />
            <Button v-if="props.canManage" size="sm" @click="openCreate"><Plus class="mr-1 size-4" /> Chemical</Button>
        </template>

        <div class="flex h-full flex-1 flex-col gap-4 p-4">
            <MasterDetail
                :has-selection="formOpen || props.selected !== null"
                :selection-key="formOpen ? `form-${formMode}` : (props.selected?.id ?? null)"
                empty-text="Select a chemical to see details."
                @close="closePane"
            >
                <template #list>
                    <div class="overflow-hidden rounded-xl border border-border">
                        <table class="w-full text-sm">
                            <thead class="bg-muted/50 text-left text-muted-foreground">
                                <tr>
                                    <SortableTh sort-key="name" :active="props.sort">Chemical</SortableTh>
                                    <SortableTh sort-key="stock" :active="props.sort">In stock</SortableTh>
                                    <th class="px-4 py-2 font-medium">Status</th>
                                    <SortableTh sort-key="cost" :active="props.sort" class="hidden md:table-cell">Cost</SortableTh>
                                </tr>
                            </thead>
                            <tbody>
                                <tr
                                    v-for="item in props.items.data"
                                    :key="item.id"
                                    class="cursor-pointer border-t border-border transition-colors hover:bg-muted/40"
                                    :class="{ 'bg-muted/60': props.selected?.id === item.id }"
                                    @click="open(item.id)"
                                >
                                    <td class="px-4 py-2.5">
                                        <div class="flex items-center gap-2.5">
                                            <EntityAvatar :src="item.photo_url" type="inventory" :name="item.name" size="sm" />
                                            <span class="font-medium">{{ item.name }}</span>
                                        </div>
                                    </td>
                                    <td class="px-4 py-2.5 text-muted-foreground">{{ item.stock }} {{ item.unit }}</td>
                                    <td class="px-4 py-2.5">
                                        <span
                                            class="inline-flex rounded-full px-2 py-0.5 text-xs font-medium"
                                            :class="
                                                item.low
                                                    ? 'bg-red-500/15 text-red-600 dark:text-red-400'
                                                    : 'bg-emerald-500/15 text-emerald-600 dark:text-emerald-400'
                                            "
                                        >
                                            {{ item.low ? 'Low' : 'OK' }}
                                        </span>
                                    </td>
                                    <td class="hidden px-4 py-2.5 text-muted-foreground md:table-cell">
                                        {{ item.cost_per_unit !== null ? money(item.cost_per_unit) + '/' + item.unit : '—' }}
                                    </td>
                                </tr>
                                <tr v-if="props.items.data.length === 0">
                                    <td colspan="4" class="px-4 py-10 text-center text-muted-foreground">
                                        <FlaskConical class="mx-auto mb-2 size-6 opacity-50" />
                                        No chemicals in stock.
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </template>

                <template #detail>
                    <!-- create / edit form: hosted in the docked pane, not an overlay -->
                    <form v-if="formOpen" class="space-y-4 text-sm" @submit.prevent="submitForm">
                        <div class="mb-1">
                            <h2 class="text-lg font-semibold">{{ formMode === 'create' ? 'New chemical' : 'Edit chemical' }}</h2>
                            <p class="text-sm text-muted-foreground">Stock changes are logged via Adjust stock.</p>
                        </div>
                        <ImageUpload
                            :model-value="form.photo"
                            :current="formMode === 'edit' ? (props.selected?.photo_url ?? null) : null"
                            @update:model-value="(f) => (form.photo = f)"
                        />
                        <div class="grid grid-cols-3 gap-3">
                            <div class="col-span-2 grid gap-1.5">
                                <Label for="cname">Name</Label>
                                <Input id="cname" v-model="form.chemical_name" />
                                <p v-if="form.errors.chemical_name" class="text-xs text-red-600">{{ form.errors.chemical_name }}</p>
                            </div>
                            <div class="grid gap-1.5">
                                <Label for="cunit">Unit</Label><Input id="cunit" v-model="form.unit" placeholder="gal / lbs" />
                            </div>
                        </div>
                        <div v-if="formMode === 'create'" class="grid grid-cols-2 gap-3">
                            <div class="grid gap-1.5">
                                <Label for="cstock">Starting stock</Label
                                ><Input id="cstock" v-model="form.current_stock" type="number" min="0" step="0.01" />
                            </div>
                            <div class="grid gap-1.5">
                                <Label for="creorder">Reorder at</Label
                                ><Input id="creorder" v-model="form.reorder_threshold" type="number" min="0" step="0.01" />
                            </div>
                        </div>
                        <div v-else class="grid gap-1.5">
                            <Label for="creorder2">Reorder at</Label
                            ><Input id="creorder2" v-model="form.reorder_threshold" type="number" min="0" step="0.01" />
                        </div>
                        <div class="grid grid-cols-2 gap-3">
                            <div class="grid gap-1.5">
                                <Label for="ccost">Cost / unit ($)</Label
                                ><Input id="ccost" v-model="form.cost_per_unit" type="number" min="0" step="0.01" />
                            </div>
                            <div class="grid gap-1.5">
                                <Label for="csell">Sell price ($)</Label
                                ><Input id="csell" v-model="form.sell_price" type="number" min="0" step="0.01" />
                            </div>
                        </div>
                        <div class="grid gap-1.5"><Label for="csup">Supplier</Label><Input id="csup" v-model="form.supplier" /></div>
                        <label v-if="formMode === 'edit'" class="flex items-center gap-2"
                            ><input v-model="form.is_active" type="checkbox" /> Active</label
                        >
                        <div class="flex justify-end gap-2 pt-2">
                            <Button type="button" variant="outline" @click="formOpen = false">Cancel</Button>
                            <Button type="submit" :disabled="form.processing">{{ formMode === 'create' ? 'Add chemical' : 'Save' }}</Button>
                        </div>
                    </form>

                    <div v-else-if="props.selected">
                        <div class="mb-4 flex items-center gap-3">
                            <EntityAvatar :src="props.selected.photo_url" type="inventory" :name="props.selected.name" size="lg" />
                            <div>
                                <h2 class="text-lg font-semibold">{{ props.selected.name }}</h2>
                                <p class="text-sm text-muted-foreground">{{ props.selected.stock }} {{ props.selected.unit }} in stock</p>
                            </div>
                        </div>

                        <div class="space-y-5 text-sm">
                            <div v-if="props.canManage" class="flex gap-2">
                                <Button size="sm" variant="outline" @click="openEdit"><Pencil class="mr-1 size-3.5" /> Edit</Button>
                                <Button size="sm" variant="outline" class="text-red-600 hover:text-red-600" @click="destroyChemical"
                                    ><Trash2 class="mr-1 size-3.5" /> Remove</Button
                                >
                            </div>

                            <dl class="space-y-1 text-muted-foreground">
                                <div class="flex justify-between">
                                    <dt>Reorder at</dt>
                                    <dd>{{ props.selected.reorder_threshold ?? '—' }} {{ props.selected.unit }}</dd>
                                </div>
                                <div class="flex justify-between">
                                    <dt>Cost</dt>
                                    <dd>{{ props.selected.cost_per_unit !== null ? money(props.selected.cost_per_unit) : '—' }}</dd>
                                </div>
                                <div class="flex justify-between">
                                    <dt>Sell price</dt>
                                    <dd>{{ props.selected.sell_price !== null ? money(props.selected.sell_price) : '—' }}</dd>
                                </div>
                                <div class="flex justify-between">
                                    <dt>Stock value</dt>
                                    <dd>{{ props.selected.value !== null ? money(props.selected.value) : '—' }}</dd>
                                </div>
                                <div v-if="props.selected.supplier" class="flex justify-between">
                                    <dt>Supplier</dt>
                                    <dd>{{ props.selected.supplier }}</dd>
                                </div>
                            </dl>

                            <section v-if="props.canManage" class="rounded-lg border border-border p-3">
                                <h3 class="mb-2 font-medium">Adjust stock</h3>
                                <form class="flex flex-wrap items-end gap-2" @submit.prevent="submitAdjust">
                                    <select v-model="adjustForm.type" class="h-9 rounded-md border border-input bg-background px-2 text-sm">
                                        <option value="restock">Restock (+)</option>
                                        <option value="usage">Usage (−)</option>
                                        <option value="adjustment">Set to…</option>
                                    </select>
                                    <Input v-model="adjustForm.quantity" type="number" min="0" step="0.01" placeholder="Qty" class="w-24" />
                                    <Button type="submit" size="sm" :disabled="adjustForm.processing || adjustForm.quantity === ''">Apply</Button>
                                </form>
                            </section>

                            <section v-if="props.selected.transactions.length">
                                <h3 class="mb-1 font-medium">Recent movement</h3>
                                <ul class="space-y-1 text-muted-foreground">
                                    <li v-for="t in props.selected.transactions" :key="t.id" class="flex justify-between">
                                        <span class="capitalize">{{ t.type }} · {{ t.quantity }} {{ props.selected.unit }}</span>
                                        <span>
                                            <template v-if="t.agent">
                                                <Link v-if="t.agent_id" :href="agentLink(t.agent_id)" class="text-primary hover:underline">{{
                                                    t.agent
                                                }}</Link>
                                                <template v-else>{{ t.agent }}</template>
                                                ·
                                            </template>
                                            {{ t.on }}
                                        </span>
                                    </li>
                                </ul>
                            </section>
                        </div>
                    </div>
                </template>
            </MasterDetail>
        </div>
    </AppLayout>
</template>
