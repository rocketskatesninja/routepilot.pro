<script setup lang="ts">
import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/AppLayout.vue';
import { cap } from '@/lib/portal';
import { formatDay, formatMoney } from '@/lib/utils';
import { type BreadcrumbItem, type SharedData } from '@/types';
import { Head, useForm, usePage } from '@inertiajs/vue3';
import { CheckCircle2, CreditCard } from 'lucide-vue-next';
import { computed } from 'vue';

const props = defineProps<{
    total: number;
    visits: { service: string; pool: string; date: string; price: number }[];
    charges: { description: string; amount: number }[];
    payments: { amount: number; on: string | null; method: string }[];
    can_pay: boolean;
    paid: boolean;
    autopay: boolean;
    card: { brand: string | null; last4: string | null } | null;
}>();

const breadcrumbs: BreadcrumbItem[] = [{ title: 'Balance', href: '/balance' }];
const page = usePage<SharedData>();
const company = computed(() => page.props.tenant?.name ?? 'your pool company');

const payForm = useForm({});
const pay = () => payForm.post('/balance/pay', { preserveScroll: true });
const setupForm = useForm({});
const setupAutopay = () => setupForm.post('/autopay/setup', { preserveScroll: true });
const disableForm = useForm({});
const disableAutopay = () => disableForm.post('/autopay/disable', { preserveScroll: true });

const cardName = computed(() => (props.card?.brand ? cap(props.card.brand) : 'Card'));
const METHODS: Record<string, string> = { card: 'Card', cash: 'Cash', check: 'Check', ach: 'Bank transfer', other: 'Payment' };
</script>

<template>
    <Head title="Balance" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="mx-auto flex w-full max-w-3xl flex-1 flex-col gap-10 px-4 py-6">
            <div
                v-if="props.paid"
                class="flex items-center gap-2 rounded-2xl border border-emerald-500/30 bg-emerald-500/10 p-4 text-sm text-emerald-700 dark:text-emerald-400"
                role="status"
            >
                <CheckCircle2 class="size-5" /> Payment received. Thank you!
            </div>

            <!-- what they owe, and paying it -->
            <section class="rounded-2xl bg-[hsl(var(--brand)/0.08)] px-6 py-7 sm:px-8">
                <template v-if="props.total > 0">
                    <h1 class="text-muted-foreground text-[15px] font-normal">You owe {{ company }}</h1>
                    <p class="mt-1 text-5xl font-semibold tracking-tight">{{ formatMoney(props.total) }}</p>
                    <div class="mt-5 flex flex-wrap items-center gap-3">
                        <Button v-if="props.can_pay && !props.autopay" size="lg" :disabled="payForm.processing" @click="pay">
                            <CreditCard class="mr-1.5 size-4" /> Pay {{ formatMoney(props.total) }}
                        </Button>
                        <p v-else-if="props.autopay" class="text-sm">Autopay will pay this automatically. You don’t need to do anything.</p>
                        <p v-else class="text-sm">Online payment isn’t set up yet. Please pay {{ company }} the usual way.</p>
                    </div>
                </template>
                <template v-else>
                    <h1 class="font-display text-[1.75rem] leading-tight font-semibold tracking-tight sm:text-[2.125rem]">You’re all paid up.</h1>
                    <p class="text-muted-foreground mt-2 text-[15px]">Thank you! New visits show up here after they’re done.</p>
                </template>
            </section>

            <!-- autopay -->
            <section v-if="props.can_pay" class="border-border flex flex-wrap items-center gap-4 rounded-2xl border p-5">
                <div class="min-w-0 flex-1">
                    <h2 class="font-medium">Autopay {{ props.autopay ? 'is on' : 'is off' }}</h2>
                    <p class="text-muted-foreground text-sm">
                        <template v-if="props.autopay && props.card"
                            >Paying automatically with your {{ cardName }} ending in {{ props.card.last4 }}.</template
                        >
                        <template v-else>Save a card and your balance is paid automatically each billing cycle.</template>
                    </p>
                </div>
                <Button v-if="!props.autopay" variant="outline" :disabled="setupForm.processing" @click="setupAutopay">Turn on autopay</Button>
                <Button v-else variant="outline" :disabled="disableForm.processing" @click="disableAutopay">Turn off autopay</Button>
            </section>

            <!-- what the balance is made of -->
            <section v-if="props.visits.length || props.charges.length" aria-labelledby="owed">
                <h2 id="owed" class="font-display text-xl font-semibold tracking-tight">What you owe for</h2>
                <ul class="border-border divide-border mt-4 divide-y rounded-2xl border">
                    <li v-for="(v, i) in props.visits" :key="`v${i}`" class="flex items-baseline justify-between gap-4 p-4 sm:px-5">
                        <span class="min-w-0">
                            <span class="block font-medium">{{ v.service }}</span>
                            <span class="text-muted-foreground text-sm">{{ formatDay(v.date) }}, {{ v.pool }}</span>
                        </span>
                        <span class="tabular-nums">{{ formatMoney(v.price) }}</span>
                    </li>
                    <li v-for="(c, i) in props.charges" :key="`c${i}`" class="flex items-baseline justify-between gap-4 p-4 sm:px-5">
                        <span class="font-medium">{{ c.description }}</span>
                        <span class="tabular-nums">{{ formatMoney(c.amount) }}</span>
                    </li>
                    <li class="flex items-baseline justify-between gap-4 p-4 sm:px-5">
                        <span class="font-semibold">Total</span>
                        <span class="font-semibold tabular-nums">{{ formatMoney(props.total) }}</span>
                    </li>
                </ul>
            </section>

            <!-- recent payments -->
            <section v-if="props.payments.length" aria-labelledby="paid">
                <h2 id="paid" class="font-display text-xl font-semibold tracking-tight">Recent payments</h2>
                <ul class="border-border divide-border mt-4 divide-y rounded-2xl border">
                    <li v-for="(p, i) in props.payments" :key="i" class="flex items-baseline justify-between gap-4 p-4 sm:px-5">
                        <span class="min-w-0">
                            <span class="block font-medium">{{ formatDay(p.on) }}</span>
                            <span class="text-muted-foreground text-sm">{{ METHODS[p.method] ?? 'Payment' }}</span>
                        </span>
                        <span class="tabular-nums">{{ formatMoney(p.amount) }}</span>
                    </li>
                </ul>
            </section>
        </div>
    </AppLayout>
</template>
