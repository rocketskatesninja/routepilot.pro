<script setup lang="ts">
import BarChart from '@/components/charts/BarChart.vue';
import LineChart from '@/components/charts/LineChart.vue';
import Sparkline from '@/components/charts/Sparkline.vue';
import { useCustomerBilling } from '@/composables/useCustomerBilling';
import AppLayout from '@/layouts/AppLayout.vue';
import { formatMoney } from '@/lib/utils';
import { type BreadcrumbItem } from '@/types';
import { Head } from '@inertiajs/vue3';
import { computed } from 'vue';

type Point = { label: string; value: number };

const props = defineProps<{
    revenue_month: number;
    outstanding: number;
    overdue_invoices: number;
    visits_month: number;
    visits_week: number;
    active_pools: number;
    active_agents: number;
    top_agents: { name: string; visits: number }[];
    revenue_series: Point[];
    visits_series: Point[];
    ar_aging: Point[];
}>();

const breadcrumbs: BreadcrumbItem[] = [{ title: 'Insights', href: '/insights' }];
const money = formatMoney;
const compactMoney = (v: number) => (Math.abs(v) >= 1000 ? `$${(v / 1000).toFixed(1)}k` : `$${Math.round(v)}`);

const revenueValues = computed(() => props.revenue_series.map((p) => p.value));
const revenueLabels = computed(() => props.revenue_series.map((p) => p.label));
const visitValues = computed(() => props.visits_series.map((p) => p.value));
const visitLabels = computed(() => props.visits_series.map((p) => p.label));

// Aging severity ramp: balanced → overdue reads green → red.
const AR_COLORS = ['hsl(160 84% 39%)', 'hsl(43 80% 50%)', 'hsl(38 92% 50%)', 'hsl(24 95% 53%)', 'hsl(0 84% 60%)'];
const arBars = computed(() => props.ar_aging.map((b, i) => ({ label: b.label, value: b.value, color: AR_COLORS[i] ?? AR_COLORS[0] })));
const techBars = computed(() => props.top_agents.map((a) => ({ label: a.name, value: a.visits })));

const billing = useCustomerBilling();
const moneyCards = computed(() => [
    {
        label: 'Revenue this month',
        value: money(props.revenue_month),
        accent: 'text-emerald-600 dark:text-emerald-400',
        series: revenueValues.value,
        spark: 'hsl(var(--chart-1))',
    },
    { label: 'Outstanding AR', value: money(props.outstanding), accent: props.outstanding > 0 ? 'text-amber-600 dark:text-amber-400' : '' },
    {
        label: 'Overdue invoices',
        value: String(props.overdue_invoices),
        accent: props.overdue_invoices > 0 ? 'text-red-600 dark:text-red-400' : '',
    },
]);
const cards = computed(() => [
    ...(billing.value ? moneyCards.value : []),
    { label: 'Visits this month', value: String(props.visits_month), accent: '', series: visitValues.value, spark: 'hsl(var(--chart-2))' },
    { label: 'Visits this week', value: String(props.visits_week), accent: '' },
    { label: 'Active pools', value: String(props.active_pools), accent: '' },
    { label: 'Active agents', value: String(props.active_agents), accent: '' },
]);
</script>

<template>
    <Head title="Insights" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex h-full flex-1 flex-col p-4">
            <div class="flex flex-1 xl:min-h-0">
                <!-- metrics + charts -->
                <div class="flex flex-1 flex-col gap-4 xl:min-h-0 xl:overflow-y-auto xl:pr-1">
                    <div class="grid grid-cols-2 gap-3 sm:grid-cols-3">
                        <div v-for="c in cards" :key="c.label" class="border-border flex flex-col rounded-xl border p-4">
                            <div class="text-2xl font-semibold tabular-nums" :class="c.accent">{{ c.value }}</div>
                            <div class="text-muted-foreground text-sm">{{ c.label }}</div>
                            <Sparkline v-if="c.series && c.series.length > 1" :values="c.series" :color="c.spark" class="mt-2" />
                        </div>
                    </div>

                    <div class="grid gap-4 lg:grid-cols-2">
                        <div v-if="billing" class="border-border rounded-xl border p-4">
                            <h2 class="mb-3 text-sm font-medium">Revenue · last 12 months</h2>
                            <LineChart :values="revenueValues" :labels="revenueLabels" :format="compactMoney" color="hsl(var(--chart-1))" />
                        </div>
                        <div class="border-border rounded-xl border p-4">
                            <h2 class="mb-3 text-sm font-medium">Completed visits · last 12 weeks</h2>
                            <LineChart :values="visitValues" :labels="visitLabels" :format="(v) => String(v)" color="hsl(var(--chart-2))" />
                        </div>
                        <div v-if="billing" class="border-border rounded-xl border p-4">
                            <h2 class="mb-3 text-sm font-medium">Accounts receivable · aging</h2>
                            <BarChart :bars="arBars" :format="money" empty-text="Nothing outstanding." />
                        </div>
                        <div class="border-border rounded-xl border p-4">
                            <h2 class="mb-3 text-sm font-medium">Top techs · this month</h2>
                            <BarChart
                                :bars="techBars"
                                :format="(v) => `${v} visit${v === 1 ? '' : 's'}`"
                                color="hsl(var(--chart-3))"
                                empty-text="No completed visits this month."
                            />
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
