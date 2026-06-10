<script setup lang="ts">
import { Input } from '@/components/ui/input';
import { Sheet, SheetContent, SheetDescription, SheetHeader, SheetTitle } from '@/components/ui/sheet';
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem } from '@/types';
import { Head, router } from '@inertiajs/vue3';
import { FlaskConical } from 'lucide-vue-next';
import { ref, watch } from 'vue';

interface InventoryRow {
    id: number;
    name: string;
    unit: string;
    stock: number;
    low: boolean;
    cost_per_unit: number | null;
}

interface InventoryDetail {
    id: number;
    name: string;
    unit: string;
    stock: number;
    reorder_threshold: number | null;
    cost_per_unit: number | null;
    sell_price: number | null;
    supplier: string | null;
    value: number | null;
    low: boolean;
    transactions: { id: number; type: string; quantity: number; on: string | null; agent: string | null }[];
}

const props = defineProps<{
    items: { data: InventoryRow[]; total: number };
    selected: InventoryDetail | null;
    filters: { search: string };
}>();

const breadcrumbs: BreadcrumbItem[] = [{ title: 'Inventory', href: '/inventory' }];

const search = ref(props.filters.search);
let timer: ReturnType<typeof setTimeout> | undefined;
watch(search, (value) => {
    clearTimeout(timer);
    timer = setTimeout(() => router.get('/inventory', { search: value || undefined }, { preserveState: true, replace: true, preserveScroll: true }), 300);
});

const open = (id: number) => router.get('/inventory', { search: search.value || undefined, selected: id }, { preserveState: true, preserveScroll: true });
const closeDrawer = () => router.get('/inventory', { search: search.value || undefined }, { preserveState: true, preserveScroll: true });
const money = (n: number) => `$${n.toFixed(2)}`;
</script>

<template>
    <Head title="Inventory" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex h-full flex-1 flex-col gap-4 p-4">
            <div class="flex items-center justify-between gap-4">
                <div>
                    <h1 class="text-xl font-semibold">Inventory</h1>
                    <p class="text-sm text-muted-foreground">{{ props.items.total }} chemicals</p>
                </div>
                <Input v-model="search" type="search" placeholder="Search chemicals…" class="max-w-xs" />
            </div>

            <div class="overflow-hidden rounded-xl border border-border">
                <table class="w-full text-sm">
                    <thead class="bg-muted/50 text-left text-muted-foreground">
                        <tr>
                            <th class="px-4 py-2 font-medium">Chemical</th>
                            <th class="px-4 py-2 font-medium">In stock</th>
                            <th class="px-4 py-2 font-medium">Status</th>
                            <th class="hidden px-4 py-2 font-medium md:table-cell">Cost</th>
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
                            <td class="px-4 py-2.5 font-medium">{{ item.name }}</td>
                            <td class="px-4 py-2.5 text-muted-foreground">{{ item.stock }} {{ item.unit }}</td>
                            <td class="px-4 py-2.5">
                                <span
                                    class="inline-flex rounded-full px-2 py-0.5 text-xs font-medium"
                                    :class="item.low ? 'bg-red-500/15 text-red-600 dark:text-red-400' : 'bg-emerald-500/15 text-emerald-600 dark:text-emerald-400'"
                                >
                                    {{ item.low ? 'Low' : 'OK' }}
                                </span>
                            </td>
                            <td class="hidden px-4 py-2.5 text-muted-foreground md:table-cell">{{ item.cost_per_unit !== null ? money(item.cost_per_unit) + '/' + item.unit : '—' }}</td>
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

            <Sheet :open="props.selected !== null" @update:open="(o: boolean) => !o && closeDrawer()">
                <SheetContent class="w-full overflow-y-auto sm:max-w-md">
                    <template v-if="props.selected">
                        <SheetHeader>
                            <SheetTitle>{{ props.selected.name }}</SheetTitle>
                            <SheetDescription>{{ props.selected.stock }} {{ props.selected.unit }} in stock</SheetDescription>
                        </SheetHeader>

                        <div class="mt-4 space-y-5 text-sm">
                            <dl class="space-y-1 text-muted-foreground">
                                <div class="flex justify-between"><dt>Reorder at</dt><dd>{{ props.selected.reorder_threshold ?? '—' }} {{ props.selected.unit }}</dd></div>
                                <div class="flex justify-between"><dt>Cost</dt><dd>{{ props.selected.cost_per_unit !== null ? money(props.selected.cost_per_unit) : '—' }}</dd></div>
                                <div class="flex justify-between"><dt>Sell price</dt><dd>{{ props.selected.sell_price !== null ? money(props.selected.sell_price) : '—' }}</dd></div>
                                <div class="flex justify-between"><dt>Stock value</dt><dd>{{ props.selected.value !== null ? money(props.selected.value) : '—' }}</dd></div>
                                <div v-if="props.selected.supplier" class="flex justify-between"><dt>Supplier</dt><dd>{{ props.selected.supplier }}</dd></div>
                            </dl>

                            <section v-if="props.selected.transactions.length">
                                <h3 class="mb-1 font-medium">Recent movement</h3>
                                <ul class="space-y-1 text-muted-foreground">
                                    <li v-for="t in props.selected.transactions" :key="t.id" class="flex justify-between">
                                        <span class="capitalize">{{ t.type }} · {{ t.quantity }} {{ props.selected.unit }}</span>
                                        <span>{{ t.on }}</span>
                                    </li>
                                </ul>
                            </section>
                        </div>
                    </template>
                </SheetContent>
            </Sheet>
        </div>
    </AppLayout>
</template>
