<script setup lang="ts">
import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/AppLayout.vue';
import { formatMoney } from '@/lib/utils';
import { type BreadcrumbItem } from '@/types';
import { Head, useForm } from '@inertiajs/vue3';
import { CheckCircle2, CreditCard } from 'lucide-vue-next';

const props = defineProps<{
    total: number;
    visits: { pool: string; date: string; price: number }[];
    charges: { description: string; amount: number }[];
    can_pay: boolean;
    paid: boolean;
    autopay: boolean;
    card: { brand: string | null; last4: string | null } | null;
}>();

const breadcrumbs: BreadcrumbItem[] = [{ title: 'Balance', href: '/balance' }];
const money = formatMoney;

const payForm = useForm({});
const pay = () => payForm.post('/balance/pay', { preserveScroll: true });
const setupForm = useForm({});
const setupAutopay = () => setupForm.post('/autopay/setup', { preserveScroll: true });
const disableForm = useForm({});
const disableAutopay = () => disableForm.post('/autopay/disable', { preserveScroll: true });
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

            <div class="border-border rounded-xl border p-5">
                <div class="text-muted-foreground text-sm">Current balance</div>
                <div class="mt-1 text-3xl font-semibold" :class="props.total > 0 ? '' : 'text-emerald-600 dark:text-emerald-400'">
                    {{ money(props.total) }}
                </div>
                <Button v-if="props.total > 0 && props.can_pay" class="mt-4" :disabled="payForm.processing" @click="pay">
                    <CreditCard class="mr-1 size-4" /> Pay with card
                </Button>
                <p v-else-if="props.total > 0 && !props.can_pay" class="text-muted-foreground mt-3 text-sm">
                    Online payment isn't set up yet — please contact your service company.
                </p>
                <p v-else class="text-muted-foreground mt-3 text-sm">You're all paid up. Thank you!</p>
            </div>

            <div v-if="props.can_pay" class="border-border rounded-xl border p-5">
                <div class="flex items-center justify-between gap-3">
                    <div>
                        <div class="font-medium">Autopay</div>
                        <p v-if="props.autopay && props.card" class="text-muted-foreground text-sm">
                            On · <span class="capitalize">{{ props.card.brand }}</span> ending {{ props.card.last4 }}
                        </p>
                        <p v-else class="text-muted-foreground text-sm">Save a card and we'll automatically pay your balance each cycle.</p>
                    </div>
                    <Button v-if="!props.autopay" size="sm" variant="outline" :disabled="setupForm.processing" @click="setupAutopay"
                        >Set up autopay</Button
                    >
                    <Button v-else size="sm" variant="outline" :disabled="disableForm.processing" @click="disableAutopay">Turn off</Button>
                </div>
            </div>

            <div v-if="props.visits.length" class="border-border overflow-hidden rounded-xl border">
                <h2 class="border-border border-b px-4 py-2 font-medium">Recent service</h2>
                <ul class="divide-border divide-y text-sm">
                    <li v-for="(v, i) in props.visits" :key="i" class="flex justify-between px-4 py-2.5">
                        <span class="text-muted-foreground">{{ v.pool }} · {{ v.date }}</span>
                        <span>{{ money(v.price) }}</span>
                    </li>
                </ul>
            </div>

            <div v-if="props.charges.length" class="border-border overflow-hidden rounded-xl border">
                <h2 class="border-border border-b px-4 py-2 font-medium">Other charges</h2>
                <ul class="divide-border divide-y text-sm">
                    <li v-for="(c, i) in props.charges" :key="i" class="flex justify-between px-4 py-2.5">
                        <span class="text-muted-foreground">{{ c.description }}</span>
                        <span>{{ money(c.amount) }}</span>
                    </li>
                </ul>
            </div>
        </div>
    </AppLayout>
</template>
