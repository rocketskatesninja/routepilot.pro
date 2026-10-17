<script setup lang="ts">
import MasterDetail from '@/components/MasterDetail.vue';
import Chip from '@/components/pane/Chip.vue';
import IconTile from '@/components/pane/IconTile.vue';
import PaneHeader from '@/components/pane/PaneHeader.vue';
import PaneSection from '@/components/pane/PaneSection.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/AppLayout.vue';
import { formatDay, formatMoney } from '@/lib/utils';
import { type BreadcrumbItem } from '@/types';
import { Head, router, useForm } from '@inertiajs/vue3';
import { Building2, Gift } from 'lucide-vue-next';
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

// --- the side panel ---
const account = computed(() => props.selected);
const statusTone: Record<string, 'good' | 'warn' | 'info' | 'bad' | 'muted' | 'special'> = {
    active: 'good',
    trialing: 'info',
    past_due: 'bad',
    expired: 'warn',
    free: 'special',
};
/** "9 pools and 2 agents, about $34.99 a month. Trial ends Sat, Oct 31 (14 days left)." */
const accountSummary = (a: TenantDetail) =>
    `${a.pools} pool${a.pools === 1 ? '' : 's'} and ${a.agents} agent${a.agents === 1 ? '' : 's'}, about ${formatMoney(a.estimated)} a month.` +
    (a.status === 'trialing' && a.trial_ends_at
        ? ` Trial ends ${formatDay(a.trial_ends_at)} (${a.trial_days_left} day${a.trial_days_left === 1 ? '' : 's'} left).`
        : '');

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
                <div class="border-border rounded-2xl border p-5">
                    <p class="text-muted-foreground text-sm">Monthly revenue</p>
                    <p class="font-display mt-1 text-3xl font-semibold tracking-tight tabular-nums">{{ formatMoney(metrics.mrr) }}</p>
                    <p class="text-muted-foreground mt-0.5 text-sm">{{ formatMoney(arr) }} a year at this rate</p>
                </div>
                <div class="border-border rounded-2xl border p-5">
                    <p class="text-muted-foreground text-sm">In trial</p>
                    <p class="font-display mt-1 text-3xl font-semibold tracking-tight tabular-nums">{{ formatMoney(metrics.trial_pipeline) }}</p>
                    <p class="text-muted-foreground mt-0.5 text-sm">{{ metrics.trialing }} tenant{{ metrics.trialing === 1 ? '' : 's' }} on trial</p>
                </div>
                <div class="border-border rounded-2xl border p-5">
                    <p class="text-muted-foreground text-sm">At risk</p>
                    <p
                        class="font-display mt-1 text-3xl font-semibold tracking-tight tabular-nums"
                        :class="metrics.at_risk > 0 ? 'text-red-600 dark:text-red-400' : ''"
                    >
                        {{ formatMoney(metrics.at_risk) }}
                    </p>
                    <p class="text-muted-foreground mt-0.5 text-sm">{{ metrics.past_due }} past due</p>
                </div>
                <div class="border-border rounded-2xl border p-5">
                    <p class="text-muted-foreground text-sm">Tenants</p>
                    <p class="font-display mt-1 text-3xl font-semibold tracking-tight tabular-nums">{{ metrics.tenants }}</p>
                    <p class="text-muted-foreground mt-0.5 text-sm">
                        {{ metrics.active }} paying<template v-if="metrics.free">, {{ metrics.free }} free</template>, {{ metrics.expired }} expired
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
                    <div class="border-border flex min-h-0 flex-1 flex-col overflow-hidden rounded-xl border">
                        <div class="border-border flex items-center justify-between gap-4 border-b px-4 py-3">
                            <h2 class="text-sm font-semibold">Accounts</h2>
                            <p class="text-muted-foreground text-xs">
                                Plan: {{ formatMoney(plan.base_price) }} a month with {{ plan.included_pools }} pools and
                                {{ plan.included_agents }} agents included
                            </p>
                        </div>
                        <div class="min-h-0 flex-1 overflow-auto">
                            <table class="w-full text-sm">
                                <thead class="bg-card sticky top-0 z-10">
                                    <tr class="border-border text-muted-foreground border-b text-left text-xs">
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
                                        class="border-border hover:bg-muted/40 cursor-pointer border-b last:border-0"
                                        :class="{ 'bg-muted/60': props.selected?.id === t.id }"
                                        @click="openTenant(t)"
                                    >
                                        <td class="px-4 py-2.5">
                                            <div class="font-medium">{{ t.name }}</div>
                                            <div class="text-muted-foreground text-xs">/{{ t.slug }}</div>
                                        </td>
                                        <td class="px-4 py-2.5">
                                            <span class="inline-flex rounded-full px-2 py-0.5 text-xs font-medium" :class="meta(t.status).class">
                                                {{ meta(t.status).label }}
                                            </span>
                                            <span v-if="t.status === 'trialing' && t.trial_ends_at" class="text-muted-foreground ml-2 text-xs">
                                                ends {{ t.trial_ends_at }}
                                            </span>
                                        </td>
                                        <td class="px-4 py-2.5 text-right tabular-nums">{{ t.pools }}</td>
                                        <td class="px-4 py-2.5 text-right tabular-nums">{{ t.agents }}</td>
                                        <td class="px-4 py-2.5 text-right font-medium tabular-nums">{{ formatMoney(t.estimated) }}</td>
                                    </tr>
                                    <tr v-if="!tenants.length">
                                        <td colspan="5" class="text-muted-foreground px-4 py-8 text-center text-sm">No tenants yet.</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </template>

                <!-- management panel -->
                <template #detail>
                    <div v-if="account" class="space-y-6">
                        <PaneHeader :title="account.name" :summary="accountSummary(account)">
                            <template #media><IconTile :icon="Building2" size="lg" /></template>
                            <template #badge>
                                <Chip :tone="statusTone[account.status] ?? 'muted'">{{ meta(account.status).label }}</Chip>
                            </template>
                            <p class="mt-2 text-[15px]">
                                <a
                                    :href="`/t/${account.slug}`"
                                    target="_blank"
                                    rel="noopener"
                                    class="text-primary font-medium underline-offset-4 hover:underline"
                                    >routepilot.pro/t/{{ account.slug }}</a
                                >
                            </p>
                        </PaneHeader>

                        <p
                            v-if="account.subscribed"
                            class="rounded-2xl border border-amber-500/30 bg-amber-500/10 p-4 text-sm text-amber-700 dark:text-amber-400"
                        >
                            They have an active Stripe subscription. Giving free access won’t stop Stripe from charging them.
                        </p>

                        <form class="space-y-6" @submit.prevent="save">
                            <PaneSection title="Free access">
                                <div
                                    class="border-border space-y-3 rounded-2xl border p-4 text-sm"
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
                                            <span class="font-medium">{{ form.billing_free ? 'On: never billed, never locked' : 'Off' }}</span>
                                        </span>
                                        <span
                                            class="relative inline-flex h-6 w-11 shrink-0 items-center rounded-full transition-colors"
                                            :class="form.billing_free ? 'bg-violet-500' : 'bg-input'"
                                        >
                                            <span
                                                class="inline-block size-5 translate-x-0.5 rounded-full bg-white shadow transition-transform"
                                                :class="{ 'translate-x-[22px]': form.billing_free }"
                                            />
                                        </span>
                                    </button>
                                    <p class="text-muted-foreground">Comps the account with no end date. It overrides any trial or subscription.</p>
                                    <div class="grid gap-1.5">
                                        <Label for="note">Reason <span class="text-muted-foreground font-normal">(optional)</span></Label>
                                        <Input
                                            id="note"
                                            v-model="form.billing_note"
                                            placeholder="e.g. Beta partner, founder comp"
                                            maxlength="255"
                                            class="h-9"
                                        />
                                        <p v-if="form.errors.billing_note" class="text-xs text-red-600">{{ form.errors.billing_note }}</p>
                                    </div>
                                </div>
                            </PaneSection>

                            <PaneSection title="Trial">
                                <div class="flex flex-wrap items-center gap-2">
                                    <Input id="trial" v-model="form.trial_ends_at" type="date" class="h-9 w-40" aria-label="Trial ends" />
                                    <Button type="button" size="sm" variant="outline" @click="extendTrial(30)">Add 30 days</Button>
                                    <Button type="button" size="sm" variant="outline" @click="extendTrial(90)">Add 90 days</Button>
                                    <Button type="button" size="sm" variant="ghost" @click="form.trial_ends_at = null">Clear</Button>
                                </div>
                                <p v-if="form.errors.trial_ends_at" class="mt-1 text-xs text-red-600">{{ form.errors.trial_ends_at }}</p>
                                <p class="text-muted-foreground mt-2 text-sm">
                                    Leave it empty for no trial. A later date gives them office access until then.
                                </p>
                            </PaneSection>

                            <div class="flex items-center gap-3">
                                <Button type="submit" :disabled="form.processing || !form.isDirty">Save</Button>
                                <span v-if="form.recentlySuccessful" class="text-sm text-emerald-700 dark:text-emerald-400">Saved.</span>
                            </div>
                        </form>
                    </div>
                </template>
            </MasterDetail>
        </div>
    </AppLayout>
</template>
