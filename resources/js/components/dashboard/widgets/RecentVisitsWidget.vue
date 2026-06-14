<script setup lang="ts">
import EmptyState from '@/components/EmptyState.vue';
import EntityAvatar from '@/components/EntityAvatar.vue';

interface Visit {
    id: number;
    pool: string | null;
    pool_photo: string | null;
    agent: string;
    completed_on: string | null;
}

defineProps<{ data: Visit[] }>();
</script>

<template>
    <div class="h-full overflow-y-auto">
        <ul v-if="data.length" class="divide-y divide-border text-sm">
            <li v-for="v in data" :key="v.id" class="flex items-center justify-between gap-2 px-1 py-2.5">
                <span class="flex min-w-0 items-center gap-2">
                    <EntityAvatar :src="v.pool_photo" type="pool" :name="v.pool" size="sm" />
                    <span class="truncate"
                        >{{ v.pool }} <span class="text-muted-foreground">· {{ v.agent }}</span></span
                    >
                </span>
                <span class="shrink-0 text-xs text-muted-foreground">{{ v.completed_on }}</span>
            </li>
        </ul>
        <EmptyState v-else class="py-6">No visits yet.</EmptyState>
    </div>
</template>
