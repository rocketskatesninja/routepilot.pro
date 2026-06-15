<script setup lang="ts">
import { type BillingState, type SharedData } from '@/types';
import { Link, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';

const page = usePage<SharedData>();
const billing = computed<BillingState | null>(() => page.props.billing);
const isAdmin = computed(() => page.props.auth.role === 'tenant_admin');

// Only nudge while the trial is winding down or a payment has failed; active and
// locked states need no bar (locked is enforced by the soft-lock middleware).
const banner = computed(() => {
    const b = billing.value;
    if (!b) return null;
    if (b.status === 'past_due') {
        return { tone: 'bad', text: 'Your last payment failed — update your card to avoid interruption.' };
    }
    if (b.status === 'trialing' && b.trial_days_left <= 7) {
        const d = b.trial_days_left;
        return { tone: 'warn', text: `Free trial ends in ${d} day${d === 1 ? '' : 's'}.` };
    }
    return null;
});

const toneClass = (tone: string) => (tone === 'bad' ? 'bg-red-500 text-red-950' : 'bg-amber-400 text-amber-950');
</script>

<template>
    <div v-if="banner" class="flex items-center justify-between gap-3 px-4 py-1.5 text-sm font-medium" :class="toneClass(banner.tone)">
        <span>{{ banner.text }}</span>
        <Link v-if="isAdmin" :href="route('billing.show')" class="rounded bg-black/10 px-2 py-0.5 transition-colors hover:bg-black/20">
            {{ banner.tone === 'bad' ? 'Update billing' : 'Add billing' }}
        </Link>
    </div>
</template>
