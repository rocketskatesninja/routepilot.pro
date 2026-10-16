<script setup lang="ts">
import EmptyState from '@/components/EmptyState.vue';
import EntityAvatar from '@/components/EntityAvatar.vue';
import { Button } from '@/components/ui/button';
import { inboxRequestLink } from '@/lib/links';
import { formatDay } from '@/lib/utils';
import { Link } from '@inertiajs/vue3';

interface Req {
    id: number;
    type: string;
    title: string;
    message: string | null;
    customer: string | null;
    customer_photo: string | null;
    pool: string | null;
    preferred_date: string | null;
    on: string | null;
}

defineProps<{ data: Req[] }>();

const badge = (type: string) =>
    ({
        service: 'bg-emerald-500/15 text-emerald-600 dark:text-emerald-400',
        hold: 'bg-sky-500/15 text-sky-600 dark:text-sky-400',
        cancel: 'bg-amber-500/15 text-amber-600 dark:text-amber-400',
    })[type] ?? 'bg-muted text-muted-foreground';
</script>

<template>
    <div class="h-full overflow-y-auto">
        <ul v-if="data.length" class="divide-border divide-y text-sm">
            <li v-for="r in data" :key="r.id" class="flex items-start justify-between gap-3 px-1 py-2.5">
                <div class="min-w-0">
                    <span class="rounded-full px-2 py-0.5 text-xs font-medium" :class="badge(r.type)">{{ r.title }}</span>
                    <span class="ml-2 inline-flex items-center gap-1.5 align-middle font-medium"
                        ><EntityAvatar :src="r.customer_photo" type="person" :name="r.customer" size="sm" shape="circle" />{{ r.customer }}</span
                    >
                    <span v-if="r.pool" class="text-muted-foreground"> · {{ r.pool }}</span>
                    <p v-if="r.message" class="text-muted-foreground mt-0.5 truncate">{{ r.message }}</p>
                    <p class="text-muted-foreground text-xs">
                        {{ formatDay(r.on) }}<span v-if="r.preferred_date"> · wants {{ formatDay(r.preferred_date) }}</span>
                    </p>
                </div>
                <Button as-child size="sm" variant="outline">
                    <Link :href="inboxRequestLink(r.id)">Answer</Link>
                </Button>
            </li>
        </ul>
        <EmptyState v-else class="py-6">No requests waiting.</EmptyState>
    </div>
</template>
