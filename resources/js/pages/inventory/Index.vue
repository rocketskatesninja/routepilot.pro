<script setup lang="ts">
import EntityAvatar from '@/components/EntityAvatar.vue';
import ImageUpload from '@/components/ImageUpload.vue';
import ListTable from '@/components/ListTable.vue';
import MasterDetail from '@/components/MasterDetail.vue';
import SortableTh from '@/components/SortableTh.vue';
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
import { useListSearch } from '@/composables/useListSearch';
import AppLayout from '@/layouts/AppLayout.vue';
import { agentLink } from '@/lib/links';
import { formatDay, formatMoney } from '@/lib/utils';
import { type BreadcrumbItem } from '@/types';
import { type Paginated } from '@/types/pagination';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { FlaskConical, Pencil, Plus, Trash2 } from 'lucide-vue-next';
import { computed, ref } from 'vue';

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
    items: Paginated<InventoryRow>;
    selected: InventoryDetail | null;
    filters: { search: string };
    sort: { key: string; dir: string };
    canManage: boolean;
}>();

const breadcrumbs: BreadcrumbItem[] = [{ title: 'Inventory', href: '/inventory' }];

const { search } = useListSearch('/inventory', props.filters.search);

const open = (id: number) =>
    router.get('/inventory', { search: search.value || undefined, selected: id }, { preserveState: true, preserveScroll: true });
const closeDrawer = () => router.get('/inventory', { search: search.value || undefined }, { preserveState: true, preserveScroll: true });
const money = formatMoney;

// --- the side panel ---
const chemical = computed(() => props.selected);
const amount = (n: number, unit: string) => `${Number.isInteger(n) ? n : n.toFixed(2)} ${unit}`;
/** "Restocked 10 gal" / "Used 2 gal" / "Counted 50 gal". */
const movement = (t: InventoryDetail['transactions'][number], unit: string) =>
    `${{ restock: 'Restocked', usage: 'Used', adjustment: 'Counted' }[t.type] ?? t.type} ${amount(t.quantity, unit)}`;

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
            <Input v-model="search" type="search" placeholder="Search chemicals…" class="h-9 min-w-0 flex-1 sm:w-44 sm:flex-none lg:w-56" />
            <Button v-if="props.canManage" size="sm" title="New chemical" @click="openCreate">
                <Plus class="size-4 sm:mr-1" /><span class="hidden sm:inline">Chemical</span>
            </Button>
        </template>

        <div class="flex h-full flex-1 flex-col gap-4 p-4">
            <MasterDetail
                :has-selection="formOpen || props.selected !== null"
                :selection-key="formOpen ? `form-${formMode}` : (props.selected?.id ?? null)"
                empty-text="Select a chemical to see details."
                @close="closePane"
            >
                <template #list>
                    <ListTable
                        :meta="props.items"
                        :columns="4"
                        :row-key="(i) => i.id"
                        :selected-key="props.selected?.id ?? null"
                        @select="(i) => open(i.id)"
                    >
                        <template #head>
                            <SortableTh sort-key="name" :active="props.sort">Chemical</SortableTh>
                            <SortableTh sort-key="stock" :active="props.sort">In stock</SortableTh>
                            <th class="px-4 py-2 font-medium">Status</th>
                            <SortableTh sort-key="cost" :active="props.sort" class="hidden md:table-cell">Cost</SortableTh>
                        </template>
                        <template #row="{ item }">
                            <td class="px-4 py-2.5">
                                <div class="flex items-center gap-2.5">
                                    <EntityAvatar :src="item.photo_url" type="inventory" :name="item.name" size="sm" />
                                    <span class="font-medium">{{ item.name }}</span>
                                </div>
                            </td>
                            <td class="text-muted-foreground px-4 py-2.5">{{ item.stock }} {{ item.unit }}</td>
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
                            <td class="text-muted-foreground hidden px-4 py-2.5 md:table-cell">
                                {{ item.cost_per_unit !== null ? money(item.cost_per_unit) + '/' + item.unit : '—' }}
                            </td>
                        </template>
                        <template #empty>
                            <FlaskConical class="mx-auto mb-2 size-6 opacity-50" />
                            No chemicals in stock.
                        </template>
                    </ListTable>
                </template>

                <template #detail>
                    <!-- create / edit form: hosted in the docked pane, not an overlay -->
                    <form v-if="formOpen" class="space-y-4 text-sm" @submit.prevent="submitForm">
                        <PaneHeader
                            :title="formMode === 'create' ? 'New chemical' : 'Edit chemical'"
                            summary="Change how much you have with Update stock, so every change is logged."
                        />
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

                    <div v-else-if="chemical" class="space-y-6">
                        <PaneHeader
                            :title="chemical.name"
                            :summary="`${amount(chemical.stock, chemical.unit)} in stock.${chemical.reorder_threshold !== null ? ` Reorder at ${amount(chemical.reorder_threshold, chemical.unit)}.` : ''}`"
                        >
                            <template #media>
                                <img
                                    v-if="chemical.photo_url"
                                    :src="chemical.photo_url"
                                    :alt="chemical.name"
                                    class="size-16 shrink-0 rounded-2xl object-cover"
                                />
                                <IconTile v-else :icon="FlaskConical" size="lg" />
                            </template>
                            <template #badge>
                                <Chip :tone="chemical.low ? 'warn' : 'good'">{{ chemical.low ? 'Running low' : 'In stock' }}</Chip>
                            </template>
                            <template v-if="props.canManage" #actions>
                                <Button size="sm" variant="outline" @click="openEdit"><Pencil class="mr-1 size-3.5" /> Edit</Button>
                                <Button size="sm" variant="outline" class="text-red-600 hover:text-red-600 dark:text-red-400" @click="destroyChemical"
                                    ><Trash2 class="mr-1 size-3.5" /> Remove</Button
                                >
                            </template>
                        </PaneHeader>

                        <PaneSection v-if="props.canManage" title="Update stock">
                            <form class="flex flex-wrap items-center gap-2" @submit.prevent="submitAdjust">
                                <select
                                    v-model="adjustForm.type"
                                    aria-label="Kind of change"
                                    class="border-input bg-background h-9 w-36 rounded-md border px-2 text-sm"
                                >
                                    <option value="restock">Restocked</option>
                                    <option value="usage">Used</option>
                                    <option value="adjustment">Counted, now</option>
                                </select>
                                <Input
                                    v-model="adjustForm.quantity"
                                    type="number"
                                    min="0"
                                    step="0.01"
                                    :aria-label="`Amount in ${chemical.unit}`"
                                    class="w-24"
                                />
                                <span class="text-muted-foreground text-sm">{{ chemical.unit }}</span>
                                <Button type="submit" size="sm" :disabled="adjustForm.processing || adjustForm.quantity === ''">Save</Button>
                            </form>
                        </PaneSection>

                        <PaneSection title="Prices">
                            <PaneFacts
                                :facts="[
                                    {
                                        label: `Cost per ${chemical.unit}`,
                                        value: chemical.cost_per_unit !== null ? formatMoney(chemical.cost_per_unit) : null,
                                    },
                                    {
                                        label: `Sell price per ${chemical.unit}`,
                                        value: chemical.sell_price !== null ? formatMoney(chemical.sell_price) : null,
                                    },
                                    { label: 'Value on the shelf', value: chemical.value !== null ? formatMoney(chemical.value) : null },
                                    { label: 'Supplier', value: chemical.supplier },
                                ]"
                            />
                        </PaneSection>

                        <PaneSection v-if="chemical.transactions.length" title="Recent changes">
                            <PaneList>
                                <PaneItem v-for="t in chemical.transactions" :key="t.id" :title="movement(t, chemical.unit)">
                                    <template #sub>
                                        {{ formatDay(t.on)
                                        }}<template v-if="t.agent">
                                            by
                                            <Link
                                                v-if="t.agent_id"
                                                :href="agentLink(t.agent_id)"
                                                class="text-primary underline-offset-4 hover:underline"
                                                >{{ t.agent }}</Link
                                            ><template v-else>{{ t.agent }}</template></template
                                        >
                                    </template>
                                </PaneItem>
                            </PaneList>
                        </PaneSection>
                    </div>
                </template>
            </MasterDetail>
        </div>
    </AppLayout>
</template>
