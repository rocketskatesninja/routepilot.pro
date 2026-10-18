<script setup lang="ts">
import Combobox from '@/components/Combobox.vue';
import EntityAvatar from '@/components/EntityAvatar.vue';
import ListTable from '@/components/ListTable.vue';
import MasterDetail from '@/components/MasterDetail.vue';
import SortableTh from '@/components/SortableTh.vue';
import Chip from '@/components/pane/Chip.vue';
import IconTile from '@/components/pane/IconTile.vue';
import PaneAction from '@/components/pane/PaneAction.vue';
import PaneHeader from '@/components/pane/PaneHeader.vue';
import PaneItem from '@/components/pane/PaneItem.vue';
import PaneList from '@/components/pane/PaneList.vue';
import PaneSection from '@/components/pane/PaneSection.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/AppLayout.vue';
import { customerLink } from '@/lib/links';
import { visitQuery } from '@/lib/listQuery';
import { cap } from '@/lib/portal';
import { formatDay, formatMoney } from '@/lib/utils';
import { type BreadcrumbItem } from '@/types';
import { type Paginated } from '@/types/pagination';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { Ban, CheckCircle2, Download, FileText, Mail, Plus, Receipt } from 'lucide-vue-next';
import { computed, ref } from 'vue';

interface BalanceRow {
    id: number;
    name: string;
    photo: string | null;
    balance: number;
    pools: number;
    autopay: boolean;
    card: string | null;
}

interface InvoiceRow {
    id: number;
    number: string;
    customer: string | null;
    customer_id: number | null;
    issued_on: string | null;
    due_on: string | null;
    total: number;
    balance: number;
    status: string;
}

interface OwingDetail {
    kind: 'owing';
    id: number;
    name: string;
    photo: string | null;
    visits: { service: string; pool: string; date: string; price: number }[];
    charges: { description: string; amount: number }[];
    total: number;
    invoices: { id: number; number: string; status: string; total: number; balance: number; issued_on: string | null }[];
}

interface InvoiceDetail {
    kind: 'invoice';
    id: number;
    number: string;
    status: string;
    customer: string | null;
    customer_id: number | null;
    has_email: boolean;
    period_start: string | null;
    period_end: string | null;
    issued_on: string | null;
    due_on: string | null;
    subtotal: number;
    tax: number;
    total: number;
    amount_paid: number;
    balance: number;
    line_items: { description: string; amount: number }[];
}

const props = defineProps<{
    view: 'owing' | 'invoices';
    balances: Paginated<BalanceRow>;
    invoices: Paginated<InvoiceRow> | null;
    counts: { owing: number; invoices: number };
    total: number;
    selected: OwingDetail | InvoiceDetail | null;
    invoiceStatus: string;
    canManage: boolean;
    customers: { id: number; name: string }[];
    sort: { key: string; dir: string };
}>();

const breadcrumbs: BreadcrumbItem[] = [{ title: 'Balances', href: '/balances' }];
const money = formatMoney;

const open = (id: number) => visitQuery({ selected: id });
const closeDrawer = () => visitQuery({ selected: undefined });

const tabs = [
    { key: 'owing', label: 'Owing' },
    { key: 'invoices', label: 'Invoices' },
] as const;
function setView(v: 'owing' | 'invoices') {
    // The sort carries across (a column the other view lacks falls back to its default
    // order there, and returns when you switch back); selection, status and page reset.
    visitQuery({ view: v === 'owing' ? undefined : v, selected: undefined, status: undefined, page: undefined });
}

const statusFilters = [
    { key: '', label: 'All' },
    { key: 'sent', label: 'Sent' },
    { key: 'overdue', label: 'Overdue' },
    { key: 'paid', label: 'Paid' },
    { key: 'draft', label: 'Draft' },
    { key: 'void', label: 'Void' },
];
const setStatus = (s: string) => visitQuery({ status: s || undefined, selected: undefined, page: undefined });

const statusClass = (s: string): string =>
    ({
        paid: 'bg-emerald-500/15 text-emerald-600 dark:text-emerald-400',
        overdue: 'bg-red-500/15 text-red-600 dark:text-red-400',
        sent: 'bg-sky-500/15 text-sky-600 dark:text-sky-400',
        draft: 'bg-muted text-muted-foreground',
        void: 'bg-muted text-muted-foreground line-through',
    })[s] ?? 'bg-muted text-muted-foreground';

const payMethod = ref('cash');
const PAY_METHODS: Record<string, string> = { cash: 'Cash', check: 'Check', card: 'Card', ach: 'Bank transfer', other: 'Other' };

// --- the side panel: an invoice, or what a customer owes ---
const invoice = computed(() => (props.selected?.kind === 'invoice' ? props.selected : null));
const owing = computed(() => (props.selected?.kind === 'owing' ? props.selected : null));
const invoiceTone: Record<string, 'good' | 'warn' | 'info' | 'bad' | 'muted'> = {
    paid: 'good',
    overdue: 'bad',
    sent: 'info',
    draft: 'muted',
    void: 'muted',
};
/** "Sent Thu, Oct 1" / "Paid, issued Thu, Oct 1" / "Draft". */
const invoiceLine = (inv: OwingDetail['invoices'][number]) => {
    const on = inv.issued_on ? formatDay(inv.issued_on) : '';
    return inv.status === 'sent' && on ? `Sent ${on}` : `${cap(inv.status)}${on ? `, issued ${on}` : ''}`;
};
/** "Issued Thu, Oct 1, due Sat, Oct 31." — plus why a draft hasn't gone out. */
const invoiceSummary = (i: InvoiceDetail) =>
    [
        i.issued_on ? `Issued ${formatDay(i.issued_on)}` : 'Not sent yet',
        i.due_on && i.status !== 'paid' && i.status !== 'void' ? `due ${formatDay(i.due_on)}` : '',
    ]
        .filter(Boolean)
        .join(', ') +
    '.' +
    (i.status === 'draft' ? (i.has_email ? ' Not emailed yet.' : ' Not emailed: there’s no email address on file.') : '');

// --- owing-view actions ---
function recordPayment() {
    if (props.selected?.kind !== 'owing') return;
    router.post(`/balances/${props.selected.id}/pay`, { method: payMethod.value }, { preserveScroll: true, onSuccess: () => closeDrawer() });
}
function generateInvoice() {
    if (props.selected?.kind !== 'owing') return;
    router.post(`/balances/${props.selected.id}/invoice`, {}, { preserveScroll: true });
}
const exportCsv = () => window.open('/balances/export', '_blank');
const emailInvoice = (id: number) => router.post(`/invoices/${id}/email`, {}, { preserveScroll: true });
function voidInvoice(id: number) {
    if (
        !confirm(
            'Void this invoice? Its charges are written off — released from the customer’s balance and never re-invoiced. This cannot be undone.',
        )
    )
        return;
    router.post(`/invoices/${id}/void`, {}, { preserveScroll: true });
}

// --- invoice-view actions ---
function markInvoicePaid() {
    if (props.selected?.kind !== 'invoice') return;
    router.post(`/invoices/${props.selected.id}/mark-paid`, { method: payMethod.value }, { preserveScroll: true });
}

// --- add manual charge ---
const chargeOpen = ref(false);
const chargeForm = useForm<{ customer_id: number | string; description: string; amount: string; taxable: boolean }>({
    customer_id: '',
    description: '',
    amount: '',
    taxable: true,
});
function openCharge() {
    chargeForm.reset();
    chargeForm.clearErrors();
    chargeOpen.value = true;
}
function submitCharge() {
    chargeForm.post('/balances/charges', {
        preserveScroll: true,
        onSuccess: () => {
            chargeOpen.value = false;
            chargeForm.reset();
        },
    });
}

// The detail pane is shared: it hosts the add-charge form when open, otherwise
// the selected invoice / customer balance. Closing cancels the form first.
function closePane() {
    if (chargeOpen.value) {
        chargeOpen.value = false;
    } else {
        closeDrawer();
    }
}
</script>

<template>
    <Head title="Balances" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <template #filters>
            <button
                v-for="t in tabs"
                :key="t.key"
                class="rounded-md px-2.5 py-1 text-sm font-medium transition-colors"
                :class="props.view === t.key ? 'bg-primary text-primary-foreground' : 'text-muted-foreground hover:bg-muted'"
                @click="setView(t.key)"
            >
                {{ t.label }} <span class="opacity-70">{{ props.counts[t.key] }}</span>
            </button>
            <div v-if="props.view === 'invoices'" class="border-border ml-2 flex items-center gap-0.5 border-l pl-2">
                <button
                    v-for="s in statusFilters"
                    :key="s.key"
                    class="rounded px-2 py-1 text-xs font-medium transition-colors"
                    :class="props.invoiceStatus === s.key ? 'bg-muted text-foreground' : 'text-muted-foreground hover:bg-muted/60'"
                    @click="setStatus(s.key)"
                >
                    {{ s.label }}
                </button>
            </div>
        </template>

        <template #actions>
            <span class="mr-1 text-sm whitespace-nowrap"
                ><span class="font-semibold">{{ money(props.total) }}</span> <span class="text-muted-foreground">outstanding</span></span
            >
            <Button v-if="props.canManage" size="sm" variant="outline" title="Add charge" @click="openCharge">
                <Plus class="size-4 sm:mr-1" /><span class="hidden sm:inline">Charge</span>
            </Button>
            <Button v-if="props.canManage" size="sm" variant="outline" title="Export CSV" @click="exportCsv">
                <Download class="size-4 sm:mr-1" /><span class="hidden sm:inline">Export</span>
            </Button>
        </template>

        <div class="flex h-full flex-1 flex-col gap-4 p-4">
            <MasterDetail
                :has-selection="chargeOpen || props.selected !== null"
                :selection-key="chargeOpen ? 'form' : props.selected ? `${props.view}-${props.selected.id}` : null"
                empty-text="Select a row to see details."
                @close="closePane"
            >
                <template #list>
                    <!-- Invoices list -->
                    <ListTable
                        v-if="props.view === 'invoices' && props.invoices"
                        :meta="props.invoices"
                        :columns="5"
                        :row-key="(i) => i.id"
                        :selected-key="props.selected?.kind === 'invoice' ? props.selected.id : null"
                        @select="(i) => open(i.id)"
                    >
                        <template #head>
                            <SortableTh sort-key="number" :active="props.sort">Invoice</SortableTh>
                            <SortableTh sort-key="customer" :active="props.sort">Customer</SortableTh>
                            <SortableTh sort-key="issued" :active="props.sort" class="hidden md:table-cell">Issued</SortableTh>
                            <SortableTh sort-key="status" :active="props.sort">Status</SortableTh>
                            <SortableTh sort-key="total" :active="props.sort" align="right" class="text-right">Total</SortableTh>
                        </template>
                        <template #row="{ item: inv }">
                            <td class="px-4 py-2.5 font-medium">{{ inv.number }}</td>
                            <td class="text-muted-foreground px-4 py-2.5">{{ inv.customer ?? '—' }}</td>
                            <td class="text-muted-foreground hidden px-4 py-2.5 md:table-cell">{{ inv.issued_on ?? '—' }}</td>
                            <td class="px-4 py-2.5">
                                <span class="inline-flex rounded-full px-2 py-0.5 text-xs font-medium capitalize" :class="statusClass(inv.status)">{{
                                    inv.status
                                }}</span>
                            </td>
                            <td class="px-4 py-2.5 text-right font-medium">
                                {{ money(inv.total)
                                }}<span v-if="inv.balance > 0" class="block text-xs font-normal text-amber-600 dark:text-amber-400"
                                    >{{ money(inv.balance) }} due</span
                                >
                            </td>
                        </template>
                        <template #empty>
                            <Receipt class="mx-auto mb-2 size-6 opacity-50" />
                            No invoices{{ props.invoiceStatus ? ` with status “${props.invoiceStatus}”` : '' }}.
                        </template>
                    </ListTable>

                    <!-- Owing list -->
                    <ListTable
                        v-else
                        :meta="props.balances"
                        :columns="4"
                        :row-key="(r) => r.id"
                        :selected-key="props.selected?.kind === 'owing' ? props.selected.id : null"
                        @select="(r) => open(r.id)"
                    >
                        <template #head>
                            <SortableTh sort-key="name" :active="props.sort">Customer</SortableTh>
                            <SortableTh sort-key="pools" :active="props.sort" class="hidden md:table-cell">Pools</SortableTh>
                            <th class="hidden px-4 py-2 text-left font-medium sm:table-cell">Autopay</th>
                            <SortableTh sort-key="balance" :active="props.sort" align="right" class="text-right">Balance</SortableTh>
                        </template>
                        <template #row="{ item: row }">
                            <td class="px-4 py-2.5">
                                <div class="flex items-center gap-2.5">
                                    <EntityAvatar :src="row.photo" type="person" :name="row.name" size="sm" shape="circle" />
                                    <span class="font-medium">{{ row.name }}</span>
                                </div>
                            </td>
                            <td class="text-muted-foreground hidden px-4 py-2.5 md:table-cell">{{ row.pools }}</td>
                            <td class="hidden px-4 py-2.5 sm:table-cell">
                                <span v-if="row.autopay" class="inline-flex items-center gap-2">
                                    <span
                                        class="inline-flex items-center rounded-full bg-emerald-500/15 px-2 py-0.5 text-xs font-medium text-emerald-600 dark:text-emerald-400"
                                        >On</span
                                    >
                                    <span v-if="row.card" class="text-muted-foreground text-xs">{{ row.card }}</span>
                                </span>
                                <span
                                    v-else
                                    class="bg-muted text-muted-foreground inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium"
                                    >Off</span
                                >
                            </td>
                            <td class="px-4 py-2.5 text-right font-medium">{{ money(row.balance) }}</td>
                        </template>
                        <template #empty>
                            <CheckCircle2 class="mx-auto mb-2 size-6 opacity-50" />
                            Everyone's paid up.
                        </template>
                    </ListTable>
                </template>

                <template #detail>
                    <!-- add-charge form: hosted in the docked pane, not an overlay -->
                    <form v-if="chargeOpen" class="space-y-4 text-sm" @submit.prevent="submitCharge">
                        <PaneHeader title="Add a charge" summary="A one-off charge, like a part or a repair. It goes on the customer’s balance." />
                        <div class="grid gap-1.5">
                            <Label for="ch_cust">Customer</Label>
                            <Combobox id="ch_cust" v-model="chargeForm.customer_id" :options="props.customers" placeholder="Search customers…" />
                            <p v-if="chargeForm.errors.customer_id" class="text-xs text-red-600">{{ chargeForm.errors.customer_id }}</p>
                        </div>
                        <div class="grid gap-1.5">
                            <Label for="ch_desc">Description</Label>
                            <Input id="ch_desc" v-model="chargeForm.description" placeholder="e.g. Filter replacement" />
                            <p v-if="chargeForm.errors.description" class="text-xs text-red-600">{{ chargeForm.errors.description }}</p>
                        </div>
                        <div class="grid gap-1.5">
                            <Label for="ch_amt">Amount ($)</Label>
                            <Input id="ch_amt" v-model="chargeForm.amount" type="number" min="0.01" step="0.01" />
                            <p v-if="chargeForm.errors.amount" class="text-xs text-red-600">{{ chargeForm.errors.amount }}</p>
                        </div>
                        <label class="flex items-center gap-2"><input v-model="chargeForm.taxable" type="checkbox" /> Taxable</label>
                        <div class="flex justify-end gap-2 pt-2">
                            <Button type="button" variant="outline" @click="chargeOpen = false">Cancel</Button>
                            <Button type="submit" :disabled="chargeForm.processing">Add charge</Button>
                        </div>
                    </form>

                    <!-- an invoice -->
                    <div v-else-if="invoice" class="space-y-6">
                        <PaneHeader :title="invoice.number" :summary="invoiceSummary(invoice)">
                            <template #media><IconTile :icon="FileText" size="lg" /></template>
                            <template #badge>
                                <Chip :tone="invoiceTone[invoice.status] ?? 'muted'">{{ cap(invoice.status) }}</Chip>
                            </template>
                            <p v-if="invoice.customer_id" class="mt-2 text-[15px]">
                                <Link :href="customerLink(invoice.customer_id)" class="text-primary font-medium underline-offset-4 hover:underline">{{
                                    invoice.customer
                                }}</Link>
                            </p>
                            <template #actions>
                                <Button as-child size="sm" variant="outline">
                                    <a :href="`/invoices/${invoice.id}/pdf`" target="_blank"><Download class="mr-1 size-3.5" /> PDF</a>
                                </Button>
                                <Button
                                    v-if="props.canManage && invoice.has_email && invoice.status !== 'void'"
                                    size="sm"
                                    variant="outline"
                                    @click="emailInvoice(invoice.id)"
                                    ><Mail class="mr-1 size-3.5" /> Email</Button
                                >
                                <Button
                                    v-if="props.canManage && invoice.status !== 'paid' && invoice.status !== 'void'"
                                    size="sm"
                                    variant="outline"
                                    class="text-red-600 hover:text-red-600 dark:text-red-400"
                                    @click="voidInvoice(invoice.id)"
                                    ><Ban class="mr-1 size-3.5" /> Void</Button
                                >
                            </template>
                        </PaneHeader>

                        <PaneSection title="What it’s for">
                            <PaneList>
                                <PaneItem v-for="(li, i) in invoice.line_items" :key="i" :title="li.description">
                                    <template #trailing
                                        ><span class="tabular-nums">{{ formatMoney(li.amount) }}</span></template
                                    >
                                </PaneItem>
                                <PaneItem v-if="invoice.tax > 0" title="Subtotal">
                                    <template #trailing
                                        ><span class="tabular-nums">{{ formatMoney(invoice.subtotal) }}</span></template
                                    >
                                </PaneItem>
                                <PaneItem v-if="invoice.tax > 0" title="Tax">
                                    <template #trailing
                                        ><span class="tabular-nums">{{ formatMoney(invoice.tax) }}</span></template
                                    >
                                </PaneItem>
                                <PaneItem title="Total">
                                    <template #trailing
                                        ><span class="font-semibold tabular-nums">{{ formatMoney(invoice.total) }}</span></template
                                    >
                                </PaneItem>
                                <PaneItem v-if="invoice.amount_paid > 0" title="Paid">
                                    <template #trailing
                                        ><span class="text-emerald-700 tabular-nums dark:text-emerald-400"
                                            >−{{ formatMoney(invoice.amount_paid) }}</span
                                        ></template
                                    >
                                </PaneItem>
                                <PaneItem v-if="invoice.balance > 0" title="Still due">
                                    <template #trailing
                                        ><span class="font-semibold text-amber-700 tabular-nums dark:text-amber-400">{{
                                            formatMoney(invoice.balance)
                                        }}</span></template
                                    >
                                </PaneItem>
                            </PaneList>
                        </PaneSection>

                        <PaneSection v-if="props.canManage && invoice.status !== 'paid' && invoice.status !== 'void'" title="Record a payment">
                            <div class="flex flex-wrap items-center gap-2">
                                <select
                                    v-model="payMethod"
                                    aria-label="Paid by"
                                    class="border-input bg-background h-9 w-36 rounded-md border px-2 text-sm"
                                >
                                    <option v-for="(label, key) in PAY_METHODS" :key="key" :value="key">{{ label }}</option>
                                </select>
                                <Button size="sm" @click="markInvoicePaid">Mark paid {{ formatMoney(invoice.balance) }}</Button>
                            </div>
                        </PaneSection>
                    </div>

                    <!-- what a customer owes -->
                    <div v-else-if="owing" class="space-y-6">
                        <PaneHeader :title="owing.name">
                            <template #media>
                                <EntityAvatar :src="owing.photo" type="person" :name="owing.name" size="lg" shape="circle" />
                            </template>
                            <p class="text-muted-foreground mt-1 text-[15px]">Owes</p>
                            <p class="font-display text-4xl font-semibold tracking-tight">{{ formatMoney(owing.total) }}</p>
                            <Link
                                :href="customerLink(owing.id)"
                                class="text-primary mt-2 inline-block text-sm font-medium underline-offset-4 hover:underline"
                                >Open customer</Link
                            >
                        </PaneHeader>

                        <PaneSection v-if="props.canManage" title="Record a payment">
                            <div class="flex flex-wrap items-center gap-2">
                                <select
                                    v-model="payMethod"
                                    aria-label="Paid by"
                                    class="border-input bg-background h-9 w-36 rounded-md border px-2 text-sm"
                                >
                                    <option v-for="(label, key) in PAY_METHODS" :key="key" :value="key">{{ label }}</option>
                                </select>
                                <Button size="sm" @click="recordPayment">Mark paid {{ formatMoney(owing.total) }}</Button>
                                <Button
                                    size="sm"
                                    variant="outline"
                                    title="Put everything not yet billed on an invoice and email it"
                                    @click="generateInvoice"
                                    ><FileText class="mr-1 size-3.5" /> Invoice now</Button
                                >
                            </div>
                        </PaneSection>

                        <PaneSection title="What they owe for">
                            <PaneList>
                                <PaneItem v-for="(v, i) in owing.visits" :key="`v${i}`" :title="v.service" :sub="`${formatDay(v.date)}, ${v.pool}`">
                                    <template #trailing
                                        ><span class="tabular-nums">{{ formatMoney(v.price) }}</span></template
                                    >
                                </PaneItem>
                                <PaneItem v-for="(c, i) in owing.charges" :key="`c${i}`" :title="c.description">
                                    <template #trailing
                                        ><span class="tabular-nums">{{ formatMoney(c.amount) }}</span></template
                                    >
                                </PaneItem>
                                <PaneItem title="Total">
                                    <template #trailing
                                        ><span class="font-semibold tabular-nums">{{ formatMoney(owing.total) }}</span></template
                                    >
                                </PaneItem>
                            </PaneList>
                        </PaneSection>

                        <PaneSection v-if="owing.invoices.length" title="Invoices">
                            <PaneList>
                                <PaneItem v-for="inv in owing.invoices" :key="inv.id" :title="inv.number" :sub="invoiceLine(inv)">
                                    <div class="mt-1 flex gap-4">
                                        <PaneAction :href="`/invoices/${inv.id}/pdf`" new-tab>PDF</PaneAction>
                                        <PaneAction v-if="props.canManage && inv.status !== 'void'" @click="emailInvoice(inv.id)">Email</PaneAction>
                                    </div>
                                    <template #trailing>
                                        <p class="tabular-nums">{{ formatMoney(inv.total) }}</p>
                                        <p v-if="inv.balance > 0" class="text-xs text-amber-700 tabular-nums dark:text-amber-400">
                                            {{ formatMoney(inv.balance) }} due
                                        </p>
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
