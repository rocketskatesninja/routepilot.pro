<script setup lang="ts">
import Chip from '@/components/pane/Chip.vue';
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

const healthTone: Record<string, 'good' | 'warn' | 'bad'> = { green: 'good', amber: 'warn', red: 'bad' };
</script>

<template>
    <div class="h-full overflow-y-auto">
        <ul v-if="data.pools.length" class="divide-border divide-y text-sm">
            <li v-for="p in data.pools" :key="p.id" class="flex items-center justify-between gap-2 px-1 py-2">
                <span class="flex min-w-0 items-center gap-2">
                    <EntityAvatar :src="p.photo" type="pool" :name="p.name" size="sm" />
                    <span class="truncate" :title="p.name">{{ p.name }}</span>
                </span>
                <Chip v-if="p.health" :tone="healthTone[p.health.color] ?? 'muted'">{{ p.health.label }}</Chip>
                <span v-else class="text-muted-foreground shrink-0 text-xs">—</span>
            </li>
        </ul>
        <EmptyState v-else class="p-0">No pools on your account yet.</EmptyState>
    </div>
</template>
