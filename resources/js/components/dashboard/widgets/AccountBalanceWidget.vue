<script setup lang="ts">
import { formatMoney } from '@/lib/utils';
import { Link } from '@inertiajs/vue3';

defineProps<{ data: { balance: number; autopay: boolean } }>();
</script>

<template>
    <div class="flex h-full flex-col items-center justify-center gap-1 text-center">
        <div class="text-3xl font-semibold tabular-nums" :class="data.balance > 0 ? '' : 'text-emerald-600 dark:text-emerald-400'">
            {{ formatMoney(data.balance) }}
        </div>
        <div class="text-xs text-muted-foreground">
            {{ data.balance > 0 ? 'balance due' : 'all paid up' }}<span v-if="data.autopay"> · autopay on</span>
        </div>
        <Link v-if="data.balance > 0" href="/balance" class="mt-1 text-xs font-medium text-primary hover:underline">Pay now →</Link>
    </div>
</template>
