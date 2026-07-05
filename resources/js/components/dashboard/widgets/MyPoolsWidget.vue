<script setup lang="ts">
import EmptyState from '@/components/EmptyState.vue';
import EntityAvatar from '@/components/EntityAvatar.vue';

interface PoolRow {
    id: number;
    name: string;
    photo: string | null;
    // Matches App\Services\ChemistryService::getLSIStatus() — an object, not a string.
    health: { label: string; color: string } | null;
}

defineProps<{ data: { pools: PoolRow[] } }>();

const healthClass = (color: string): string =>
    ({
        green: 'bg-emerald-500/15 text-emerald-600 dark:text-emerald-400',
        amber: 'bg-amber-500/15 text-amber-600 dark:text-amber-400',
        red: 'bg-rose-500/15 text-rose-600 dark:text-rose-400',
    })[color] ?? 'bg-muted text-muted-foreground';
</script>

<template>
    <div class="h-full overflow-y-auto">
        <ul v-if="data.pools.length" class="divide-y divide-border text-sm">
            <li v-for="p in data.pools" :key="p.id" class="flex items-center justify-between gap-2 px-1 py-2">
                <span class="flex min-w-0 items-center gap-2">
                    <EntityAvatar :src="p.photo" type="pool" :name="p.name" size="sm" />
                    <span class="truncate">{{ p.name }}</span>
                </span>
                <span v-if="p.health" class="shrink-0 rounded-full px-2 py-0.5 text-xs font-medium" :class="healthClass(p.health.color)">{{
                    p.health.label
                }}</span>
                <span v-else class="shrink-0 text-xs text-muted-foreground">—</span>
            </li>
        </ul>
        <EmptyState v-else class="p-0">No pools on your account yet.</EmptyState>
    </div>
</template>
