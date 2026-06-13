<script setup lang="ts">
import { Link } from '@inertiajs/vue3';

interface Note {
    id: string;
    title: string;
    body: string;
    url: string | null;
    read: boolean;
    on: string | null;
}

defineProps<{ data: Note[] }>();
</script>

<template>
    <div class="h-full overflow-y-auto">
        <ul v-if="data.length" class="divide-y divide-border text-sm">
            <li v-for="n in data" :key="n.id" class="px-1 py-2">
                <component :is="n.url ? Link : 'div'" :href="n.url ?? undefined" class="block">
                    <div class="flex items-center gap-2">
                        <span v-if="!n.read" class="size-1.5 shrink-0 rounded-full bg-primary"></span>
                        <span class="truncate font-medium" :class="n.read ? 'text-muted-foreground' : ''">{{ n.title }}</span>
                        <span class="ml-auto shrink-0 text-xs text-muted-foreground">{{ n.on }}</span>
                    </div>
                    <p v-if="n.body" class="mt-0.5 line-clamp-2 text-xs text-muted-foreground">{{ n.body }}</p>
                </component>
            </li>
        </ul>
        <div v-else class="flex h-full items-center justify-center text-center text-sm text-muted-foreground">No notifications.</div>
    </div>
</template>
