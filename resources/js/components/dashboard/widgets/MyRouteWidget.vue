<script setup lang="ts">
import EntityAvatar from '@/components/EntityAvatar.vue';
import { Link } from '@inertiajs/vue3';
import { ChevronRight } from 'lucide-vue-next';

interface Stop {
    id: number;
    pool: string | null;
    pool_photo: string | null;
    status: string;
}

defineProps<{ data: { label: string | null; stops: Stop[] } }>();

const statusClasses: Record<string, string> = {
    completed: 'bg-emerald-500/15 text-emerald-600 dark:text-emerald-400',
    in_progress: 'bg-sky-500/15 text-sky-600 dark:text-sky-400',
    pending: 'bg-muted text-muted-foreground',
    skipped: 'bg-amber-500/15 text-amber-600 dark:text-amber-400',
};
</script>

<template>
    <div class="h-full overflow-y-auto">
        <template v-if="data.stops.length">
            <div class="mb-1 px-1 text-xs text-muted-foreground">{{ data.label }} · {{ data.stops.length }} stop(s)</div>
            <ul class="divide-y divide-border text-sm">
                <li v-for="(stop, i) in data.stops" :key="stop.id">
                    <Link
                        :href="`/visit/${stop.id}`"
                        class="flex items-center justify-between gap-2 rounded px-1 py-2.5 transition-colors hover:bg-muted/40"
                    >
                        <span class="flex min-w-0 items-center gap-2">
                            <span class="text-muted-foreground">{{ i + 1 }}.</span>
                            <EntityAvatar :src="stop.pool_photo" type="pool" :name="stop.pool" size="sm" />
                            <span class="truncate">{{ stop.pool }}</span>
                        </span>
                        <span class="flex shrink-0 items-center gap-1.5">
                            <span
                                class="rounded-full px-2 py-0.5 text-xs font-medium capitalize"
                                :class="statusClasses[stop.status] ?? 'bg-muted'"
                                >{{ stop.status.replace('_', ' ') }}</span
                            >
                            <ChevronRight class="size-4 text-muted-foreground" />
                        </span>
                    </Link>
                </li>
            </ul>
        </template>
        <div v-else class="flex h-full items-center justify-center py-6 text-center text-sm text-muted-foreground">
            No pending stops on your route.
        </div>
    </div>
</template>
