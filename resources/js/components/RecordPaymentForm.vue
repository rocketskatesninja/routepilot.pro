<script setup lang="ts">
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { formatMoney } from '@/lib/utils';
import { useForm } from '@inertiajs/vue3';
import { computed, watch } from 'vue';

/**
 * Record a payment the office took (cash, a check…): any amount up to what's
 * owed, filled in with all of it. Posts to `url`; the panel then shows the new
 * balance and the payment in its history.
 */
const props = defineProps<{ url: string; owed: number }>();

const METHODS: Record<string, string> = { cash: 'Cash', check: 'Check', card: 'Card', ach: 'Bank transfer', other: 'Other' };
const form = useForm({ method: 'cash', amount: props.owed.toFixed(2) });

// Another customer or invoice, or a new balance after a payment: start from the full amount again.
watch(
    () => [props.url, props.owed],
    () => {
        form.amount = props.owed.toFixed(2);
        form.clearErrors();
    },
);

const amount = computed(() => Number(form.amount));
const isPartial = computed(() => amount.value > 0 && amount.value < props.owed - 0.005);
const label = computed(() =>
    !(amount.value > 0) ? 'Record payment' : isPartial.value ? `Record ${formatMoney(amount.value)}` : 'Mark paid in full',
);
const submit = () => form.post(props.url, { preserveScroll: true });
</script>

<template>
    <form class="space-y-1.5" @submit.prevent="submit">
        <div class="flex flex-wrap items-center gap-2">
            <select v-model="form.method" aria-label="Paid by" class="border-input bg-background h-9 w-36 rounded-md border px-2 text-sm">
                <option v-for="(name, key) in METHODS" :key="key" :value="key">{{ name }}</option>
            </select>
            <div class="relative">
                <span class="text-muted-foreground pointer-events-none absolute top-1/2 left-2.5 -translate-y-1/2 text-sm">$</span>
                <Input
                    v-model="form.amount"
                    type="number"
                    inputmode="decimal"
                    min="0.01"
                    step="0.01"
                    :max="owed"
                    aria-label="Amount paid"
                    class="h-9 w-28 pl-6 tabular-nums"
                />
            </div>
            <Button type="submit" size="sm" :disabled="form.processing || !(amount > 0)">{{ label }}</Button>
        </div>
        <p v-if="form.errors.amount" class="text-xs text-red-600 dark:text-red-400">{{ form.errors.amount }}</p>
        <p v-else-if="isPartial" class="text-muted-foreground text-xs">{{ formatMoney(owed - amount) }} will still be owed.</p>
    </form>
</template>
