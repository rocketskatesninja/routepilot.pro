<script setup lang="ts">
import { Button } from '@/components/ui/button';
import { formatMoney } from '@/lib/utils';
import { type SharedData } from '@/types';
import { Link, useForm, usePage } from '@inertiajs/vue3';
import { CheckCircle2, CreditCard } from 'lucide-vue-next';
import { computed } from 'vue';

// `can_pay`: online payment is set up, so Pay goes straight to checkout (as on the Balance page).
defineProps<{ data: { balance: number; autopay: boolean; can_pay: boolean } }>();

const company = computed(() => usePage<SharedData>().props.tenant?.name ?? 'your pool company');
const pay = useForm({});
const payNow = () => pay.post('/balance/pay', { preserveScroll: true });
</script>

<template>
    <div class="flex h-full flex-col items-center justify-center gap-2 text-center">
        <template v-if="data.balance > 0">
            <div>
                <p class="text-3xl font-semibold tabular-nums">{{ formatMoney(data.balance) }}</p>
                <p class="text-muted-foreground text-xs">balance due</p>
            </div>
            <p v-if="data.autopay" class="text-sm">Autopay will pay this automatically.</p>
            <Button v-else-if="data.can_pay" :disabled="pay.processing" @click="payNow">
                <CreditCard class="mr-1.5 size-4" /> Pay {{ formatMoney(data.balance) }}
            </Button>
            <p v-else class="text-muted-foreground text-sm">Online payment isn’t set up yet. Please pay {{ company }} the usual way.</p>
            <Link href="/balance" class="text-primary text-xs font-medium hover:underline">See what it’s for</Link>
        </template>
        <template v-else>
            <CheckCircle2 class="size-7 text-emerald-600 dark:text-emerald-400" />
            <p class="font-medium">All paid up<span v-if="data.autopay" class="text-muted-foreground font-normal"> · autopay on</span></p>
            <Link href="/balance" class="text-primary text-xs font-medium hover:underline">Your payments</Link>
        </template>
    </div>
</template>
