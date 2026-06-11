<script setup lang="ts">
import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem } from '@/types';
import { Head, useForm } from '@inertiajs/vue3';
import { CheckCircle2, CreditCard } from 'lucide-vue-next';

const props = defineProps<{
    total: number;
    visits: { pool: string; date: string; price: number }[];
    charges: { description: string; amount: number }[];
    can_pay: boolean;
    paid: boolean;
}>();

const breadcrumbs: BreadcrumbItem[] = [{ title: 'Balance', href: '/balance' }];
const money = (n: number) => `$${n.toFixed(2)}`;

const payForm = useForm({});
const pay = () => payForm.post('/balance/pay', { preserveScroll: true });
</script>

<template>
    <Head title="Balance" />

    <AppLayout :breadcrumbs="breadcrumbs" :meta="`${money(props.total)} due`">
        <div class="mx-auto flex w-full max-w-2xl flex-1 flex-col gap-4 p-4">
            <div
                v-if="props.paid"
                class="flex items-center gap-2 rounded-xl border border-emerald-500/30 bg-emerald-500/10 p-3 text-sm text-emerald-700 dark:text-emerald-400"
            >
                <CheckCircle2 class="size-5" /> Payment received — thank you!
            </div>

            <div class="rounded-xl border border-border p-5">
                <div class="text-sm text-muted-foreground">Current balance</div>
                <div class="mt-1 text-3xl font-semibold" :class="props.total > 0 ? '' : 'text-emerald-600 dark:text-emerald-400'">
                    {{ money(props.total) }}
                </div>
                <Button v-if="props.total > 0 && props.can_pay" class="mt-4" :disabled="payForm.processing" @click="pay">
                    <CreditCard class="mr-1 size-4" /> Pay with card
                </Button>
                <p v-else-if="props.total > 0 && !props.can_pay" class="mt-3 text-sm text-muted-foreground">
                    Online payment isn't set up yet — please contact your service company.
                </p>
                <p v-else class="mt-3 text-sm text-muted-foreground">You're all paid up. Thank you!</p>
            </div>

            <div v-if="props.visits.length" class="overflow-hidden rounded-xl border border-border">
                <h2 class="border-b border-border px-4 py-2 font-medium">Recent service</h2>
                <ul class="divide-y divide-border text-sm">
                    <li v-for="(v, i) in props.visits" :key="i" class="flex justify-between px-4 py-2.5">
                        <span class="text-muted-foreground">{{ v.pool }} · {{ v.date }}</span>
                        <span>{{ money(v.price) }}</span>
                    </li>
                </ul>
            </div>

            <div v-if="props.charges.length" class="overflow-hidden rounded-xl border border-border">
                <h2 class="border-b border-border px-4 py-2 font-medium">Other charges</h2>
                <ul class="divide-y divide-border text-sm">
                    <li v-for="(c, i) in props.charges" :key="i" class="flex justify-between px-4 py-2.5">
                        <span class="text-muted-foreground">{{ c.description }}</span>
                        <span>{{ money(c.amount) }}</span>
                    </li>
                </ul>
            </div>
        </div>
    </AppLayout>
</template>
