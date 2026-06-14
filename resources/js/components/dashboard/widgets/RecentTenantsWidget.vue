<script setup lang="ts">
import EmptyState from '@/components/EmptyState.vue';
import { Link } from '@inertiajs/vue3';

interface TenantRow {
    id: number;
    name: string;
    status: string | null;
    logo: string | null;
}

defineProps<{ data: TenantRow[] }>();

const statusClass = (s: string | null): string =>
    s === 'active' ? 'bg-emerald-500/15 text-emerald-600 dark:text-emerald-400' : 'bg-muted text-muted-foreground';
</script>

<template>
    <div class="h-full overflow-y-auto">
        <ul v-if="data.length" class="divide-y divide-border text-sm">
            <li v-for="t in data" :key="t.id">
                <Link :href="`/people?selected=${t.id}&selected_type=tenant`" class="flex items-center justify-between gap-2 rounded px-1 py-2 transition-colors hover:bg-muted/40">
                    <span class="flex min-w-0 items-center gap-2">
                        <span class="flex size-7 shrink-0 items-center justify-center overflow-hidden rounded-md bg-muted text-xs font-semibold">
                            <img v-if="t.logo" :src="t.logo" alt="" class="size-full object-cover" />
                            <span v-else>{{ t.name.charAt(0) }}</span>
                        </span>
                        <span class="truncate">{{ t.name }}</span>
                    </span>
                    <span class="shrink-0 rounded-full px-2 py-0.5 text-xs font-medium capitalize" :class="statusClass(t.status)">{{
                        t.status
                    }}</span>
                </Link>
            </li>
        </ul>
        <EmptyState v-else class="p-0">No tenants yet.</EmptyState>
    </div>
</template>
