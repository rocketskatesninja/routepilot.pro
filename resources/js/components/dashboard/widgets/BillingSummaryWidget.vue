<script setup lang="ts">
interface Row {
    customer: string;
    balance: number;
}

defineProps<{ data: { outstanding_total: number; customer_count: number; top: Row[] } }>();

const money = (n: number) => new Intl.NumberFormat('en-US', { style: 'currency', currency: 'USD' }).format(n);
</script>

<template>
    <div class="flex h-full flex-col gap-2">
        <div class="shrink-0">
            <div class="text-2xl font-semibold tabular-nums">{{ money(data.outstanding_total) }}</div>
            <div class="text-xs text-muted-foreground">outstanding · {{ data.customer_count }} customer(s)</div>
        </div>
        <ul v-if="data.top.length" class="flex-1 divide-y divide-border overflow-y-auto text-sm">
            <li v-for="(r, i) in data.top" :key="i" class="flex items-center justify-between gap-2 px-1 py-2">
                <span class="truncate">{{ r.customer }}</span>
                <span class="shrink-0 font-medium tabular-nums">{{ money(r.balance) }}</span>
            </li>
        </ul>
        <div v-else class="flex flex-1 items-center justify-center text-center text-sm text-muted-foreground">
            All caught up — no outstanding balances.
        </div>
    </div>
</template>
