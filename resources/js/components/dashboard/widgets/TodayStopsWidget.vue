<script setup lang="ts">
import EmptyState from '@/components/EmptyState.vue';
import EntityAvatar from '@/components/EntityAvatar.vue';
import { visitStatusClass } from '@/lib/statusColors';
import { Link } from '@inertiajs/vue3';
import { ArrowRight } from 'lucide-vue-next';

interface Stop {
    id: number;
    pool: string | null;
    pool_photo: string | null;
    agent: string;
    status: string;
}

defineProps<{ data: Stop[] }>();
</script>

<template>
    <div class="flex h-full flex-col">
        <Link href="/schedule" class="mb-1 inline-flex shrink-0 items-center gap-1 px-1 text-xs font-medium text-primary hover:underline">
            Open schedule <ArrowRight class="size-3" />
        </Link>
        <ul v-if="data.length" class="flex-1 divide-y divide-border overflow-y-auto text-sm">
            <li v-for="s in data" :key="s.id" class="flex items-center justify-between gap-2 px-1 py-2">
                <span class="flex min-w-0 items-center gap-2">
                    <EntityAvatar :src="s.pool_photo" type="pool" :name="s.pool" size="sm" />
                    <span class="truncate"
                        >{{ s.pool }} <span class="text-muted-foreground">· {{ s.agent }}</span></span
                    >
                </span>
                <span class="shrink-0 rounded-full px-2 py-0.5 text-xs font-medium capitalize" :class="visitStatusClass(s.status)">{{
                    s.status.replace('_', ' ')
                }}</span>
            </li>
        </ul>
        <EmptyState v-else class="h-auto flex-1 p-0">No stops scheduled today.</EmptyState>
    </div>
</template>
