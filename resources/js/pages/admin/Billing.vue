<script setup lang="ts">
import MasterDetail from '@/components/MasterDetail.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/AppLayout.vue';
import { formatMoney } from '@/lib/utils';
import { type BreadcrumbItem } from '@/types';
import { Head, router, useForm } from '@inertiajs/vue3';
import { Gift } from 'lucide-vue-next';
import { computed, watch } from 'vue';

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

interface TenantDetail extends TenantRow {
    free: boolean;
    billing_note: string | null;
    trial_days_left: number;
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
        free: number;
    };
    tenants: TenantRow[];
    selected: TenantDetail | null;
    plan: { base_price: number; included_pools: number; included_agents: number };
}>();

const breadcrumbs: BreadcrumbItem[] = [{ title: 'Billing', href: '/platform/billing' }];

const statusMeta: Record<string, { label: string; class: string }> = {
    active: { label: 'Active', class: 'bg-emerald-500/15 text-emerald-600 dark:text-emerald-400' },
    trialing: { label: 'Trialing', class: 'bg-sky-500/15 text-sky-600 dark:text-sky-400' },
    past_due: { label: 'Past due', class: 'bg-red-500/15 text-red-600 dark:text-red-400' },
    expired: { label: 'Expired', class: 'bg-amber-500/15 text-amber-600 dark:text-amber-400' },
    canceled: { label: 'Canceled', class: 'bg-muted text-muted-foreground' },
    free: { label: 'Free', class: 'bg-violet-500/15 text-violet-600 dark:text-violet-400' },
    none: { label: 'No trial', class: 'bg-muted text-muted-foreground' },
};
const meta = (s: string) => statusMeta[s] ?? statusMeta.none;

// Annualized run-rate is a handy headline figure beside MRR.
const arr = computed(() => props.metrics.mrr * 12);

// Server-driven selection (mirrors people/Index): the ?selected id round-trips
// so the panel gets the tenant's full billing detail without an extra fetch.
function openTenant(row: TenantRow) {
    router.get('/platform/billing', { selected: row.id }, { preserveState: true, preserveScroll: true });
}
function closePane() {
    router.get('/platform/billing', {}, { preserveState: true, preserveScroll: true });
}

// The editor form, kept in sync with whichever tenant is selected.
const form = useForm<{ billing_free: boolean; billing_note: string; trial_ends_at: string | null }>({
    billing_free: false,
    billing_note: '',
    trial_ends_at: null,
});
watch(
    () => props.selected?.id,
    () => {
        const s = props.selected;
        form.defaults({
            billing_free: s?.free ?? false,
            billing_note: s?.billing_note ?? '',
            trial_ends_at: s?.trial_ends_at ?? null,
        });
        form.reset();
        form.clearErrors();
    },
    { immediate: true },
);

function extendTrial(days: number) {
    const cur = form.trial_ends_at ? new Date(form.trial_ends_at) : null;
    const base = cur && cur > new Date() ? cur : new Date();
    base.setDate(base.getDate() + days);
    form.trial_ends_at = base.toISOString().slice(0, 10);
}
function save() {
    if (!props.selected) return;
    form.transform((d) => ({ ...d, trial_ends_at: d.trial_ends_at || null })).patch(route('platform.billing.update', props.selected.id), {
        preserveScroll: true,
        preserveState: true,
    });
}
</script>

<template>
    <Head title="Platform billing" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex h-full min-h-0 w-full flex-1 flex-col gap-6 p-4">
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
                    <p class="mt-0.5 text-xs text-muted-foreground">
                        {{ metrics.active }} paying<span v-if="metrics.free"> · {{ metrics.free }} free</span> · {{ metrics.expired }} expired
                    </p>
                </div>
            </div>

            <MasterDetail
                :has-selection="props.selected !== null"
                :selection-key="props.selected?.id ?? null"
                empty-text="Select an account to manage its billing."
                @close="closePane"
            >
                <!-- accounts list -->
                <template #list>
                    <div class="flex min-h-0 flex-1 flex-col overflow-hidden rounded-xl border border-border">
                        <div class="flex items-center justify-between gap-4 border-b border-border px-4 py-3">
                            <h2 class="text-sm font-semibold">Accounts</h2>
                            <p class="text-xs text-muted-foreground">
                                Plan: {{ formatMoney(plan.base_price) }}/mo · {{ plan.included_pools }} pools · {{ plan.included_agents }} agents
                                incl.
                            </p>
                        </div>
                        <div class="min-h-0 flex-1 overflow-auto">
                            <table class="w-full text-sm">
                                <thead class="sticky top-0 z-10 bg-card">
                                    <tr class="border-b border-border text-left text-xs uppercase tracking-wide text-muted-foreground">
                                        <th class="px-4 py-2 font-medium">Company</th>
                                        <th class="px-4 py-2 font-medium">Status</th>
                                        <th class="px-4 py-2 text-right font-medium">Pools</th>
                                        <th class="px-4 py-2 text-right font-medium">Agents</th>
                                        <th class="px-4 py-2 text-right font-medium">Est. / mo</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr
                                        v-for="t in tenants"
                                        :key="t.id"
                                        class="cursor-pointer border-b border-border last:border-0 hover:bg-muted/40"
                                        :class="{ 'bg-muted/60': props.selected?.id === t.id }"
                                        @click="openTenant(t)"
                                    >
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
                </template>

                <!-- management panel -->
                <template #detail>
                    <div v-if="props.selected" class="space-y-5 text-sm">
                        <div>
                            <div class="flex items-center gap-2">
                                <h2 class="text-lg font-semibold">{{ props.selected.name }}</h2>
                                <span class="inline-flex rounded-full px-2 py-0.5 text-xs font-medium" :class="meta(props.selected.status).class">
                                    {{ meta(props.selected.status).label }}
                                </span>
                            </div>
                            <p class="text-xs text-muted-foreground">/{{ props.selected.slug }}</p>
                        </div>

                        <!-- usage at a glance -->
                        <dl class="grid grid-cols-3 gap-2">
                            <div class="rounded-lg border border-border p-2.5 text-center">
                                <dt class="text-[10px] uppercase tracking-wide text-muted-foreground">Pools</dt>
                                <dd class="font-mono text-lg font-semibold tabular-nums">{{ props.selected.pools }}</dd>
                            </div>
                            <div class="rounded-lg border border-border p-2.5 text-center">
                                <dt class="text-[10px] uppercase tracking-wide text-muted-foreground">Agents</dt>
                                <dd class="font-mono text-lg font-semibold tabular-nums">{{ props.selected.agents }}</dd>
                            </div>
                            <div class="rounded-lg border border-border p-2.5 text-center">
                                <dt class="text-[10px] uppercase tracking-wide text-muted-foreground">Est./mo</dt>
                                <dd class="font-mono text-lg font-semibold tabular-nums">{{ formatMoney(props.selected.estimated) }}</dd>
                            </div>
                        </dl>

                        <p
                            v-if="props.selected.subscribed"
                            class="rounded-lg border border-border bg-muted/40 px-3 py-2 text-xs text-muted-foreground"
                        >
                            This account has an active Stripe subscription — comping it won't stop Stripe from billing them.
                        </p>

                        <form class="space-y-5" @submit.prevent="save">
                            <!-- free access — the headline lever -->
                            <div
                                class="rounded-xl border border-border p-3.5"
                                :class="form.billing_free ? 'border-violet-500/40 bg-violet-500/5' : ''"
                            >
                                <button
                                    type="button"
                                    role="switch"
                                    :aria-checked="form.billing_free"
                                    class="flex w-full items-center justify-between gap-3 text-left"
                                    @click="form.billing_free = !form.billing_free"
                                >
                                    <span class="flex items-center gap-2">
                                        <Gift class="size-4" :class="form.billing_free ? 'text-violet-500' : 'text-muted-foreground'" />
                                        <span class="font-medium">Free access</span>
                                    </span>
                                    <span
                                        class="relative inline-flex h-5 w-9 shrink-0 items-center rounded-full transition-colors"
                                        :class="form.billing_free ? 'bg-violet-500' : 'bg-input'"
                                    >
                                        <span
                                            class="inline-block size-4 translate-x-0.5 rounded-full bg-white shadow transition-transform"
                                            :class="{ 'translate-x-[18px]': form.billing_free }"
                                        />
                                    </span>
                                </button>
                                <p class="mt-1.5 text-xs text-muted-foreground">
                                    Comps this account indefinitely — never billed, never locked. Overrides trial &amp; subscription state.
                                </p>
                                <div class="mt-3">
                                    <Label for="note" class="text-xs">Reason (optional)</Label>
                                    <Input
                                        id="note"
                                        v-model="form.billing_note"
                                        placeholder="e.g. Beta partner, founder comp"
                                        maxlength="255"
                                        class="mt-1 h-9"
                                    />
                                    <p v-if="form.errors.billing_note" class="mt-1 text-xs text-red-500">{{ form.errors.billing_note }}</p>
                                </div>
                            </div>

                            <!-- trial management -->
                            <div>
                                <Label for="trial" class="text-xs">Trial ends</Label>
                                <div class="mt-1 flex items-center gap-2">
                                    <Input id="trial" v-model="form.trial_ends_at" type="date" class="h-9 w-40" />
                                    <Button type="button" size="sm" variant="outline" @click="extendTrial(30)">+30d</Button>
                                    <Button type="button" size="sm" variant="outline" @click="extendTrial(90)">+90d</Button>
                                    <Button type="button" size="sm" variant="ghost" @click="form.trial_ends_at = null">Clear</Button>
                                </div>
                                <p v-if="form.errors.trial_ends_at" class="mt-1 text-xs text-red-500">{{ form.errors.trial_ends_at }}</p>
                                <p class="mt-1 text-xs text-muted-foreground">
                                    Leave empty for no trial. Extending gives the account back-office access until that date.
                                </p>
                            </div>

                            <div class="flex items-center gap-2">
                                <Button type="submit" :disabled="form.processing || !form.isDirty">Save</Button>
                                <span v-if="form.recentlySuccessful" class="text-xs text-emerald-600 dark:text-emerald-400">Saved.</span>
                            </div>
                        </form>
                    </div>
                </template>
            </MasterDetail>
        </div>
    </AppLayout>
</template>
