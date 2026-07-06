<script setup lang="ts">
import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem } from '@/types';
import { Head, router } from '@inertiajs/vue3';
import { Bell } from 'lucide-vue-next';

interface Note {
    id: string;
    title: string;
    body: string;
    url: string | null;
    read: boolean;
    on: string | null;
}

const props = defineProps<{ notifications: Note[] }>();

const breadcrumbs: BreadcrumbItem[] = [{ title: 'Notifications', href: '/notifications' }];

function open(n: Note) {
    router.post(
        `/notifications/${n.id}/read`,
        {},
        {
            preserveScroll: true,
            onSuccess: () => {
                if (n.url) router.visit(n.url);
            },
        },
    );
}
const readAll = () => router.post('/notifications/read-all', {}, { preserveScroll: true });
const clearAll = () => {
    if (confirm('Clear all notifications? This cannot be undone.')) {
        router.delete('/notifications', { preserveScroll: true });
    }
};
</script>

<template>
    <Head title="Notifications" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <template #actions>
            <Button v-if="props.notifications.some((n) => !n.read)" size="sm" variant="outline" @click="readAll">Mark all read</Button>
            <Button v-if="props.notifications.length > 0" size="sm" variant="outline" @click="clearAll">Clear all</Button>
        </template>

        <div class="mx-auto flex w-full max-w-2xl flex-1 flex-col gap-4 p-4">
            <div class="overflow-hidden rounded-xl border border-border">
                <ul class="divide-y divide-border text-sm">
                    <li
                        v-for="n in props.notifications"
                        :key="n.id"
                        class="flex cursor-pointer items-start justify-between gap-3 px-4 py-3 transition-colors hover:bg-muted/40"
                        :class="{ 'bg-muted/30': !n.read }"
                        @click="open(n)"
                    >
                        <div>
                            <div class="font-medium" :class="{ 'text-muted-foreground': n.read }">{{ n.title }}</div>
                            <p class="text-muted-foreground">{{ n.body }}</p>
                            <p class="text-xs text-muted-foreground">{{ n.on }}</p>
                        </div>
                        <span v-if="!n.read" class="mt-1 size-2 shrink-0 rounded-full bg-sky-500" aria-label="Unread" />
                    </li>
                    <li v-if="props.notifications.length === 0" class="px-4 py-10 text-center text-muted-foreground">
                        <Bell class="mx-auto mb-2 size-6 opacity-50" />
                        You're all caught up.
                    </li>
                </ul>
            </div>
        </div>
    </AppLayout>
</template>
