<script setup lang="ts">
import EntityAvatar from '@/components/EntityAvatar.vue';
import ListTable from '@/components/ListTable.vue';
import MasterDetail from '@/components/MasterDetail.vue';
import SortableTh from '@/components/SortableTh.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/AppLayout.vue';
import { customerLink } from '@/lib/links';
import { formatMoney } from '@/lib/utils';
import { type BreadcrumbItem } from '@/types';
import { type Paginated } from '@/types/pagination';
import { Head, Link, router, useForm, usePage } from '@inertiajs/vue3';
import { CheckCircle2, Download, FileText, Mail, Plus, Receipt } from 'lucide-vue-next';
import { ref } from 'vue';

interface BalanceRow {
    id: number;
    name: string;
    photo: string | null;
    balance: number;
    pools: number;
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
    visits: { pool: string; date: string; price: number }[];
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
const page = usePage();

// Merge a patch onto the current query and navigate — preserves sort/status/etc.
function navigate(patch: Record<string, string | number | undefined>) {
    const url = new URL(page.url, 'http://localhost');
    for (const [k, v] of Object.entries(patch)) {
        if (v === undefined || v === '') url.searchParams.delete(k);
        else url.searchParams.set(k, String(v));
    }
    router.get(url.pathname + url.search, {}, { preserveState: true, preserveScroll: true });
}

const open = (id: number) => navigate({ selected: id });
const closeDrawer = () => navigate({ selected: undefined });

const tabs = [
    { key: 'owing', label: 'Owing' },
    { key: 'invoices', label: 'Invoices' },
] as const;
function setView(v: 'owing' | 'invoices') {
    // Each view has its own sort/status/selection, so reset them on switch.
    navigate({ view: v === 'owing' ? undefined : v, selected: undefined, sort: undefined, dir: undefined, status: undefined, page: undefined });
}

const statusFilters = [
    { key: '', label: 'All' },
    { key: 'sent', label: 'Sent' },
    { key: 'overdue', label: 'Overdue' },
    { key: 'paid', label: 'Paid' },
    { key: 'draft', label: 'Draft' },
];
const setStatus = (s: string) => navigate({ status: s || undefined, selected: undefined, page: undefined });

const statusClass = (s: string): string =>
    ({
        paid: 'bg-emerald-500/15 text-emerald-600 dark:text-emerald-400',
        overdue: 'bg-red-500/15 text-red-600 dark:text-red-400',
        sent: 'bg-sky-500/15 text-sky-600 dark:text-sky-400',
        draft: 'bg-muted text-muted-foreground',
    })[s] ?? 'bg-muted text-muted-foreground';

const payMethod = ref('cash');

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
            <div v-if="props.view === 'invoices'" class="ml-2 flex items-center gap-0.5 border-l border-border pl-2">
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
            <span class="mr-1 whitespace-nowrap text-sm"
                ><span class="font-semibold">{{ money(props.total) }}</span> <span class="text-muted-foreground">outstanding</span></span
            >
            <Button v-if="props.canManage" size="sm" variant="outline" @click="openCharge"><Plus class="mr-1 size-4" /> Charge</Button>
            <Button v-if="props.canManage" size="sm" variant="outline" @click="exportCsv"><Download class="mr-1 size-4" /> Export</Button>
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
                            <td class="px-4 py-2.5 text-muted-foreground">{{ inv.customer ?? '—' }}</td>
                            <td class="hidden px-4 py-2.5 text-muted-foreground md:table-cell">{{ inv.issued_on ?? '—' }}</td>
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
                        :columns="3"
                        :row-key="(r) => r.id"
                        :selected-key="props.selected?.kind === 'owing' ? props.selected.id : null"
                        @select="(r) => open(r.id)"
                    >
                        <template #head>
                            <SortableTh sort-key="name" :active="props.sort">Customer</SortableTh>
                            <SortableTh sort-key="pools" :active="props.sort" class="hidden md:table-cell">Pools</SortableTh>
                            <SortableTh sort-key="balance" :active="props.sort" align="right" class="text-right">Balance</SortableTh>
                        </template>
                        <template #row="{ item: row }">
                            <td class="px-4 py-2.5">
                                <div class="flex items-center gap-2.5">
                                    <EntityAvatar :src="row.photo" type="person" :name="row.name" size="sm" shape="circle" />
                                    <span class="font-medium">{{ row.name }}</span>
                                </div>
                            </td>
                            <td class="hidden px-4 py-2.5 text-muted-foreground md:table-cell">{{ row.pools }}</td>
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
                        <div class="mb-1">
                            <h2 class="text-lg font-semibold">Add a charge</h2>
                            <p class="text-sm text-muted-foreground">An ad-hoc charge raises the customer's balance.</p>
                        </div>
                        <div class="grid gap-1.5">
                            <Label for="ch_cust">Customer</Label>
                            <select
                                id="ch_cust"
                                v-model="chargeForm.customer_id"
                                class="h-9 rounded-md border border-input bg-background px-2 text-sm"
                            >
                                <option value="">Select…</option>
                                <option v-for="c in props.customers" :key="c.id" :value="c.id">{{ c.name }}</option>
                            </select>
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

                    <!-- invoice detail -->
                    <div v-else-if="props.selected?.kind === 'invoice'">
                        <div class="mb-4">
                            <div class="flex items-center justify-between gap-2">
                                <h2 class="text-lg font-semibold">{{ props.selected.number }}</h2>
                                <span
                                    class="inline-flex rounded-full px-2 py-0.5 text-xs font-medium capitalize"
                                    :class="statusClass(props.selected.status)"
                                    >{{ props.selected.status }}</span
                                >
                            </div>
                            <p class="text-sm text-muted-foreground">
                                <Link
                                    v-if="props.selected.customer_id"
                                    :href="customerLink(props.selected.customer_id)"
                                    class="text-primary hover:underline"
                                    >{{ props.selected.customer }}</Link
                                >
                                <template v-else>{{ props.selected.customer }}</template>
                            </p>
                        </div>

                        <div class="space-y-5 text-sm">
                            <dl class="space-y-1 text-muted-foreground">
                                <div class="flex justify-between">
                                    <dt>Issued</dt>
                                    <dd>{{ props.selected.issued_on ?? '—' }}</dd>
                                </div>
                                <div class="flex justify-between">
                                    <dt>Due</dt>
                                    <dd>{{ props.selected.due_on ?? '—' }}</dd>
                                </div>
                                <div v-if="props.selected.period_end" class="flex justify-between">
                                    <dt>Period</dt>
                                    <dd>{{ props.selected.period_start ?? '…' }} – {{ props.selected.period_end }}</dd>
                                </div>
                            </dl>

                            <section v-if="props.selected.line_items.length">
                                <h3 class="mb-1 font-medium">Line items</h3>
                                <ul class="space-y-1 text-muted-foreground">
                                    <li v-for="(li, i) in props.selected.line_items" :key="i" class="flex justify-between gap-2">
                                        <span>{{ li.description }}</span
                                        ><span>{{ money(li.amount) }}</span>
                                    </li>
                                </ul>
                            </section>

                            <dl class="space-y-1 border-t border-border pt-2">
                                <div class="flex justify-between text-muted-foreground">
                                    <dt>Subtotal</dt>
                                    <dd>{{ money(props.selected.subtotal) }}</dd>
                                </div>
                                <div class="flex justify-between text-muted-foreground">
                                    <dt>Tax</dt>
                                    <dd>{{ money(props.selected.tax) }}</dd>
                                </div>
                                <div class="flex justify-between font-medium">
                                    <dt>Total</dt>
                                    <dd>{{ money(props.selected.total) }}</dd>
                                </div>
                                <div v-if="props.selected.amount_paid > 0" class="flex justify-between text-emerald-600 dark:text-emerald-400">
                                    <dt>Paid</dt>
                                    <dd>{{ money(props.selected.amount_paid) }}</dd>
                                </div>
                                <div v-if="props.selected.balance > 0" class="flex justify-between font-medium text-amber-600 dark:text-amber-400">
                                    <dt>Balance due</dt>
                                    <dd>{{ money(props.selected.balance) }}</dd>
                                </div>
                            </dl>

                            <div class="flex flex-wrap gap-2 border-t border-border pt-3">
                                <a :href="`/invoices/${props.selected.id}/pdf`" target="_blank">
                                    <Button size="sm" variant="outline"><Download class="mr-1 size-3.5" /> PDF</Button>
                                </a>
                                <Button v-if="props.canManage" size="sm" variant="outline" @click="emailInvoice(props.selected.id)"
                                    ><Mail class="mr-1 size-3.5" /> Email</Button
                                >
                            </div>

                            <div
                                v-if="props.canManage && props.selected.status !== 'paid'"
                                class="flex items-center gap-2 border-t border-border pt-3"
                            >
                                <select v-model="payMethod" class="h-9 rounded-md border border-input bg-background px-2 text-sm">
                                    <option value="cash">Cash</option>
                                    <option value="check">Check</option>
                                    <option value="card">Card</option>
                                    <option value="ach">ACH</option>
                                    <option value="other">Other</option>
                                </select>
                                <Button size="sm" @click="markInvoicePaid">Mark paid · {{ money(props.selected.balance) }}</Button>
                            </div>
                        </div>
                    </div>

                    <!-- owing (customer balance) detail -->
                    <div v-else-if="props.selected">
                        <div class="mb-4 flex items-center gap-3">
                            <EntityAvatar :src="props.selected.photo" type="person" :name="props.selected.name" size="lg" shape="circle" />
                            <div>
                                <h2 class="text-lg font-semibold">
                                    <Link :href="customerLink(props.selected.id)" class="hover:underline">{{ props.selected.name }}</Link>
                                </h2>
                                <p class="text-sm text-muted-foreground">{{ money(props.selected.total) }} outstanding</p>
                            </div>
                        </div>

                        <div class="space-y-5 text-sm">
                            <section v-if="props.selected.visits.length">
                                <h3 class="mb-1 font-medium">Unpaid visits</h3>
                                <ul class="space-y-1 text-muted-foreground">
                                    <li v-for="(v, i) in props.selected.visits" :key="i" class="flex justify-between">
                                        <span
                                            >{{ v.pool }} <span class="text-xs">· {{ v.date }}</span></span
                                        >
                                        <span>{{ money(v.price) }}</span>
                                    </li>
                                </ul>
                            </section>

                            <section v-if="props.selected.charges.length">
                                <h3 class="mb-1 font-medium">Manual charges</h3>
                                <ul class="space-y-1 text-muted-foreground">
                                    <li v-for="(c, i) in props.selected.charges" :key="i" class="flex justify-between">
                                        <span>{{ c.description }}</span
                                        ><span>{{ money(c.amount) }}</span>
                                    </li>
                                </ul>
                            </section>

                            <div class="flex justify-between border-t border-border pt-2 font-medium">
                                <span>Total</span><span>{{ money(props.selected.total) }}</span>
                            </div>

                            <section v-if="props.selected.invoices.length">
                                <h3 class="mb-1 font-medium">Invoices</h3>
                                <ul class="space-y-1 text-muted-foreground">
                                    <li v-for="inv in props.selected.invoices" :key="inv.id" class="flex items-center justify-between">
                                        <span class="flex items-center gap-2">
                                            <a :href="`/invoices/${inv.id}/pdf`" target="_blank" class="text-foreground hover:underline"
                                                >{{ inv.number }} <span class="text-xs capitalize text-muted-foreground">· {{ inv.status }}</span></a
                                            >
                                            <button
                                                v-if="props.canManage"
                                                class="text-xs text-sky-600 hover:underline dark:text-sky-400"
                                                @click="emailInvoice(inv.id)"
                                            >
                                                Email
                                            </button>
                                        </span>
                                        <span
                                            >{{ money(inv.total)
                                            }}<span v-if="inv.balance > 0" class="text-amber-600 dark:text-amber-400">
                                                · {{ money(inv.balance) }} due</span
                                            ></span
                                        >
                                    </li>
                                </ul>
                            </section>

                            <div v-if="props.canManage" class="space-y-2 border-t border-border pt-3">
                                <div class="flex items-center gap-2">
                                    <select v-model="payMethod" class="h-9 rounded-md border border-input bg-background px-2 text-sm">
                                        <option value="cash">Cash</option>
                                        <option value="check">Check</option>
                                        <option value="card">Card</option>
                                        <option value="ach">ACH</option>
                                        <option value="other">Other</option>
                                    </select>
                                    <Button size="sm" @click="recordPayment">Mark paid · {{ money(props.selected.total) }}</Button>
                                </div>
                                <Button size="sm" variant="outline" class="w-full" @click="generateInvoice"
                                    ><FileText class="mr-1 size-3.5" /> Generate invoice</Button
                                >
                            </div>
                            <p v-else class="text-xs text-muted-foreground">Card payments + autopay arrive with the Stripe flow.</p>
                        </div>
                    </div>
                </template>
            </MasterDetail>
        </div>
    </AppLayout>
</template>
