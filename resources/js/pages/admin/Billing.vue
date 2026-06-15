<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { formatMoney } from '@/lib/utils';
import { type BreadcrumbItem } from '@/types';
import { Head } from '@inertiajs/vue3';
import { computed } from 'vue';

interface TenantRow {
    id: number;
    name: string;
    slug: string;
    status: string;
    subscribed: boolean;
    trial_ends_at: string | null;
    pools: number;
    agents: number;
    estimated: number;
}

const props = defineProps<{
    metrics: {
        mrr: number;
        at_risk: number;
        trial_pipeline: number;
        tenants: number;
        active: number;
        trialing: number;
        past_due: number;
        expired: number;
    };
    tenants: TenantRow[];
    plan: { base_price: number; included_pools: number; included_agents: number };
}>();

const breadcrumbs: BreadcrumbItem[] = [{ title: 'Billing', href: '/platform/billing' }];

const statusMeta: Record<string, { label: string; class: string }> = {
    active: { label: 'Active', class: 'bg-emerald-500/15 text-emerald-600 dark:text-emerald-400' },
    trialing: { label: 'Trialing', class: 'bg-sky-500/15 text-sky-600 dark:text-sky-400' },
    past_due: { label: 'Past due', class: 'bg-red-500/15 text-red-600 dark:text-red-400' },
    expired: { label: 'Expired', class: 'bg-amber-500/15 text-amber-600 dark:text-amber-400' },
    canceled: { label: 'Canceled', class: 'bg-muted text-muted-foreground' },
    none: { label: 'No trial', class: 'bg-muted text-muted-foreground' },
};
const meta = (s: string) => statusMeta[s] ?? statusMeta.none;

// Annualized run-rate is a handy headline figure beside MRR.
const arr = computed(() => props.metrics.mrr * 12);
</script>

<template>
    <Head title="Platform billing" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex w-full flex-1 flex-col gap-6 p-4">
            <!-- headline metrics -->
            <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
                <div class="rounded-xl border border-border bg-card p-4">
                    <p class="text-xs font-medium uppercase tracking-wide text-muted-foreground">MRR</p>
                    <p class="mt-1 font-mono text-2xl font-bold tabular-nums">{{ formatMoney(metrics.mrr) }}</p>
                    <p class="mt-0.5 text-xs text-muted-foreground">{{ formatMoney(arr) }}/yr run-rate</p>
                </div>
                <div class="rounded-xl border border-border bg-card p-4">
                    <p class="text-xs font-medium uppercase tracking-wide text-muted-foreground">Trial pipeline</p>
                    <p class="mt-1 font-mono text-2xl font-bold tabular-nums">{{ formatMoney(metrics.trial_pipeline) }}</p>
                    <p class="mt-0.5 text-xs text-muted-foreground">{{ metrics.trialing }} on trial</p>
                </div>
                <div class="rounded-xl border border-border bg-card p-4">
                    <p class="text-xs font-medium uppercase tracking-wide text-muted-foreground">At risk</p>
                    <p class="mt-1 font-mono text-2xl font-bold tabular-nums" :class="metrics.at_risk > 0 ? 'text-red-600 dark:text-red-400' : ''">
                        {{ formatMoney(metrics.at_risk) }}
                    </p>
                    <p class="mt-0.5 text-xs text-muted-foreground">{{ metrics.past_due }} past due</p>
                </div>
                <div class="rounded-xl border border-border bg-card p-4">
                    <p class="text-xs font-medium uppercase tracking-wide text-muted-foreground">Tenants</p>
                    <p class="mt-1 font-mono text-2xl font-bold tabular-nums">{{ metrics.tenants }}</p>
                    <p class="mt-0.5 text-xs text-muted-foreground">{{ metrics.active }} paying · {{ metrics.expired }} expired</p>
                </div>
            </div>

            <!-- per-tenant table -->
            <div class="overflow-hidden rounded-xl border border-border">
                <div class="flex items-center justify-between gap-4 border-b border-border px-4 py-3">
                    <h2 class="text-sm font-semibold">Accounts</h2>
                    <p class="text-xs text-muted-foreground">
                        Plan: {{ formatMoney(plan.base_price) }}/mo · {{ plan.included_pools }} pools · {{ plan.included_agents }} agents incl.
                    </p>
                </div>
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead>
                            <tr class="border-b border-border text-left text-xs uppercase tracking-wide text-muted-foreground">
                                <th class="px-4 py-2 font-medium">Company</th>
                                <th class="px-4 py-2 font-medium">Status</th>
                                <th class="px-4 py-2 text-right font-medium">Pools</th>
                                <th class="px-4 py-2 text-right font-medium">Agents</th>
                                <th class="px-4 py-2 text-right font-medium">Est. / mo</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="t in tenants" :key="t.id" class="border-b border-border last:border-0 hover:bg-muted/40">
                                <td class="px-4 py-2.5">
                                    <div class="font-medium">{{ t.name }}</div>
                                    <div class="text-xs text-muted-foreground">/{{ t.slug }}</div>
                                </td>
                                <td class="px-4 py-2.5">
                                    <span class="inline-flex rounded-full px-2 py-0.5 text-xs font-medium" :class="meta(t.status).class">
                                        {{ meta(t.status).label }}
                                    </span>
                                    <span v-if="t.status === 'trialing' && t.trial_ends_at" class="ml-2 text-xs text-muted-foreground">
                                        ends {{ t.trial_ends_at }}
                                    </span>
                                </td>
                                <td class="px-4 py-2.5 text-right font-mono tabular-nums">{{ t.pools }}</td>
                                <td class="px-4 py-2.5 text-right font-mono tabular-nums">{{ t.agents }}</td>
                                <td class="px-4 py-2.5 text-right font-mono font-medium tabular-nums">{{ formatMoney(t.estimated) }}</td>
                            </tr>
                            <tr v-if="!tenants.length">
                                <td colspan="5" class="px-4 py-8 text-center text-sm text-muted-foreground">No tenants yet.</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
