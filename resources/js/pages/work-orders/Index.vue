<script setup lang="ts">
import Combobox from '@/components/Combobox.vue';
import ListTable from '@/components/ListTable.vue';
import MasterDetail from '@/components/MasterDetail.vue';
import NoteBubble from '@/components/NoteBubble.vue';
import Chip from '@/components/pane/Chip.vue';
import PaneFacts from '@/components/pane/PaneFacts.vue';
import PaneHeader from '@/components/pane/PaneHeader.vue';
import PaneSection from '@/components/pane/PaneSection.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/AppLayout.vue';
import { customerLink, poolLink, reportLink } from '@/lib/links';
import { visitQuery } from '@/lib/listQuery';
import { formatDay } from '@/lib/utils';
import { type BreadcrumbItem } from '@/types';
import { type Paginated } from '@/types/pagination';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { Check, Pencil, Plus, RotateCcw, Trash2, Wrench } from 'lucide-vue-next';
import { computed, ref } from 'vue';

interface OrderRow {
    id: number;
    title: string;
    urgent: boolean;
    overdue: boolean;
    due_on: string | null;
    pool: string | null;
    customer: string | null;
    assignee: string | null;
    done_on: string | null;
}

interface OrderFields {
    title: string;
    details: string | null;
    urgent: boolean;
    due_on: string | null;
    assigned_to: number | null;
    pool_id: number | null;
}

interface OrderDetail {
    id: number;
    title: string;
    details: string | null;
    status: 'open' | 'done';
    urgent: boolean;
    overdue: boolean;
    due_on: string | null;
    pool: string | null;
    pool_id: number | null;
    customer: string | null;
    customer_id: number | null;
    assignee: string | null;
    raised_by: string | null;
    raised_on: string | null;
    visit_id: number | null;
    visit_on: string | null;
    done_by: string | null;
    done_on: string | null;
    comments: { id: number; author: string; on: string | null; body: string }[];
    fields: OrderFields;
    can: { update: boolean; complete: boolean; comment: boolean; delete: boolean };
}

const props = defineProps<{
    orders: Paginated<OrderRow>;
    selected: OrderDetail | null;
    status: 'open' | 'done';
    mine: boolean;
    runsTheList: boolean;
    canCreate: boolean;
    pools: { id: number; name: string }[];
    people: { id: number; name: string }[];
}>();

const breadcrumbs: BreadcrumbItem[] = [{ title: 'Work orders', href: '/work-orders' }];

// The URL keeps only what differs from the defaults (open, everyone's).
const setStatus = (status: string) => visitQuery({ status: status === 'done' ? 'done' : undefined, page: undefined, selected: undefined });
const toggleMine = () => visitQuery({ mine: props.mine ? undefined : 1, page: undefined });
const open = (id: number) => visitQuery({ selected: id });
const closeDrawer = () => visitQuery({ selected: undefined });

const order = computed(() => props.selected);

const where = (o: { pool: string | null; customer: string | null }) => (o.pool ? (o.customer ? `${o.pool}, ${o.customer}` : o.pool) : null);

// The pane hosts the add / edit form when one is open; closing the pane cancels it first.
function closePane() {
    if (formOpen.value) formOpen.value = false;
    else closeDrawer();
}

// --- add / edit ---
const formOpen = ref(false);
const formMode = ref<'create' | 'edit'>('create');
const form = useForm<{ title: string; details: string; urgent: boolean; due_on: string; assigned_to: number | string; pool_id: number | string }>({
    title: '',
    details: '',
    urgent: false,
    due_on: '',
    assigned_to: '',
    pool_id: '',
});

function openCreate() {
    form.reset();
    form.clearErrors();
    formMode.value = 'create';
    formOpen.value = true;
}

function openEdit() {
    if (!props.selected) return;
    const f = props.selected.fields;
    form.title = f.title;
    form.details = f.details ?? '';
    form.urgent = f.urgent;
    form.due_on = f.due_on ?? '';
    form.assigned_to = f.assigned_to ?? '';
    form.pool_id = f.pool_id ?? '';
    form.clearErrors();
    formMode.value = 'edit';
    formOpen.value = true;
}

function submitForm() {
    const done = { preserveScroll: true, onSuccess: () => (formOpen.value = false) };
    if (formMode.value === 'create') form.post('/work-orders', done);
    else if (props.selected) form.patch(`/work-orders/${props.selected.id}`, done);
}

const setDone = (done: boolean) =>
    props.selected && router.post(`/work-orders/${props.selected.id}/${done ? 'done' : 'reopen'}`, {}, { preserveScroll: true });

function destroy() {
    if (!props.selected || !confirm('Delete this work order?')) return;
    router.delete(`/work-orders/${props.selected.id}`);
}

// --- the notes thread ---
const noteForm = useForm({ body: '' });
function addNote() {
    if (!props.selected || noteForm.body.trim() === '') return;
    noteForm.post(`/work-orders/${props.selected.id}/comments`, { preserveScroll: true, onSuccess: () => noteForm.reset() });
}
</script>

<template>
    <Head title="Work orders" />

    <AppLayout :breadcrumbs="breadcrumbs" :meta="`${props.orders.total} ${props.status === 'done' ? 'done' : 'open'}`">
        <template #actions>
            <div class="border-input flex rounded-md border p-0.5">
                <button
                    v-for="s in ['open', 'done']"
                    :key="s"
                    class="rounded px-2.5 py-1 text-sm font-medium transition-colors"
                    :class="props.status === s ? 'bg-muted text-foreground' : 'text-muted-foreground hover:text-foreground'"
                    @click="setStatus(s)"
                >
                    {{ s === 'open' ? 'Open' : 'Done' }}
                </button>
            </div>
            <Button v-if="props.runsTheList" size="sm" :variant="props.mine ? 'default' : 'outline'" :aria-pressed="props.mine" @click="toggleMine">
                Mine
            </Button>
            <Button v-if="props.canCreate" size="sm" title="New work order" @click="openCreate">
                <Plus class="size-4 sm:mr-1" /><span class="hidden sm:inline">Work order</span>
            </Button>
        </template>

        <div class="flex h-full flex-1 flex-col gap-4 p-4">
            <MasterDetail
                :has-selection="formOpen || props.selected !== null"
                :selection-key="formOpen ? `form-${formMode}` : (props.selected?.id ?? null)"
                empty-text="Select a work order to see it."
                @close="closePane"
            >
                <template #list>
                    <ListTable
                        :meta="props.orders"
                        :columns="3"
                        :row-key="(o) => o.id"
                        :selected-key="props.selected?.id ?? null"
                        @select="(o) => open(o.id)"
                    >
                        <template #head>
                            <th class="px-4 py-2 text-left font-medium">Work order</th>
                            <th class="px-4 py-2 text-left font-medium">{{ props.status === 'done' ? 'Done' : 'Due' }}</th>
                            <th class="hidden px-4 py-2 text-left font-medium md:table-cell">Assigned to</th>
                        </template>
                        <template #row="{ item: o }">
                            <td class="px-4 py-2.5">
                                <div class="flex items-center gap-2">
                                    <span class="font-medium">{{ o.title }}</span>
                                    <Chip v-if="o.urgent" tone="bad">Urgent</Chip>
                                </div>
                                <p v-if="where(o)" class="text-muted-foreground text-xs">{{ where(o) }}</p>
                            </td>
                            <td class="px-4 py-2.5 whitespace-nowrap">
                                <template v-if="props.status === 'done'"
                                    ><span class="text-muted-foreground">{{ formatDay(o.done_on) }}</span></template
                                >
                                <Chip v-else-if="o.overdue" tone="warn">Overdue</Chip>
                                <span v-else-if="o.due_on" class="text-muted-foreground">{{ formatDay(o.due_on) }}</span>
                                <span v-else class="text-muted-foreground">—</span>
                            </td>
                            <td class="text-muted-foreground hidden px-4 py-2.5 md:table-cell">{{ o.assignee ?? 'Nobody yet' }}</td>
                        </template>
                        <template #empty>
                            <Wrench class="mx-auto mb-2 size-6 opacity-50" />
                            <template v-if="props.status === 'done'">Nothing finished yet.</template>
                            <template v-else-if="props.runsTheList && !props.mine"
                                >Nothing to do. Techs can add work orders from their visits.</template
                            >
                            <template v-else>Nothing assigned to you.</template>
                        </template>
                    </ListTable>
                </template>

                <template #detail>
                    <!-- add / edit, hosted in the docked pane -->
                    <form v-if="formOpen" class="space-y-4 text-sm" @submit.prevent="submitForm">
                        <PaneHeader
                            :title="formMode === 'create' ? 'New work order' : 'Edit work order'"
                            summary="Something the company needs to get done."
                        />
                        <div class="grid gap-1.5">
                            <Label for="wo_title">What needs doing</Label>
                            <Input id="wo_title" v-model="form.title" maxlength="160" />
                            <p v-if="form.errors.title" class="text-xs text-red-600">{{ form.errors.title }}</p>
                        </div>
                        <div class="grid gap-1.5">
                            <Label for="wo_details">Details</Label>
                            <textarea
                                id="wo_details"
                                v-model="form.details"
                                rows="3"
                                class="border-input bg-background placeholder:text-muted-foreground w-full rounded-md border px-3 py-2 text-sm"
                            ></textarea>
                        </div>
                        <div class="grid gap-1.5">
                            <Label for="wo_pool">Pool</Label>
                            <Combobox id="wo_pool" v-model="form.pool_id" :options="props.pools" placeholder="Search pools… (optional)" />
                            <p v-if="form.errors.pool_id" class="text-xs text-red-600">{{ form.errors.pool_id }}</p>
                        </div>
                        <div class="flex flex-wrap gap-3">
                            <div class="grid gap-1.5">
                                <Label for="wo_assignee">Assigned to</Label>
                                <select
                                    id="wo_assignee"
                                    v-model="form.assigned_to"
                                    class="border-input bg-background h-9 w-56 rounded-md border px-2 text-sm"
                                >
                                    <option value="">Nobody yet</option>
                                    <option v-for="p in props.people" :key="p.id" :value="p.id">{{ p.name }}</option>
                                </select>
                                <p v-if="form.errors.assigned_to" class="text-xs text-red-600">{{ form.errors.assigned_to }}</p>
                            </div>
                            <div class="grid gap-1.5">
                                <Label for="wo_due">Due</Label>
                                <Input id="wo_due" v-model="form.due_on" type="date" class="w-40" />
                            </div>
                        </div>
                        <label class="flex items-center gap-2"
                            ><input v-model="form.urgent" type="checkbox" class="accent-primary size-4" /> Urgent</label
                        >
                        <div class="flex justify-end gap-2 pt-2">
                            <Button type="button" variant="outline" @click="formOpen = false">Cancel</Button>
                            <Button type="submit" :disabled="form.processing">{{ formMode === 'create' ? 'Add work order' : 'Save' }}</Button>
                        </div>
                    </form>

                    <div v-else-if="order" class="space-y-6">
                        <PaneHeader :title="order.title" :summary="where(order) ?? 'Not tied to a pool.'">
                            <template #badge>
                                <span class="flex flex-wrap gap-1.5">
                                    <Chip v-if="order.urgent" tone="bad">Urgent</Chip>
                                    <Chip v-if="order.overdue" tone="warn">Overdue</Chip>
                                    <Chip :tone="order.status === 'done' ? 'good' : 'info'">{{ order.status === 'done' ? 'Done' : 'Open' }}</Chip>
                                </span>
                            </template>
                            <template v-if="order.can.complete || order.can.update || order.can.delete" #actions>
                                <template v-if="order.can.complete">
                                    <Button v-if="order.status === 'open'" size="sm" @click="setDone(true)"
                                        ><Check class="mr-1 size-3.5" /> Mark done</Button
                                    >
                                    <Button v-else size="sm" variant="outline" @click="setDone(false)"
                                        ><RotateCcw class="mr-1 size-3.5" /> Reopen</Button
                                    >
                                </template>
                                <Button v-if="order.can.update" size="sm" variant="outline" @click="openEdit"
                                    ><Pencil class="mr-1 size-3.5" /> Edit</Button
                                >
                                <Button
                                    v-if="order.can.delete"
                                    size="sm"
                                    variant="outline"
                                    class="text-red-600 hover:text-red-600 dark:text-red-400"
                                    @click="destroy"
                                    ><Trash2 class="mr-1 size-3.5" /> Delete</Button
                                >
                            </template>
                        </PaneHeader>

                        <PaneSection v-if="order.details" title="Details">
                            <p class="text-sm whitespace-pre-line">{{ order.details }}</p>
                        </PaneSection>

                        <PaneSection title="About it">
                            <PaneFacts
                                :facts="[
                                    { label: 'Assigned to', value: order.assignee ?? 'Nobody yet' },
                                    { label: 'Due', value: order.due_on ? `${formatDay(order.due_on)}${order.overdue ? ', overdue' : ''}` : null },
                                    { label: 'Raised', value: [order.raised_by, formatDay(order.raised_on)].filter(Boolean).join(', ') || null },
                                    {
                                        label: 'Done',
                                        value: order.status === 'done' ? [order.done_by, formatDay(order.done_on)].filter(Boolean).join(', ') : null,
                                    },
                                ]"
                            />
                            <div class="mt-3 flex flex-wrap gap-x-4 gap-y-1 text-sm">
                                <Link v-if="order.visit_id" :href="reportLink(order.visit_id)" class="text-primary underline-offset-4 hover:underline"
                                    >From the visit on {{ formatDay(order.visit_on) }}</Link
                                >
                                <Link v-if="order.pool_id" :href="poolLink(order.pool_id)" class="text-primary underline-offset-4 hover:underline"
                                    >See the pool</Link
                                >
                                <Link
                                    v-if="order.customer_id"
                                    :href="customerLink(order.customer_id)"
                                    class="text-primary underline-offset-4 hover:underline"
                                    >See the customer</Link
                                >
                            </div>
                        </PaneSection>

                        <PaneSection title="Notes">
                            <div v-if="order.comments.length" class="space-y-2">
                                <NoteBubble v-for="c in order.comments" :key="c.id" :author="`${c.author}, ${formatDay(c.on)}`">{{
                                    c.body
                                }}</NoteBubble>
                            </div>
                            <p v-else class="text-muted-foreground text-sm">No notes yet.</p>
                            <form v-if="order.can.comment" class="mt-3 space-y-2" @submit.prevent="addNote">
                                <textarea
                                    v-model="noteForm.body"
                                    rows="2"
                                    aria-label="Add a note"
                                    placeholder="Add a note: what's been done, what's next."
                                    class="border-input bg-background placeholder:text-muted-foreground w-full rounded-md border px-3 py-2 text-sm"
                                ></textarea>
                                <p v-if="noteForm.errors.body" class="text-xs text-red-600">{{ noteForm.errors.body }}</p>
                                <div class="flex justify-end">
                                    <Button type="submit" size="sm" :disabled="noteForm.processing || noteForm.body.trim() === ''">Add note</Button>
                                </div>
                            </form>
                        </PaneSection>
                    </div>
                </template>
            </MasterDetail>
        </div>
    </AppLayout>
</template>
