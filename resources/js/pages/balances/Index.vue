<script setup lang="ts">
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Sheet, SheetContent, SheetDescription, SheetHeader, SheetTitle } from '@/components/ui/sheet';
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem } from '@/types';
import { Head, router, useForm } from '@inertiajs/vue3';
import { CheckCircle2, Download, FileText, Plus } from 'lucide-vue-next';
import { ref } from 'vue';

interface BalanceRow {
    id: number;
    name: string;
    balance: number;
    pools: number;
}

interface BalanceDetail {
    id: number;
    name: string;
    visits: { pool: string; date: string; price: number }[];
    charges: { description: string; amount: number }[];
    total: number;
    invoices: { id: number; number: string; status: string; total: number; balance: number; issued_on: string | null }[];
}

const props = defineProps<{
    balances: BalanceRow[];
    total: number;
    selected: BalanceDetail | null;
    canManage: boolean;
    customers: { id: number; name: string }[];
}>();

const breadcrumbs: BreadcrumbItem[] = [{ title: 'Balances', href: '/balances' }];

const open = (id: number) => router.get('/balances', { selected: id }, { preserveState: true, preserveScroll: true });
const closeDrawer = () => router.get('/balances', {}, { preserveState: true, preserveScroll: true });
const money = (n: number) => `$${n.toFixed(2)}`;

const payMethod = ref('cash');
function recordPayment() {
    if (!props.selected) return;
    router.post(`/balances/${props.selected.id}/pay`, { method: payMethod.value }, { preserveScroll: true, onSuccess: () => closeDrawer() });
}
function generateInvoice() {
    if (!props.selected) return;
    router.post(`/balances/${props.selected.id}/invoice`, {}, { preserveScroll: true });
}
const exportCsv = () => window.open('/balances/export', '_blank');

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
</script>

<template>
    <Head title="Balances" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex h-full flex-1 flex-col gap-4 p-4">
            <div class="flex items-end justify-between gap-4">
                <div>
                    <h1 class="text-xl font-semibold">Balances</h1>
                    <p class="text-sm text-muted-foreground">{{ props.balances.length }} customers owe</p>
                </div>
                <div class="flex items-end gap-3">
                    <Button v-if="props.canManage" size="sm" variant="outline" @click="openCharge"><Plus class="mr-1 size-4" /> Charge</Button>
                    <Button v-if="props.canManage" size="sm" variant="outline" @click="exportCsv"><Download class="mr-1 size-4" /> Export</Button>
                    <div class="text-right">
                        <div class="text-2xl font-semibold">{{ money(props.total) }}</div>
                        <div class="text-sm text-muted-foreground">outstanding</div>
                    </div>
                </div>
            </div>

            <div class="overflow-hidden rounded-xl border border-border">
                <table class="w-full text-sm">
                    <thead class="bg-muted/50 text-left text-muted-foreground">
                        <tr>
                            <th class="px-4 py-2 font-medium">Customer</th>
                            <th class="hidden px-4 py-2 font-medium md:table-cell">Pools</th>
                            <th class="px-4 py-2 text-right font-medium">Balance</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr
                            v-for="row in props.balances"
                            :key="row.id"
                            class="cursor-pointer border-t border-border transition-colors hover:bg-muted/40"
                            :class="{ 'bg-muted/60': props.selected?.id === row.id }"
                            @click="open(row.id)"
                        >
                            <td class="px-4 py-2.5 font-medium">{{ row.name }}</td>
                            <td class="hidden px-4 py-2.5 text-muted-foreground md:table-cell">{{ row.pools }}</td>
                            <td class="px-4 py-2.5 text-right font-medium">{{ money(row.balance) }}</td>
                        </tr>
                        <tr v-if="props.balances.length === 0">
                            <td colspan="3" class="px-4 py-10 text-center text-muted-foreground">
                                <CheckCircle2 class="mx-auto mb-2 size-6 opacity-50" />
                                Everyone's paid up.
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <Sheet :open="props.selected !== null && !chargeOpen" @update:open="(o: boolean) => !o && closeDrawer()">
                <SheetContent class="w-full overflow-y-auto sm:max-w-md">
                    <template v-if="props.selected">
                        <SheetHeader>
                            <SheetTitle>{{ props.selected.name }}</SheetTitle>
                            <SheetDescription>{{ money(props.selected.total) }} outstanding</SheetDescription>
                        </SheetHeader>

                        <div class="mt-4 space-y-5 text-sm">
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
                                    <li v-for="inv in props.selected.invoices" :key="inv.id" class="flex justify-between">
                                        <a :href="`/invoices/${inv.id}/pdf`" target="_blank" class="text-foreground hover:underline"
                                            >{{ inv.number }} <span class="text-xs capitalize text-muted-foreground">· {{ inv.status }}</span></a
                                        >
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
                    </template>
                </SheetContent>
            </Sheet>

            <!-- add manual charge -->
            <Sheet v-model:open="chargeOpen">
                <SheetContent class="w-full overflow-y-auto sm:max-w-md">
                    <SheetHeader>
                        <SheetTitle>Add a charge</SheetTitle>
                        <SheetDescription>An ad-hoc charge raises the customer's balance.</SheetDescription>
                    </SheetHeader>
                    <form class="mt-4 space-y-4 text-sm" @submit.prevent="submitCharge">
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
                </SheetContent>
            </Sheet>
        </div>
    </AppLayout>
</template>
