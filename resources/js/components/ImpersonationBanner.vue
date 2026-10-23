<script setup lang="ts">
import type { User } from '@/types';
import { router, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';

const page = usePage();
const auth = computed(() => page.props.auth as { impersonating?: boolean; user?: User | null; role?: string } | undefined);
const impersonating = computed(() => auth.value?.impersonating === true);

const ROLE_LABELS: Record<string, string> = { tenant_admin: 'admin', agent: 'agent', customer: 'customer' };
/** "Dana Reyes, agent at Sunshine Pools" */
const who = computed(() => {
    const role = ROLE_LABELS[auth.value?.role ?? ''] ?? auth.value?.role ?? '';
    const company = (page.props.tenant as { name?: string } | null | undefined)?.name;
    return `${auth.value?.user?.name ?? 'this account'}${role ? `, ${role}` : ''}${company ? ` at ${company}` : ''}`;
});

const stop = () => router.post('/impersonate/stop');
</script>

<template>
    <div v-if="impersonating" class="flex items-center justify-between gap-3 bg-amber-500 px-4 py-1.5 text-sm font-medium text-amber-950">
        <span class="min-w-0 truncate">You're signed in as {{ who }} (impersonating).</span>
        <button class="shrink-0 rounded bg-amber-950/15 px-2 py-0.5 transition-colors hover:bg-amber-950/25" @click="stop">Stop impersonating</button>
    </div>
</template>
