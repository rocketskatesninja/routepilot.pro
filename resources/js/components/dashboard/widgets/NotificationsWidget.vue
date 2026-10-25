<script setup lang="ts">
import EmptyState from '@/components/EmptyState.vue';
import { Link } from '@inertiajs/vue3';

interface Note {
    id: string;
    title: string;
    body: string;
    read: boolean;
    on: string | null;
}

defineProps<{ data: Note[] }>();
</script>

<template>
    <div class="h-full overflow-y-auto">
        <ul v-if="data.length" class="divide-border divide-y text-sm">
            <li v-for="n in data" :key="n.id" class="px-1 py-2">
                <!-- Opening marks it read on the way (NotificationController::open). -->
                <Link :href="`/notifications/${n.id}/open`" class="block">
                    <div class="flex items-center gap-2">
                        <span v-if="!n.read" class="bg-primary size-1.5 shrink-0 rounded-full"></span>
                        <span class="truncate font-medium" :class="n.read ? 'text-muted-foreground' : ''">{{ n.title }}</span>
                        <span class="text-muted-foreground ml-auto shrink-0 text-xs">{{ n.on }}</span>
                    </div>
                    <p v-if="n.body" class="text-muted-foreground mt-0.5 line-clamp-2 text-xs">{{ n.body }}</p>
                </Link>
            </li>
        </ul>
        <EmptyState v-else class="p-0">No notifications.</EmptyState>
    </div>
</template>
