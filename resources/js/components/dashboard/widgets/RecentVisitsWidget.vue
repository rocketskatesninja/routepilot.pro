<script setup lang="ts">
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
                    <span class="truncate">{{ v.pool }} <span class="text-muted-foreground">· {{ v.agent }}</span></span>
                </span>
                <span class="shrink-0 text-xs text-muted-foreground">{{ v.completed_on }}</span>
            </li>
        </ul>
        <div v-else class="flex h-full items-center justify-center py-6 text-center text-sm text-muted-foreground">No visits yet.</div>
    </div>
</template>
