<script setup lang="ts">
import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem } from '@/types';
import { Head, router } from '@inertiajs/vue3';
import {
    Banknote,
    Bell,
    CalendarClock,
    Check,
    CircleCheck,
    CreditCard,
    Inbox,
    RotateCcw,
    TriangleAlert,
    UserPlus,
    X,
    type LucideIcon,
} from 'lucide-vue-next';
import { computed, ref } from 'vue';

type Severity = 'alert' | 'warning' | 'info' | 'success';

interface Note {
    id: string;
    title: string;
    body: string;
    url: string | null;
    read: boolean;
    severity: Severity;
    icon: string;
    on: string | null;
}

const props = defineProps<{ notifications: Note[] }>();

const breadcrumbs: BreadcrumbItem[] = [{ title: 'Notifications', href: '/notifications' }];

// Icon names come from the server (derived from the notification type); map to components.
const ICONS: Record<string, LucideIcon> = {
    TriangleAlert,
    CreditCard,
    Banknote,
    CalendarClock,
    CircleCheck,
    UserPlus,
    Inbox,
    Bell,
};
const iconFor = (n: Note): LucideIcon => ICONS[n.icon] ?? Bell;

// Severity → the icon badge's colors (fixed semantic colors, like the health rings).
const SEVERITY: Record<Severity, { text: string; bg: string }> = {
    alert: { text: 'text-red-500', bg: 'bg-red-500/10' },
    warning: { text: 'text-amber-500', bg: 'bg-amber-500/10' },
    info: { text: 'text-sky-500', bg: 'bg-sky-500/10' },
    success: { text: 'text-emerald-500', bg: 'bg-emerald-500/10' },
};

const tab = ref<'all' | 'unread'>('all');
const unreadCount = computed(() => props.notifications.filter((n) => !n.read).length);
const shown = computed(() => (tab.value === 'unread' ? props.notifications.filter((n) => !n.read) : props.notifications));

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
const toggleRead = (n: Note) =>
    router.post(`/notifications/${n.id}/${n.read ? 'unread' : 'read'}`, {}, { preserveScroll: true, preserveState: true });
const dismiss = (n: Note) => router.delete(`/notifications/${n.id}`, { preserveScroll: true, preserveState: true });

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
            <div class="inline-flex items-center gap-0.5 rounded-md border border-border p-0.5 text-sm">
                <button
                    type="button"
                    class="rounded px-2 py-1 transition-colors"
                    :class="tab === 'all' ? 'bg-muted font-medium text-foreground' : 'text-muted-foreground hover:text-foreground'"
                    @click="tab = 'all'"
                >
                    All <span class="text-xs text-muted-foreground">{{ props.notifications.length }}</span>
                </button>
                <button
                    type="button"
                    class="rounded px-2 py-1 transition-colors"
                    :class="tab === 'unread' ? 'bg-muted font-medium text-foreground' : 'text-muted-foreground hover:text-foreground'"
                    @click="tab = 'unread'"
                >
                    Unread <span class="text-xs" :class="unreadCount ? 'text-sky-500' : 'text-muted-foreground'">{{ unreadCount }}</span>
                </button>
            </div>
            <Button v-if="unreadCount > 0" size="sm" variant="outline" @click="readAll">Mark all read</Button>
            <Button v-if="props.notifications.length > 0" size="sm" variant="outline" @click="clearAll">Clear all</Button>
        </template>

        <div class="mx-auto flex w-full max-w-2xl flex-1 flex-col gap-4 p-4">
            <div class="overflow-hidden rounded-xl border border-border">
                <ul class="divide-y divide-border text-sm">
                    <li
                        v-for="n in shown"
                        :key="n.id"
                        class="group flex items-start gap-3 px-4 py-3 transition-colors hover:bg-muted/40"
                        :class="{ 'bg-muted/30': !n.read }"
                    >
                        <span
                            class="mt-0.5 flex size-8 shrink-0 items-center justify-center rounded-lg"
                            :class="[SEVERITY[n.severity].bg, n.read ? 'opacity-60' : '']"
                        >
                            <component :is="iconFor(n)" class="size-4" :class="SEVERITY[n.severity].text" />
                        </span>

                        <button type="button" class="min-w-0 flex-1 cursor-pointer text-left" @click="open(n)">
                            <div class="flex items-center gap-2">
                                <span class="truncate font-medium" :class="{ 'text-muted-foreground': n.read }">{{ n.title }}</span>
                                <span v-if="!n.read" class="size-2 shrink-0 rounded-full bg-sky-500" aria-label="Unread" />
                            </div>
                            <p class="text-muted-foreground">{{ n.body }}</p>
                            <p class="text-xs text-muted-foreground">{{ n.on }}</p>
                        </button>

                        <div class="flex shrink-0 items-center gap-1">
                            <button
                                type="button"
                                class="rounded p-1.5 text-muted-foreground transition-colors hover:bg-muted hover:text-foreground"
                                :title="n.read ? 'Mark as unread' : 'Mark as read'"
                                @click.stop="toggleRead(n)"
                            >
                                <component :is="n.read ? RotateCcw : Check" class="size-4" />
                            </button>
                            <button
                                type="button"
                                class="rounded p-1.5 text-muted-foreground transition-colors hover:bg-muted hover:text-foreground"
                                title="Dismiss"
                                @click.stop="dismiss(n)"
                            >
                                <X class="size-4" />
                            </button>
                        </div>
                    </li>

                    <li v-if="shown.length === 0" class="px-4 py-10 text-center text-muted-foreground">
                        <Bell class="mx-auto mb-2 size-6 opacity-50" />
                        {{ tab === 'unread' ? 'No unread notifications.' : "You're all caught up." }}
                    </li>
                </ul>
            </div>
        </div>
    </AppLayout>
</template>
