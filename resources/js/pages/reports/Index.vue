<script setup lang="ts">
import Sparkline from '@/components/charts/Sparkline.vue';
import EntityAvatar from '@/components/EntityAvatar.vue';
import ListTable from '@/components/ListTable.vue';
import MasterDetail from '@/components/MasterDetail.vue';
import NoteBubble from '@/components/NoteBubble.vue';
import Chip from '@/components/pane/Chip.vue';
import IconTile from '@/components/pane/IconTile.vue';
import PaneFacts from '@/components/pane/PaneFacts.vue';
import PaneHeader from '@/components/pane/PaneHeader.vue';
import PaneItem from '@/components/pane/PaneItem.vue';
import PaneList from '@/components/pane/PaneList.vue';
import PaneSection from '@/components/pane/PaneSection.vue';
import WaterChecks from '@/components/portal/WaterChecks.vue';
import SortableTh from '@/components/SortableTh.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/AppLayout.vue';
import { targetsFor } from '@/lib/chemistry';
import { agentLink, customerLink, workOrderLink } from '@/lib/links';
import { visitQuery } from '@/lib/listQuery';
import { checkWater, waterSummary, type WaterTargets } from '@/lib/portal';
import { formatAmount, formatDay, formatMoney } from '@/lib/utils';
import { type BreadcrumbItem } from '@/types';
import { type Paginated } from '@/types/pagination';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { Check, FileText, Lock, Minus, Pencil, Plus, Trash2, X } from 'lucide-vue-next';
import { computed, ref } from 'vue';

interface VisitRow {
    id: number;
    completed_on: string | null;
    pool: string | null;
    pool_photo: string | null;
    customer: string | null;
    customer_photo: string | null;
    agent: string | null;
    agent_photo: string | null;
}

interface Reading {
    free_chlorine: number | null;
    total_chlorine: number | null;
    ph: number | null;
    alkalinity: number | null;
    calcium_hardness: number | null;
    cyanuric_acid: number | null;
    salt: number | null;
    water_temperature: number | null;
    lsi_score: number | null;
}

interface VisitDetail {
    id: number;
    pool: string | null;
    service: string | null;
    targets: WaterTargets;
    customer: string | null;
    customer_id: number | null;
    agent: string | null;
    agent_id: number | null;
    completed_on: string | null;
    notes: string | null;
    office_notes: string | null;
    work_order: { id: number; title: string; details: string | null; urgent: boolean; status: string } | null;
    reading: Reading | null;
    // chemical_id null: typed before the chemical list (kept as written, not billed)
    treatments: { chemical_id: number | null; name: string; amount: number; unit: string }[];
    tasks: { name: string; done: boolean }[];
    photos: string[];
    trend: { chlorine: number[]; ph: number[]; dates: string[] };
    can_edit?: boolean;
    can_delete?: boolean;
    charge?: { amount: number; invoice: string | null } | null;
    chemicals_billed?: boolean; // its chemicals are already on the customer's bill
}

const props = defineProps<{
    visits: Paginated<VisitRow>;
    selected: VisitDetail | null;
    chemicals: { id: number; name: string; unit: string }[]; // what the edit form picks from
    sort: { key: string; dir: string };
}>();

const breadcrumbs: BreadcrumbItem[] = [{ title: 'Reports', href: '/reports' }];

const open = (id: number) => visitQuery({ selected: id });
const closeDrawer = () => visitQuery({ selected: undefined });

const readingLabels: Record<keyof Reading, string> = {
    free_chlorine: 'Free Cl',
    total_chlorine: 'Total Cl',
    ph: 'pH',
    alkalinity: 'Alkalinity',
    calcium_hardness: 'Calcium',
    cyanuric_acid: 'CYA',
    salt: 'Salt',
    water_temperature: 'Temp °F',
    lsi_score: 'LSI',
};

// --- the side panel ---
const visit = computed(() => props.selected);
const waterChecks = computed(() => (visit.value?.reading ? checkWater(visit.value.reading, visit.value.targets) : []));
// The readings beside the checks. A 0 is a reading; only a box left blank (null) is left out.
const waterFacts = computed(() => {
    const r = visit.value?.reading;
    if (!r) return [];
    return [
        { label: 'Water temperature', value: r.water_temperature !== null ? `${r.water_temperature}°F` : null },
        { label: 'Total chlorine', value: r.total_chlorine !== null ? `${r.total_chlorine} ppm` : null },
        { label: 'Saturation (LSI)', value: r.lsi_score },
    ].filter((f) => f.value !== null);
});
const firstWord = (name: string | null) => name?.split(' ')[0] ?? null;
/** "Weekly Pool Service on Thu, Oct 15." */
const visitSummary = (v: VisitDetail) => `${v.service ?? 'Service visit'}${v.completed_on ? ` on ${formatDay(v.completed_on)}` : ''}.`;
// The trend charts shade this pool's own healthy range.
const band = (key: 'free_chlorine' | 'ph'): [number, number] => {
    const r = targetsFor({ custom_target_ranges: visit.value?.targets ?? null })[key];
    return [r.min, r.max];
};

// --- edit ---
const editReadingKeys = [
    'free_chlorine',
    'total_chlorine',
    'ph',
    'alkalinity',
    'calcium_hardness',
    'cyanuric_acid',
    'salt',
    'water_temperature',
] as const;

const editing = ref(false);
const form = useForm<{
    completed_on: string;
    notes: string;
    office_notes: string;
    reading: Record<string, number | string | null>;
    treatments: { chemical_id: number | null; name: string; amount: number | string; unit: string }[];
    tasks: { name: string; done: boolean }[];
}>({
    completed_on: '',
    notes: '',
    office_notes: '',
    reading: {},
    treatments: [],
    tasks: [],
});

function openEdit() {
    const s = props.selected;
    if (!s) return;
    form.completed_on = s.completed_on ?? '';
    form.notes = s.notes ?? '';
    form.office_notes = s.office_notes ?? '';
    form.reading = Object.fromEntries(editReadingKeys.map((k) => [k, s.reading?.[k] ?? null]));
    form.treatments = s.treatments.map((t) => ({ ...t }));
    form.tasks = s.tasks.map((t) => ({ ...t }));
    form.clearErrors();
    editing.value = true;
}

/** Delete the report after saying exactly what goes with it (its charge, stock, the stop). */
function deleteReport() {
    const v = props.selected;
    if (!v) return;
    const charge = v.charge
        ? ` The ${formatMoney(v.charge.amount)} it was billed is cancelled${v.charge.invoice ? ` and ${v.charge.invoice} updates` : ''}.`
        : '';
    const ok = confirm(
        `Delete this report?${charge} Its readings, photos and notes are removed, chemicals it used go back into inventory, and the stop opens again. This can't be undone.`,
    );
    if (ok) router.delete(`/reports/${v.id}`, { preserveScroll: true });
}

function submitEdit() {
    if (!props.selected) return;
    form.patch(`/reports/${props.selected.id}`, {
        preserveScroll: true,
        onSuccess: () => (editing.value = false),
    });
}
</script>

<template>
    <Head title="Reports" />

    <AppLayout :breadcrumbs="breadcrumbs" :meta="`${props.visits.total} completed visits`">
        <div class="flex h-full flex-1 flex-col gap-4 p-4">
            <MasterDetail
                :has-selection="props.selected !== null"
                :selection-key="props.selected?.id ?? null"
                empty-text="Select a visit to see details."
                @close="closeDrawer"
            >
                <template #list>
                    <ListTable
                        :meta="props.visits"
                        :columns="4"
                        :row-key="(v) => v.id"
                        :selected-key="props.selected?.id ?? null"
                        @select="(v) => open(v.id)"
                    >
                        <template #head>
                            <SortableTh sort-key="date" :active="props.sort">Date</SortableTh>
                            <SortableTh sort-key="pool" :active="props.sort">Pool</SortableTh>
                            <SortableTh sort-key="customer" :active="props.sort" class="hidden md:table-cell">Customer</SortableTh>
                            <SortableTh sort-key="agent" :active="props.sort" class="hidden lg:table-cell">Agent</SortableTh>
                        </template>
                        <template #row="{ item }">
                            <td class="px-4 py-2.5 font-medium">{{ item.completed_on }}</td>
                            <td class="px-4 py-2.5">
                                <div class="flex items-center gap-2">
                                    <EntityAvatar :src="item.pool_photo" type="pool" :name="item.pool" size="sm" />
                                    <span>{{ item.pool }}</span>
                                </div>
                            </td>
                            <td class="text-muted-foreground hidden px-4 py-2.5 md:table-cell">
                                <div class="flex items-center gap-2">
                                    <EntityAvatar :src="item.customer_photo" type="person" :name="item.customer" size="sm" shape="circle" />
                                    <span>{{ item.customer }}</span>
                                </div>
                            </td>
                            <td class="text-muted-foreground hidden px-4 py-2.5 lg:table-cell">
                                <div class="flex items-center gap-2">
                                    <EntityAvatar :src="item.agent_photo" type="person" :name="item.agent" size="sm" shape="circle" />
                                    <span>{{ item.agent }}</span>
                                </div>
                            </td>
                        </template>
                        <template #empty>
                            <FileText class="mx-auto mb-2 size-6 opacity-50" />
                            No completed visits yet.
                        </template>
                    </ListTable>
                </template>

                <template #detail>
                    <div v-if="visit" class="space-y-6">
                        <PaneHeader :title="visit.pool ?? 'Visit'" :summary="visitSummary(visit)">
                            <template #media><IconTile :icon="FileText" size="lg" /></template>
                            <p class="mt-2 text-[15px]">
                                <template v-if="visit.customer_id"
                                    >For
                                    <Link
                                        :href="customerLink(visit.customer_id)"
                                        class="text-primary font-medium underline-offset-4 hover:underline"
                                        >{{ visit.customer }}</Link
                                    ></template
                                ><template v-if="visit.agent"
                                    >{{ visit.customer_id ? ', by ' : 'By ' }}
                                    <Link
                                        v-if="visit.agent_id"
                                        :href="agentLink(visit.agent_id)"
                                        class="text-primary font-medium underline-offset-4 hover:underline"
                                        >{{ visit.agent }}</Link
                                    ><template v-else>{{ visit.agent }}</template></template
                                >
                            </p>
                            <template v-if="(visit.can_edit || visit.can_delete) && !editing" #actions>
                                <Button v-if="visit.can_edit" size="sm" variant="outline" @click="openEdit"
                                    ><Pencil class="mr-1 size-3.5" /> Edit</Button
                                >
                                <Button
                                    v-if="visit.can_delete"
                                    size="sm"
                                    variant="outline"
                                    class="text-red-600 hover:text-red-600 dark:text-red-400"
                                    @click="deleteReport"
                                    ><Trash2 class="mr-1 size-3.5" /> Delete</Button
                                >
                            </template>
                        </PaneHeader>

                        <!-- edit form -->
                        <form v-if="editing" class="space-y-4 text-sm" @submit.prevent="submitEdit">
                            <div class="grid gap-1.5">
                                <Label for="r_date">Date</Label>
                                <Input id="r_date" v-model="form.completed_on" type="date" />
                            </div>
                            <div>
                                <span class="text-muted-foreground text-xs font-medium">Readings</span>
                                <div class="mt-1 grid grid-cols-3 gap-2">
                                    <div v-for="k in editReadingKeys" :key="k" class="grid gap-1">
                                        <Label :for="`r_${k}`" class="text-xs">{{ readingLabels[k] }}</Label>
                                        <Input :id="`r_${k}`" v-model="form.reading[k]" type="number" step="any" />
                                    </div>
                                </div>
                            </div>
                            <div>
                                <div class="mb-1 flex items-center justify-between">
                                    <span class="text-muted-foreground text-xs font-medium">Chemicals</span>
                                    <Button
                                        v-if="props.chemicals.length"
                                        type="button"
                                        size="sm"
                                        variant="outline"
                                        @click="form.treatments.push({ chemical_id: null, name: '', amount: '', unit: '' })"
                                    >
                                        <Plus class="mr-1 size-3.5" /> Add
                                    </Button>
                                </div>
                                <p v-if="visit.chemicals_billed" class="text-muted-foreground mb-1.5 text-xs">
                                    These are already on the customer's bill. Changes here update stock, not the bill; adjust it on Balances.
                                </p>
                                <div class="space-y-1.5">
                                    <div v-for="(t, i) in form.treatments" :key="i" class="flex items-center gap-2">
                                        <!-- typed before the chemical list: kept as written -->
                                        <span v-if="t.chemical_id === null && t.name" class="flex-1 truncate text-sm"
                                            >{{ t.name }} {{ formatAmount(Number(t.amount), t.unit) }}</span
                                        >
                                        <template v-else>
                                            <select
                                                v-model="t.chemical_id"
                                                aria-label="Chemical"
                                                class="border-input bg-background h-9 min-w-0 flex-1 rounded-md border px-2 text-sm"
                                            >
                                                <option :value="null" disabled>Pick a chemical</option>
                                                <option v-for="c in props.chemicals" :key="c.id" :value="c.id">{{ c.name }}</option>
                                            </select>
                                            <Input
                                                v-model="t.amount"
                                                type="number"
                                                min="1"
                                                step="1"
                                                placeholder="Amt"
                                                aria-label="Amount"
                                                class="w-16"
                                            />
                                            <span class="text-muted-foreground w-7 text-sm">{{
                                                props.chemicals.find((c) => c.id === t.chemical_id)?.unit
                                            }}</span>
                                        </template>
                                        <Button type="button" size="icon" variant="outline" aria-label="Remove" @click="form.treatments.splice(i, 1)"
                                            ><X class="size-4"
                                        /></Button>
                                    </div>
                                </div>
                            </div>
                            <div>
                                <div class="mb-1 flex items-center justify-between">
                                    <span class="text-muted-foreground text-xs font-medium">Tasks</span>
                                    <Button type="button" size="sm" variant="outline" @click="form.tasks.push({ name: '', done: false })">
                                        <Plus class="mr-1 size-3.5" /> Add
                                    </Button>
                                </div>
                                <div class="space-y-1.5">
                                    <div v-for="(t, i) in form.tasks" :key="i" class="flex items-center gap-2">
                                        <input v-model="t.done" type="checkbox" />
                                        <Input v-model="t.name" placeholder="Task" class="flex-1" />
                                        <Button type="button" size="icon" variant="outline" @click="form.tasks.splice(i, 1)"
                                            ><X class="size-4"
                                        /></Button>
                                    </div>
                                </div>
                            </div>
                            <div class="grid gap-1.5">
                                <Label for="r_notes">Note to the homeowner</Label>
                                <textarea
                                    id="r_notes"
                                    v-model="form.notes"
                                    rows="3"
                                    class="border-input bg-background rounded-md border px-3 py-2 text-sm"
                                ></textarea>
                            </div>
                            <div class="grid gap-1.5">
                                <Label for="r_office_notes"
                                    >Office note <span class="text-muted-foreground font-normal">(only your team sees this)</span></Label
                                >
                                <textarea
                                    id="r_office_notes"
                                    v-model="form.office_notes"
                                    rows="3"
                                    class="border-input bg-background rounded-md border px-3 py-2 text-sm"
                                ></textarea>
                            </div>
                            <div class="flex justify-end gap-2 pt-2">
                                <Button type="button" variant="outline" @click="editing = false">Cancel</Button>
                                <Button type="submit" :disabled="form.processing">Save</Button>
                            </div>
                        </form>

                        <template v-else>
                            <PaneSection v-if="visit.tasks.length" title="What was done">
                                <ul class="grid gap-x-6 gap-y-1.5 text-sm sm:grid-cols-2">
                                    <li v-for="(t, i) in visit.tasks" :key="i" class="flex gap-2" :class="t.done ? '' : 'text-muted-foreground'">
                                        <Check v-if="t.done" class="text-primary mt-0.5 size-4 shrink-0" />
                                        <Minus v-else class="mt-0.5 size-4 shrink-0" />
                                        {{ t.name }}<template v-if="!t.done"> (skipped)</template>
                                    </li>
                                </ul>
                            </PaneSection>

                            <!-- shown when anything was measured, even just the temperature -->
                            <PaneSection v-if="waterChecks.length || waterFacts.length" title="Water test">
                                <template v-if="waterChecks.length">
                                    <p class="text-muted-foreground mb-3 text-sm">{{ waterSummary(waterChecks, false) }}</p>
                                    <div class="border-border rounded-2xl border px-4">
                                        <WaterChecks :checks="waterChecks" :explain="false" />
                                    </div>
                                </template>
                                <PaneFacts v-if="waterFacts.length" :class="{ 'mt-3': waterChecks.length }" :facts="waterFacts" />
                            </PaneSection>

                            <PaneSection v-if="visit.treatments.length" title="Chemicals added">
                                <PaneList>
                                    <PaneItem v-for="(t, i) in visit.treatments" :key="i" :title="t.name">
                                        <template #trailing
                                            ><span class="tabular-nums">{{ formatAmount(t.amount, t.unit) }}</span></template
                                        >
                                    </PaneItem>
                                </PaneList>
                            </PaneSection>

                            <PaneSection v-if="visit.notes" title="Note to the homeowner">
                                <NoteBubble :author="`${firstWord(visit.agent) ?? 'The tech'} wrote`">{{ visit.notes }}</NoteBubble>
                            </PaneSection>

                            <PaneSection v-if="visit.office_notes" title="Office note">
                                <template #action>
                                    <span class="text-muted-foreground flex items-center gap-1 text-sm"
                                        ><Lock class="size-3.5" /> Only your team sees this</span
                                    >
                                </template>
                                <NoteBubble :author="`${firstWord(visit.agent) ?? 'The tech'} wrote`">{{ visit.office_notes }}</NoteBubble>
                            </PaneSection>

                            <PaneSection v-if="visit.work_order" title="Work order">
                                <template #action>
                                    <span class="flex items-center gap-1.5">
                                        <Chip v-if="visit.work_order.urgent" tone="bad">Urgent</Chip>
                                        <Chip :tone="visit.work_order.status === 'done' ? 'good' : 'info'">{{
                                            visit.work_order.status === 'done' ? 'Done' : 'Open'
                                        }}</Chip>
                                    </span>
                                </template>
                                <p class="font-medium">{{ visit.work_order.title }}</p>
                                <p v-if="visit.work_order.details" class="text-muted-foreground mt-1 text-sm whitespace-pre-line">
                                    {{ visit.work_order.details }}
                                </p>
                                <Link
                                    :href="workOrderLink(visit.work_order.id)"
                                    class="text-primary mt-2 inline-block text-sm underline-offset-4 hover:underline"
                                    >Open the work order</Link
                                >
                            </PaneSection>

                            <PaneSection v-if="visit.photos.length" title="Photos">
                                <div class="grid grid-cols-3 gap-2">
                                    <a
                                        v-for="(photo, i) in visit.photos"
                                        :key="i"
                                        :href="photo"
                                        target="_blank"
                                        rel="noopener"
                                        class="focus-visible:ring-ring overflow-hidden rounded-xl focus-visible:ring-2 focus-visible:outline-none"
                                    >
                                        <img
                                            :src="photo"
                                            :alt="`Photo ${i + 1} from this visit`"
                                            class="aspect-square w-full object-cover transition hover:opacity-90"
                                        />
                                    </a>
                                </div>
                            </PaneSection>

                            <PaneSection v-if="visit.trend && (visit.trend.chlorine.length > 1 || visit.trend.ph.length > 1)" title="Recent visits">
                                <div class="grid grid-cols-2 gap-3">
                                    <figure v-if="visit.trend.chlorine.length > 1" class="border-border rounded-2xl border p-3">
                                        <figcaption class="text-sm font-medium">Chlorine</figcaption>
                                        <Sparkline
                                            :values="visit.trend.chlorine"
                                            :band="band('free_chlorine')"
                                            color="hsl(var(--brand))"
                                            class="mt-1"
                                        />
                                    </figure>
                                    <figure v-if="visit.trend.ph.length > 1" class="border-border rounded-2xl border p-3">
                                        <figcaption class="text-sm font-medium">pH</figcaption>
                                        <Sparkline :values="visit.trend.ph" :band="band('ph')" color="hsl(var(--brand))" class="mt-1" />
                                    </figure>
                                </div>
                                <p v-if="visit.trend.dates.length > 1" class="text-muted-foreground mt-2 text-xs">
                                    The last {{ visit.trend.dates.length }} visits, {{ visit.trend.dates[0] }} to
                                    {{ visit.trend.dates[visit.trend.dates.length - 1] }}. Shaded area is the healthy range.
                                </p>
                            </PaneSection>
                        </template>
                    </div>
                </template>
            </MasterDetail>
        </div>
    </AppLayout>
</template>
