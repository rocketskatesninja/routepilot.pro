<script setup lang="ts">
import NotificationSettings from '@/components/NotificationSettings.vue';
import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem } from '@/types';
import { Head, Link, router } from '@inertiajs/vue3';
import {
    Banknote,
    Bell,
    CalendarClock,
    Check,
    CircleCheck,
    CreditCard,
    Inbox,
    RotateCcw,
    Settings,
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
    read: boolean;
    severity: Severity;
    icon: string;
    on: string | null;
}

const props = defineProps<{
    notifications: Note[];
    /** The types this user gets, each with a switch under Settings. */
    types: Record<string, [string, string]>;
    preferences: Record<string, boolean>;
    /** The server's web-push key; null when phone notifications aren't set up. */
    pushKey: string | null;
}>();

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

const tab = ref<'all' | 'unread' | 'settings'>('all');
const unreadCount = computed(() => props.notifications.filter((n) => !n.read).length);
const shown = computed(() => (tab.value === 'unread' ? props.notifications.filter((n) => !n.read) : props.notifications));

const toggleRead = (n: Note) =>
    router.post(`/notifications/${n.id}/${n.read ? 'unread' : 'read'}`, {}, { preserveScroll: true, preserveState: true });
const dismiss = (n: Note) => router.delete(`/notifications/${n.id}`, { preserveScroll: true, preserveState: true });

const hasTypes = Object.keys(props.types).length > 0;

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
            <div class="border-border inline-flex items-center gap-0.5 rounded-md border p-0.5 text-sm">
                <button
                    type="button"
                    class="rounded px-2 py-1 transition-colors"
                    :class="tab === 'all' ? 'bg-muted text-foreground font-medium' : 'text-muted-foreground hover:text-foreground'"
                    @click="tab = 'all'"
                >
                    All <span class="text-muted-foreground text-xs">{{ props.notifications.length }}</span>
                </button>
                <button
                    type="button"
                    class="rounded px-2 py-1 transition-colors"
                    :class="tab === 'unread' ? 'bg-muted text-foreground font-medium' : 'text-muted-foreground hover:text-foreground'"
                    @click="tab = 'unread'"
                >
                    Unread <span class="text-xs" :class="unreadCount ? 'text-sky-500' : 'text-muted-foreground'">{{ unreadCount }}</span>
                </button>
                <button
                    v-if="hasTypes"
                    type="button"
                    class="inline-flex items-center gap-1.5 rounded px-2 py-1 transition-colors"
                    :class="tab === 'settings' ? 'bg-muted text-foreground font-medium' : 'text-muted-foreground hover:text-foreground'"
                    title="Choose which notifications you get"
                    @click="tab = 'settings'"
                >
                    <Settings class="size-3.5" /> <span class="hidden sm:inline">Settings</span>
                </button>
            </div>
            <template v-if="tab !== 'settings'">
                <Button v-if="unreadCount > 0" size="sm" variant="outline" @click="readAll">Mark all read</Button>
                <Button v-if="props.notifications.length > 0" size="sm" variant="outline" @click="clearAll">Clear all</Button>
            </template>
        </template>

        <div class="mx-auto flex w-full max-w-2xl flex-1 flex-col gap-4 p-4">
            <NotificationSettings v-if="tab === 'settings'" :types="props.types" :preferences="props.preferences" :push-key="props.pushKey" />
            <div v-else class="border-border overflow-hidden rounded-xl border">
                <ul class="divide-border divide-y text-sm">
                    <li
                        v-for="n in shown"
                        :key="n.id"
                        class="group hover:bg-muted/40 flex items-start gap-3 px-4 py-3 transition-colors"
                        :class="{ 'bg-muted/30': !n.read }"
                    >
                        <span
                            class="mt-0.5 flex size-8 shrink-0 items-center justify-center rounded-lg"
                            :class="[SEVERITY[n.severity].bg, n.read ? 'opacity-60' : '']"
                        >
                            <component :is="iconFor(n)" class="size-4" :class="SEVERITY[n.severity].text" />
                        </span>

                        <!-- Opening marks it read on the way (NotificationController::open). -->
                        <Link :href="`/notifications/${n.id}/open`" class="min-w-0 flex-1 text-left">
                            <div class="flex items-center gap-2">
                                <span class="truncate font-medium" :class="{ 'text-muted-foreground': n.read }">{{ n.title }}</span>
                                <span v-if="!n.read" class="size-2 shrink-0 rounded-full bg-sky-500" aria-label="Unread" />
                            </div>
                            <p class="text-muted-foreground">{{ n.body }}</p>
                            <p class="text-muted-foreground text-xs">{{ n.on }}</p>
                        </Link>

                        <div class="flex shrink-0 items-center gap-1">
                            <button
                                type="button"
                                class="text-muted-foreground hover:bg-muted hover:text-foreground rounded p-1.5 transition-colors"
                                :title="n.read ? 'Mark as unread' : 'Mark as read'"
                                @click.stop="toggleRead(n)"
                            >
                                <component :is="n.read ? RotateCcw : Check" class="size-4" />
                            </button>
                            <button
                                type="button"
                                class="text-muted-foreground hover:bg-muted hover:text-foreground rounded p-1.5 transition-colors"
                                title="Dismiss"
                                @click.stop="dismiss(n)"
                            >
                                <X class="size-4" />
                            </button>
                        </div>
                    </li>

                    <li v-if="shown.length === 0" class="text-muted-foreground px-4 py-10 text-center">
                        <Bell class="mx-auto mb-2 size-6 opacity-50" />
                        {{ tab === 'unread' ? 'No unread notifications.' : "You're all caught up." }}
                    </li>
                </ul>
            </div>
        </div>
    </AppLayout>
</template>
