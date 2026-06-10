<script setup lang="ts">
import { Sheet, SheetContent, SheetDescription, SheetHeader, SheetTitle } from '@/components/ui/sheet';
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem } from '@/types';
import { Head, router } from '@inertiajs/vue3';
import { CheckCircle2 } from 'lucide-vue-next';

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
}

const props = defineProps<{
    balances: BalanceRow[];
    total: number;
    selected: BalanceDetail | null;
}>();

const breadcrumbs: BreadcrumbItem[] = [{ title: 'Balances', href: '/balances' }];

const open = (id: number) => router.get('/balances', { selected: id }, { preserveState: true, preserveScroll: true });
const closeDrawer = () => router.get('/balances', {}, { preserveState: true, preserveScroll: true });
const money = (n: number) => `$${n.toFixed(2)}`;
</script>

<template>
    <Head title="Balances" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex h-full flex-1 flex-col gap-4 p-4">
            <div class="flex items-end justify-between">
                <div>
                    <h1 class="text-xl font-semibold">Balances</h1>
                    <p class="text-sm text-muted-foreground">{{ props.balances.length }} customers owe</p>
                </div>
                <div class="text-right">
                    <div class="text-2xl font-semibold">{{ money(props.total) }}</div>
                    <div class="text-sm text-muted-foreground">outstanding</div>
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

            <Sheet :open="props.selected !== null" @update:open="(o: boolean) => !o && closeDrawer()">
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
                                        <span>{{ v.pool }} <span class="text-xs">· {{ v.date }}</span></span>
                                        <span>{{ money(v.price) }}</span>
                                    </li>
                                </ul>
                            </section>

                            <section v-if="props.selected.charges.length">
                                <h3 class="mb-1 font-medium">Manual charges</h3>
                                <ul class="space-y-1 text-muted-foreground">
                                    <li v-for="(c, i) in props.selected.charges" :key="i" class="flex justify-between">
                                        <span>{{ c.description }}</span><span>{{ money(c.amount) }}</span>
                                    </li>
                                </ul>
                            </section>

                            <div class="flex justify-between border-t border-border pt-2 font-medium">
                                <span>Total</span><span>{{ money(props.selected.total) }}</span>
                            </div>
                            <p class="text-xs text-muted-foreground">Recording payments and sending invoices arrive with the payments flow.</p>
                        </div>
                    </template>
                </SheetContent>
            </Sheet>
        </div>
    </AppLayout>
</template>
