<script setup lang="ts">
import EmptyState from '@/components/EmptyState.vue';
import EntityAvatar from '@/components/EntityAvatar.vue';
import { Link } from '@inertiajs/vue3';
import { SkipForward } from 'lucide-vue-next';

interface Activity {
    key: string;
    pool: string | null;
    pool_photo: string | null;
    agent: string;
    on: string | null;
    status: string;
    report_url: string | null;
}

defineProps<{ data: Activity[] }>();
</script>

<template>
    <div class="h-full overflow-y-auto">
        <ul v-if="data.length" class="divide-y divide-border text-sm">
            <li v-for="a in data" :key="a.key">
                <component
                    :is="a.report_url ? Link : 'div'"
                    :href="a.report_url ?? undefined"
                    class="-mx-1 flex items-center justify-between gap-2 rounded px-1 py-2.5"
                    :class="a.report_url ? 'cursor-pointer hover:bg-muted hover:text-foreground' : ''"
                >
                    <span class="flex min-w-0 items-center gap-2">
                        <EntityAvatar :src="a.pool_photo" type="pool" :name="a.pool" size="sm" />
                        <span class="min-w-0 truncate">
                            <span :class="a.status === 'skipped' ? 'text-muted-foreground line-through' : ''">{{ a.pool }}</span>
                            <span class="text-muted-foreground"> · {{ a.agent }}</span>
                        </span>
                    </span>
                    <span class="flex shrink-0 items-center gap-1.5 text-xs text-muted-foreground">
                        <span
                            v-if="a.status === 'skipped'"
                            class="inline-flex items-center gap-0.5 rounded-full bg-amber-500/15 px-1.5 py-0.5 text-amber-600 dark:text-amber-400"
                        >
                            <SkipForward class="size-3" /> skipped
                        </span>
                        {{ a.on }}
                    </span>
                </component>
            </li>
        </ul>
        <EmptyState v-else class="py-6">No recent activity.</EmptyState>
    </div>
</template>
