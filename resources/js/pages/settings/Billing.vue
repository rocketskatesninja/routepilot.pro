<script setup lang="ts">
import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/AppLayout.vue';
import { formatMoney } from '@/lib/utils';
import { type BreadcrumbItem } from '@/types';
import { Head, usePage } from '@inertiajs/vue3';
import { CheckCircle2, CreditCard, TriangleAlert } from 'lucide-vue-next';
import { computed } from 'vue';

defineProps<{
    configured: boolean;
    plan: { base_price: number; included_pools: number; included_agents: number; price_per_pool: number; price_per_agent: number };
}>();

interface Line {
    used: number;
    included: number;
    over: number;
    unit_price: number;
    overage: number;
}
interface Billing {
    status: string;
    on_trial: boolean;
    subscribed: boolean;
    trial_ends_at: string | null;
    trial_days_left: number;
    usage: { pools: Line; agents: Line; base: number; overage_total: number; estimated_total: number };
}

const page = usePage();
const billing = computed(() => page.props.billing as Billing | null);
const csrf = computed(() => (page.props.csrf as string | undefined) ?? '');
const breadcrumbs: BreadcrumbItem[] = [{ title: 'Billing', href: '/billing' }];

const banner = computed(() => {
    const b = billing.value;
    if (!b) return null;
    switch (b.status) {
        case 'active':
            return { tone: 'ok', icon: CheckCircle2, text: 'Your subscription is active.' };
        case 'trialing':
            return {
                tone: 'info',
                icon: CreditCard,
                text: `Free trial — ${b.trial_days_left} day${b.trial_days_left === 1 ? '' : 's'} left${b.trial_ends_at ? ` (ends ${b.trial_ends_at})` : ''}.`,
            };
        case 'past_due':
            return { tone: 'bad', icon: TriangleAlert, text: 'Your last payment failed — update your card to avoid interruption.' };
        case 'expired':
            return { tone: 'bad', icon: TriangleAlert, text: 'Your free trial has ended. Subscribe to keep using RoutePilot.' };
        default:
            return null;
    }
});
const toneClass = (tone: string) =>
    tone === 'ok'
        ? 'border-emerald-500/30 bg-emerald-500/10 text-emerald-700 dark:text-emerald-400'
        : tone === 'bad'
          ? 'border-red-500/30 bg-red-500/10 text-red-700 dark:text-red-400'
          : 'border-primary/30 bg-primary/10 text-primary';
</script>

<template>
    <Head title="Billing" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="mx-auto flex w-full max-w-3xl flex-1 flex-col gap-6 p-4">
            <!-- status -->
            <div v-if="banner" class="flex items-center gap-2 rounded-xl border px-4 py-3 text-sm font-medium" :class="toneClass(banner.tone)">
                <component :is="banner.icon" class="size-5 shrink-0" />
                <span>{{ banner.text }}</span>
            </div>

            <!-- plan + usage -->
            <div class="rounded-xl border border-border p-5">
                <div class="mb-4 flex items-start justify-between gap-4">
                    <div>
                        <h2 class="text-lg font-semibold">Your plan</h2>
                        <p class="text-sm text-muted-foreground">
                            {{ formatMoney(plan.base_price) }}/mo base — includes {{ plan.included_pools }} pools and
                            {{ plan.included_agents }} agents. Extra pools are {{ formatMoney(plan.price_per_pool) }} each; extra agents
                            {{ formatMoney(plan.price_per_agent) }} each.
                        </p>
                    </div>
                </div>

                <div v-if="billing" class="overflow-hidden rounded-lg border border-border">
                    <table class="w-full text-sm">
                        <tbody>
                            <tr class="border-b border-border">
                                <td class="px-4 py-2.5 font-medium">Base plan</td>
                                <td class="px-4 py-2.5 text-right text-muted-foreground">
                                    includes {{ plan.included_pools }} pools · {{ plan.included_agents }} agents
                                </td>
                                <td class="px-4 py-2.5 text-right font-medium">{{ formatMoney(billing.usage.base) }}</td>
                            </tr>
                            <tr class="border-b border-border">
                                <td class="px-4 py-2.5 font-medium">Pools</td>
                                <td class="px-4 py-2.5 text-right text-muted-foreground">
                                    {{ billing.usage.pools.used }} used<span v-if="billing.usage.pools.over">
                                        · {{ billing.usage.pools.over }} over</span
                                    >
                                </td>
                                <td class="px-4 py-2.5 text-right font-medium">{{ formatMoney(billing.usage.pools.overage) }}</td>
                            </tr>
                            <tr class="border-b border-border">
                                <td class="px-4 py-2.5 font-medium">Agents</td>
                                <td class="px-4 py-2.5 text-right text-muted-foreground">
                                    {{ billing.usage.agents.used }} used<span v-if="billing.usage.agents.over">
                                        · {{ billing.usage.agents.over }} over</span
                                    >
                                </td>
                                <td class="px-4 py-2.5 text-right font-medium">{{ formatMoney(billing.usage.agents.overage) }}</td>
                            </tr>
                            <tr class="bg-muted/40">
                                <td class="px-4 py-3 font-semibold" colspan="2">Estimated monthly total</td>
                                <td class="px-4 py-3 text-right text-base font-bold">{{ formatMoney(billing.usage.estimated_total) }}</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- actions -->
            <div class="flex items-center justify-between gap-4 rounded-xl border border-border p-5">
                <div>
                    <h3 class="font-semibold">{{ billing?.subscribed ? 'Manage subscription' : 'Subscribe' }}</h3>
                    <p class="text-sm text-muted-foreground">
                        {{
                            billing?.subscribed
                                ? 'Update your card, view invoices, or cancel in the Stripe portal.'
                                : 'Add a payment method to continue after your trial. You won’t be charged until the trial ends.'
                        }}
                    </p>
                </div>

                <template v-if="!configured">
                    <Button disabled>Coming soon</Button>
                </template>
                <a v-else-if="billing?.subscribed" :href="route('billing.portal')">
                    <Button variant="outline"><CreditCard class="mr-1 size-4" /> Manage billing</Button>
                </a>
                <form v-else method="POST" :action="route('billing.checkout')">
                    <input type="hidden" name="_token" :value="csrf" />
                    <Button type="submit"><CreditCard class="mr-1 size-4" /> Subscribe</Button>
                </form>
            </div>

            <p class="px-1 text-xs text-muted-foreground">Billing is handled securely by Stripe. We never store your card details.</p>
        </div>
    </AppLayout>
</template>
