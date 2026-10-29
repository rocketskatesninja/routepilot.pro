<script setup lang="ts">
import EntityAvatar from '@/components/EntityAvatar.vue';
import Hint from '@/components/Hint.vue';
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
import { agentLink, poolLink, reportLink } from '@/lib/links';
import { visitQuery } from '@/lib/listQuery';
import { formatAmount, formatDay, formatMoney, unitFor } from '@/lib/utils';
import { type BreadcrumbItem } from '@/types';
import { type Paginated } from '@/types/pagination';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { Archive, FlaskConical, Pencil, Plus, RotateCcw, Wrench } from 'lucide-vue-next';
import { computed, ref } from 'vue';

interface InventoryRow {
    id: number;
    name: string;
    part_number: string | null;
    photo_url: string | null;
    unit: string;
    stock: number;
    low: boolean;
    cost_per_unit: number | null;
}

interface InventoryDetail {
    id: number;
    name: string;
    kind: 'chemical' | 'part';
    part_number: string | null;
    photo_url: string | null;
    unit: string;
    stock: number;
    reorder_threshold: number | null;
    cost_per_unit: number | null;
    sell_price: number | null;
    supplier: string | null;
    is_active: boolean;
    unit_locked: boolean; // it has stock or history, so its unit can't change
    value: number | null;
    low: boolean;
    /** Newest first; `change` is signed: + onto the shelf, − off it. */
    // by: the tech on a visit, else who updated it in the office. pool/report_id: usage from a visit (pool_id null once the pool's archived).
    transactions: {
        id: number;
        type: string;
        change: number;
        on: string | null;
        by: string | null;
        agent_id: number | null;
        pool: string | null;
        pool_id: number | null;
        report_id: number | null;
    }[];
}

const props = defineProps<{
    items: Paginated<InventoryRow>;
    selected: InventoryDetail | null;
    filters: { search: string; archived: boolean; type: 'chemicals' | 'parts' };
    counts: { active: number; archived: number; chemicals: number; parts: number };
    sort: { key: string; dir: string };
    canManage: boolean;
}>();

const breadcrumbs: BreadcrumbItem[] = [{ title: 'Inventory', href: '/inventory' }];

const { search } = useListSearch(props.filters.search);

const open = (id: number) => visitQuery({ search: search.value || undefined, selected: id });
const deselect = () => visitQuery({ search: search.value || undefined, selected: undefined });
// Chemicals and repair parts are listed apart; switching resets selection, page and Archived.
const parts = computed(() => props.filters.type === 'parts');
const types = computed(() => [
    { type: 'chemicals', label: 'Chemicals', count: props.counts.chemicals },
    { type: 'parts', label: 'Parts', count: props.counts.parts },
]);
const showType = (type: string) => visitQuery({ type, archived: undefined, selected: undefined, page: undefined }, { replace: true });
const word = computed(() => (parts.value ? 'part' : 'chemical'));
// Archived ones have their own tab; switching keeps the search, resets selection and page.
const tabs = computed(() => [
    { archived: false, label: 'Active', count: props.counts.active },
    { archived: true, label: 'Archived', count: props.counts.archived },
]);
const showArchived = (archived: boolean) =>
    visitQuery({ archived: archived ? 1 : undefined, selected: undefined, page: undefined }, { replace: true });

// --- the side panel ---
const chemical = computed(() => props.selected);
/** "Restocked 10 gal" / "Used 2 gal" / "Counted, 3 gal less". */
function movement(t: InventoryDetail['transactions'][number], unit: string): string {
    const size = formatAmount(Math.abs(t.change), unit);
    if (t.type === 'restock') return `Restocked ${size}`;
    // Stock never goes below zero, so usage from an empty shelf moves nothing: it's tagged "None in stock" instead.
    if (t.type === 'usage') return t.change === 0 ? 'Used' : `Used ${size}`;
    return t.change === 0 ? 'Counted, no change' : `Counted, ${size} ${t.change > 0 ? 'more' : 'less'}`;
}

// The detail pane is shared: it hosts the create/edit form when one is open,
// otherwise the selected item's detail. Closing the pane cancels the form first.
function closePane() {
    if (formOpen.value) {
        formOpen.value = false;
    } else {
        deselect();
    }
}

// --- create / edit chemical ---
const formOpen = ref(false);
const formMode = ref<'create' | 'edit'>('create');
const formId = ref<number | null>(null);
const form = useForm<{
    chemical_name: string;
    kind: 'chemical' | 'part';
    part_number: string;
    unit: string;
    current_stock: string;
    reorder_threshold: string;
    cost_per_unit: string;
    sell_price: string;
    supplier: string;
    photo: File | null;
}>({
    chemical_name: '',
    kind: 'chemical',
    part_number: '',
    unit: 'gal',
    current_stock: '0',
    reorder_threshold: '',
    cost_per_unit: '',
    sell_price: '',
    supplier: '',
    photo: null,
});

function openCreate() {
    form.reset();
    // A part is counted in units (the server holds it to that too).
    form.kind = parts.value ? 'part' : 'chemical';
    form.unit = parts.value ? 'unit' : 'gal';
    form.clearErrors();
    formMode.value = 'create';
    formId.value = null;
    formOpen.value = true;
}

const text = (n: number | null) => (n !== null ? String(n) : '');

function openEdit() {
    if (!props.selected) return;
    const c = props.selected;
    form.chemical_name = c.name;
    form.kind = c.kind;
    form.part_number = c.part_number ?? '';
    form.unit = c.unit;
    form.reorder_threshold = text(c.reorder_threshold);
    form.cost_per_unit = text(c.cost_per_unit);
    form.sell_price = text(c.sell_price);
    form.supplier = c.supplier ?? '';
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

// Archiving keeps the pane open on it, so Restore is right there as an undo.
function archiveChemical() {
    if (!props.selected) return;
    if (
        !confirm(
            `Archive this ${word.value}? It leaves the list and visits stop taking it from stock. Its history is kept, and you can restore it from the Archived tab.`,
        )
    )
        return;
    router.post(`/inventory/${props.selected.id}/archive`, {}, { preserveScroll: true });
}

function restoreChemical() {
    if (props.selected) router.post(`/inventory/${props.selected.id}/restore`, {}, { preserveScroll: true });
}

// --- update stock (inline in the detail pane) ---
const adjustForm = useForm({ type: 'restock', quantity: '' });
function submitAdjust() {
    if (!props.selected || adjustForm.quantity === '') return;
    adjustForm.post(`/inventory/${props.selected.id}/adjust`, { preserveScroll: true, onSuccess: () => adjustForm.reset() });
}
</script>

<template>
    <Head title="Inventory" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <template #filters>
            <button
                v-for="t in types"
                :key="t.type"
                class="shrink-0 rounded-md px-2.5 py-1 text-sm font-medium whitespace-nowrap transition-colors"
                :class="props.filters.type === t.type ? 'bg-primary text-primary-foreground' : 'text-muted-foreground hover:bg-muted'"
                @click="showType(t.type)"
            >
                {{ t.label }} <span class="opacity-70">{{ t.count }}</span>
            </button>
            <span class="bg-border mx-1 h-5 w-px shrink-0" aria-hidden="true" />
            <button
                v-for="t in tabs"
                :key="t.label"
                class="shrink-0 rounded-md px-2.5 py-1 text-sm font-medium whitespace-nowrap transition-colors"
                :class="props.filters.archived === t.archived ? 'bg-muted text-foreground' : 'text-muted-foreground hover:bg-muted'"
                @click="showArchived(t.archived)"
            >
                {{ t.label }} <span class="opacity-70">{{ t.count }}</span>
            </button>
        </template>

        <template #actions>
            <Input
                v-model="search"
                type="search"
                :placeholder="parts ? 'Search parts or part #…' : 'Search chemicals…'"
                class="h-9 min-w-0 flex-1 sm:w-44 sm:flex-none lg:w-56"
            />
            <Button v-if="props.canManage" size="sm" :title="`New ${word}`" @click="openCreate">
                <Plus class="size-4 sm:mr-1" /><span class="hidden sm:inline">{{ parts ? 'Part' : 'Chemical' }}</span>
            </Button>
        </template>

        <div class="flex h-full flex-1 flex-col gap-4 p-4">
            <MasterDetail
                :has-selection="formOpen || props.selected !== null"
                :selection-key="formOpen ? `form-${formMode}` : (props.selected?.id ?? null)"
                :empty-text="`Select a ${word} to see details.`"
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
                            <SortableTh sort-key="name" :active="props.sort">{{ parts ? 'Part' : 'Chemical' }}</SortableTh>
                            <SortableTh sort-key="stock" :active="props.sort">In stock</SortableTh>
                            <th class="px-4 py-2 font-medium">Status</th>
                            <SortableTh sort-key="cost" :active="props.sort" class="hidden md:table-cell">Cost</SortableTh>
                        </template>
                        <template #row="{ item }">
                            <td class="px-4 py-2.5">
                                <div class="flex items-center gap-2.5">
                                    <EntityAvatar :src="item.photo_url" :type="parts ? 'part' : 'inventory'" :name="item.name" size="sm" />
                                    <span class="min-w-0">
                                        <span class="font-medium">{{ item.name }}</span>
                                        <span v-if="item.part_number" class="text-muted-foreground ml-1.5 text-xs">#{{ item.part_number }}</span>
                                    </span>
                                </div>
                            </td>
                            <td class="text-muted-foreground px-4 py-2.5">{{ formatAmount(item.stock, item.unit) }}</td>
                            <td class="px-4 py-2.5">
                                <Chip v-if="props.filters.archived" tone="muted">Archived</Chip>
                                <Chip v-else :tone="item.low ? 'warn' : 'good'">{{ item.low ? 'Running low' : 'In stock' }}</Chip>
                            </td>
                            <td class="text-muted-foreground hidden px-4 py-2.5 md:table-cell">
                                {{ item.cost_per_unit !== null ? `${formatMoney(item.cost_per_unit)}/${unitFor(1, item.unit)}` : '—' }}
                            </td>
                        </template>
                        <template #empty>
                            <component :is="parts ? Wrench : FlaskConical" class="mx-auto mb-2 size-6 opacity-50" />
                            {{
                                props.filters.archived
                                    ? `No archived ${word}s.`
                                    : parts
                                      ? 'No parts yet. Add the repair parts you keep on hand.'
                                      : 'No chemicals yet.'
                            }}
                        </template>
                    </ListTable>
                </template>

                <template #detail>
                    <!-- create / edit form: hosted in the docked pane, not an overlay -->
                    <form v-if="formOpen" class="space-y-4 text-sm" @submit.prevent="submitForm">
                        <PaneHeader
                            :title="`${formMode === 'create' ? 'New' : 'Edit'} ${form.kind === 'part' ? 'part' : 'chemical'}`"
                            summary="Change how much you have with Update stock, so every change is logged."
                        />
                        <ImageUpload
                            :model-value="form.photo"
                            :current="formMode === 'edit' ? (props.selected?.photo_url ?? null) : null"
                            :error="form.errors.photo"
                            @update:model-value="(f) => (form.photo = f)"
                        />
                        <div class="grid grid-cols-3 gap-3">
                            <div class="col-span-2 grid gap-1.5">
                                <Label for="cname">Name</Label>
                                <Input id="cname" v-model="form.chemical_name" />
                                <p v-if="form.errors.chemical_name" class="text-xs text-red-600">{{ form.errors.chemical_name }}</p>
                            </div>
                            <!-- A part has its part number here; it's always counted in units. -->
                            <div v-if="form.kind === 'part'" class="grid gap-1.5">
                                <Label for="cpart">Part number</Label>
                                <Input id="cpart" v-model="form.part_number" placeholder="Optional" />
                                <p v-if="form.errors.part_number" class="text-xs text-red-600">{{ form.errors.part_number }}</p>
                            </div>
                            <div v-else class="grid gap-1.5">
                                <!-- One unit per chemical: liquids by the gallon, dry chemicals by the pound. -->
                                <Label for="cunit">Unit</Label>
                                <select
                                    id="cunit"
                                    v-model="form.unit"
                                    :disabled="formMode === 'edit' && props.selected?.unit_locked"
                                    :title="
                                        formMode === 'edit' && props.selected?.unit_locked ? 'Can’t change once it has stock or history' : undefined
                                    "
                                    class="border-input bg-background h-9 rounded-md border px-2 text-sm disabled:opacity-60"
                                >
                                    <option value="gal">gal</option>
                                    <option value="lbs">lbs</option>
                                    <option value="bag">bag (40 lb)</option>
                                    <option value="puck">puck (3″ tablet)</option>
                                    <option value="tab">tab (1″ tablet)</option>
                                </select>
                            </div>
                        </div>
                        <div class="grid grid-cols-2 gap-3">
                            <div v-if="formMode === 'create'" class="grid gap-1.5">
                                <Label for="cstock">Starting stock</Label
                                ><Input id="cstock" v-model="form.current_stock" type="number" min="0" step="0.01" />
                            </div>
                            <div class="grid gap-1.5">
                                <Label for="creorder">Reorder at</Label
                                ><Input id="creorder" v-model="form.reorder_threshold" type="number" min="0" step="0.01" />
                            </div>
                        </div>
                        <div class="grid grid-cols-2 gap-3">
                            <div class="grid gap-1.5">
                                <!-- Both are per the chemical's own unit, named as it's picked above, so a price can't be read as per bag. -->
                                <Label for="ccost">Cost per {{ unitFor(1, form.unit) }} ($)</Label
                                ><Input id="ccost" v-model="form.cost_per_unit" type="number" min="0" step="0.01" />
                            </div>
                            <div class="grid gap-1.5">
                                <Label for="csell">Sell price per {{ unitFor(1, form.unit) }} ($)</Label
                                ><Input id="csell" v-model="form.sell_price" type="number" min="0" step="0.01" />
                            </div>
                        </div>
                        <div class="grid gap-1.5"><Label for="csup">Supplier</Label><Input id="csup" v-model="form.supplier" /></div>
                        <div class="flex justify-end gap-2 pt-2">
                            <Button type="button" variant="outline" @click="formOpen = false">Cancel</Button>
                            <Button type="submit" :disabled="form.processing">{{ formMode === 'create' ? `Add ${form.kind}` : 'Save' }}</Button>
                        </div>
                    </form>

                    <div v-else-if="chemical" class="space-y-6">
                        <PaneHeader
                            :title="chemical.name"
                            :summary="`${chemical.part_number ? `Part #${chemical.part_number}. ` : ''}${formatAmount(chemical.stock, chemical.unit)} in stock.${chemical.reorder_threshold !== null ? ` Reorder at ${formatAmount(chemical.reorder_threshold, chemical.unit)}.` : ''}`"
                        >
                            <template #media>
                                <img
                                    v-if="chemical.photo_url"
                                    :src="chemical.photo_url"
                                    :alt="chemical.name"
                                    class="size-16 shrink-0 rounded-2xl object-cover"
                                />
                                <IconTile v-else :icon="chemical.kind === 'part' ? Wrench : FlaskConical" size="lg" />
                            </template>
                            <template #badge>
                                <Chip v-if="!chemical.is_active" tone="muted">Archived</Chip>
                                <Chip v-else :tone="chemical.low ? 'warn' : 'good'">{{ chemical.low ? 'Running low' : 'In stock' }}</Chip>
                            </template>
                            <template v-if="props.canManage && chemical.is_active" #actions>
                                <Button size="sm" variant="outline" @click="openEdit"><Pencil class="mr-1 size-3.5" /> Edit</Button>
                                <Hint
                                    text="Takes it off the list, and visits stop taking it from stock. Its history stays, and you can restore it from the Archived tab."
                                >
                                    <Button size="sm" variant="outline" @click="archiveChemical"><Archive class="mr-1 size-3.5" /> Archive</Button>
                                </Hint>
                            </template>
                        </PaneHeader>

                        <div v-if="!chemical.is_active" class="rounded-2xl border border-amber-500/30 bg-amber-500/10 p-4 text-sm">
                            <p class="font-medium">Archived.</p>
                            <p class="text-muted-foreground mt-0.5">It's off the list, and visits don't take it from stock. Its history is kept.</p>
                            <Button v-if="props.canManage" size="sm" variant="outline" class="mt-3" @click="restoreChemical"
                                ><RotateCcw class="mr-1 size-3.5" /> Restore</Button
                            >
                        </div>

                        <PaneSection v-if="props.canManage && chemical.is_active" title="Update stock">
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
                                <span class="text-muted-foreground text-sm">{{ unitFor(Number(adjustForm.quantity), chemical.unit) }}</span>
                                <Button type="submit" size="sm" :disabled="adjustForm.processing || adjustForm.quantity === ''">Save</Button>
                            </form>
                        </PaneSection>

                        <PaneSection v-if="chemical.cost_per_unit !== null || chemical.sell_price !== null || chemical.supplier" title="Prices">
                            <PaneFacts
                                :facts="[
                                    {
                                        label: `Cost per ${unitFor(1, chemical.unit)}`,
                                        value: chemical.cost_per_unit !== null ? formatMoney(chemical.cost_per_unit) : null,
                                    },
                                    {
                                        label: `Sell price per ${unitFor(1, chemical.unit)}`,
                                        value: chemical.sell_price !== null ? formatMoney(chemical.sell_price) : null,
                                    },
                                    { label: 'Value on the shelf', value: chemical.value !== null ? formatMoney(chemical.value) : null },
                                    { label: 'Supplier', value: chemical.supplier },
                                ]"
                            />
                        </PaneSection>

                        <PaneSection v-if="chemical.transactions.length" title="Recent changes">
                            <PaneList>
                                <PaneItem v-for="t in chemical.transactions" :key="t.id">
                                    <template #title>
                                        {{ movement(t, chemical.unit)
                                        }}<template v-if="t.pool">
                                            at
                                            <Link
                                                v-if="t.pool_id"
                                                :href="poolLink(t.pool_id)"
                                                class="text-primary underline-offset-4 hover:underline"
                                                >{{ t.pool }}</Link
                                            ><template v-else>{{ t.pool }}</template></template
                                        >
                                        <Chip v-if="t.type === 'usage' && t.change === 0" tone="warn" class="ml-1.5">None in stock</Chip>
                                    </template>
                                    <template #sub>
                                        {{ formatDay(t.on)
                                        }}<template v-if="t.by">
                                            by
                                            <Link
                                                v-if="t.agent_id"
                                                :href="agentLink(t.agent_id)"
                                                class="text-primary underline-offset-4 hover:underline"
                                                >{{ t.by }}</Link
                                            ><template v-else>{{ t.by }}</template></template
                                        >
                                    </template>
                                    <template v-if="t.report_id" #trailing>
                                        <Link
                                            :href="reportLink(t.report_id)"
                                            class="text-primary text-sm font-medium underline-offset-4 hover:underline"
                                            >View report</Link
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
