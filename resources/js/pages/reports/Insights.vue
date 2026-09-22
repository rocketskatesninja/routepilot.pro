<script setup lang="ts">
import BarChart from '@/components/charts/BarChart.vue';
import LineChart from '@/components/charts/LineChart.vue';
import Sparkline from '@/components/charts/Sparkline.vue';
import AppLayout from '@/layouts/AppLayout.vue';
import { formatMoney } from '@/lib/utils';
import { type BreadcrumbItem } from '@/types';
import { Head, router } from '@inertiajs/vue3';
import { UserPlus } from 'lucide-vue-next';
import { computed } from 'vue';

interface LeadRow {
    id: number;
    name: string;
    email: string | null;
    phone: string | null;
    message: string | null;
    source: string;
    status: string;
    on: string | null;
}
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
    leads: LeadRow[];
    new_leads: number;
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

const cards = computed(() => [
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
    { label: 'Visits this month', value: String(props.visits_month), accent: '', series: visitValues.value, spark: 'hsl(var(--chart-2))' },
    { label: 'Visits this week', value: String(props.visits_week), accent: '' },
    { label: 'Active pools', value: String(props.active_pools), accent: '' },
    { label: 'Active agents', value: String(props.active_agents), accent: '' },
]);

const statuses = ['new', 'contacted', 'converted', 'archived'];
const statusClass = (s: string) =>
    s === 'converted'
        ? 'text-emerald-600 dark:text-emerald-400'
        : s === 'archived'
          ? 'text-muted-foreground'
          : s === 'contacted'
            ? 'text-sky-600 dark:text-sky-400'
            : 'text-amber-600 dark:text-amber-400';
const setStatus = (lead: LeadRow, status: string) => router.patch(`/leads/${lead.id}`, { status }, { preserveScroll: true });
</script>

<template>
    <Head title="Insights" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex h-full flex-1 flex-col p-4">
            <div class="grid flex-1 gap-4 xl:min-h-0 xl:grid-cols-5">
                <!-- metrics + charts -->
                <div class="flex flex-col gap-4 xl:col-span-3 xl:min-h-0 xl:overflow-y-auto xl:pr-1">
                    <div class="grid grid-cols-2 gap-3 sm:grid-cols-3">
                        <div v-for="c in cards" :key="c.label" class="flex flex-col rounded-xl border border-border p-4">
                            <div class="text-2xl font-semibold tabular-nums" :class="c.accent">{{ c.value }}</div>
                            <div class="text-sm text-muted-foreground">{{ c.label }}</div>
                            <Sparkline v-if="c.series && c.series.length > 1" :values="c.series" :color="c.spark" class="mt-2" />
                        </div>
                    </div>

                    <div class="grid gap-4 lg:grid-cols-2">
                        <div class="rounded-xl border border-border p-4">
                            <h2 class="mb-3 text-sm font-medium">Revenue · last 12 months</h2>
                            <LineChart :values="revenueValues" :labels="revenueLabels" :format="compactMoney" color="hsl(var(--chart-1))" />
                        </div>
                        <div class="rounded-xl border border-border p-4">
                            <h2 class="mb-3 text-sm font-medium">Completed visits · last 12 weeks</h2>
                            <LineChart :values="visitValues" :labels="visitLabels" :format="(v) => String(v)" color="hsl(var(--chart-2))" />
                        </div>
                        <div class="rounded-xl border border-border p-4">
                            <h2 class="mb-3 text-sm font-medium">Accounts receivable · aging</h2>
                            <BarChart :bars="arBars" :format="money" empty-text="Nothing outstanding." />
                        </div>
                        <div class="rounded-xl border border-border p-4">
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

                <!-- leads inbox -->
                <div class="flex min-h-0 flex-col overflow-hidden rounded-xl border border-border xl:col-span-2">
                    <h2 class="flex items-center justify-between border-b border-border px-4 py-2 font-medium">
                        <span>Leads</span>
                        <span
                            v-if="props.new_leads"
                            class="rounded-full bg-amber-500/15 px-2 py-0.5 text-xs font-medium text-amber-600 dark:text-amber-400"
                            >{{ props.new_leads }} new</span
                        >
                    </h2>
                    <ul class="min-h-0 flex-1 divide-y divide-border overflow-y-auto text-sm">
                        <li v-for="lead in props.leads" :key="lead.id" class="flex items-start justify-between gap-3 px-4 py-3">
                            <div class="min-w-0">
                                <div class="font-medium">{{ lead.name }}</div>
                                <div class="truncate text-xs text-muted-foreground">
                                    {{ lead.email ?? lead.phone ?? '—' }} · <span class="capitalize">{{ lead.source }}</span>
                                    <span v-if="lead.on"> · {{ lead.on }}</span>
                                </div>
                                <div v-if="lead.message" class="mt-0.5 truncate text-xs text-muted-foreground">{{ lead.message }}</div>
                            </div>
                            <select
                                :value="lead.status"
                                class="h-8 shrink-0 rounded-md border border-input bg-background px-2 text-xs font-medium capitalize"
                                :class="statusClass(lead.status)"
                                @change="setStatus(lead, ($event.target as HTMLSelectElement).value)"
                            >
                                <option v-for="s in statuses" :key="s" :value="s">{{ s }}</option>
                            </select>
                        </li>
                        <li v-if="props.leads.length === 0" class="px-4 py-10 text-center text-muted-foreground">
                            <UserPlus class="mx-auto mb-2 size-6 opacity-50" />
                            No leads yet — they arrive from your public site.
                        </li>
                    </ul>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
