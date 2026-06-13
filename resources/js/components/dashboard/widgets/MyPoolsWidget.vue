<script setup lang="ts">
import EntityAvatar from '@/components/EntityAvatar.vue';

interface PoolRow {
    id: number;
    name: string;
    photo: string | null;
    health: string | null;
}

defineProps<{ data: { pools: PoolRow[] } }>();

const healthClass = (h: string | null): string => {
    const k = (h ?? '').toLowerCase();
    if (k.includes('balanc')) return 'bg-emerald-500/15 text-emerald-600 dark:text-emerald-400';
    if (k.includes('corros') || k.includes('scal') || k.includes('aggress')) return 'bg-amber-500/15 text-amber-600 dark:text-amber-400';
    return 'bg-muted text-muted-foreground';
};
</script>

<template>
    <div class="h-full overflow-y-auto">
        <ul v-if="data.pools.length" class="divide-y divide-border text-sm">
            <li v-for="p in data.pools" :key="p.id" class="flex items-center justify-between gap-2 px-1 py-2">
                <span class="flex min-w-0 items-center gap-2">
                    <EntityAvatar :src="p.photo" type="pool" :name="p.name" size="sm" />
                    <span class="truncate">{{ p.name }}</span>
                </span>
                <span v-if="p.health" class="shrink-0 rounded-full px-2 py-0.5 text-xs font-medium capitalize" :class="healthClass(p.health)">{{ p.health }}</span>
                <span v-else class="shrink-0 text-xs text-muted-foreground">—</span>
            </li>
        </ul>
        <div v-else class="flex h-full items-center justify-center text-center text-sm text-muted-foreground">No pools on your account yet.</div>
    </div>
</template>
