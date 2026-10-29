<script setup lang="ts">
import NotificationSettings from '@/components/NotificationSettings.vue';
import Pagination from '@/components/Pagination.vue';
import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/AppLayout.vue';
import { visitQuery } from '@/lib/listQuery';
import { type BreadcrumbItem } from '@/types';
import { type Paginated } from '@/types/pagination';
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
import { computed, ref, watch } from 'vue';

type Severity = 'alert' | 'warning' | 'info' | 'success';

interface Note {
    id: string;
    title: string;
    body: string;
    read: boolean;
    severity: Severity;
    icon: string;
    on: string | null; // "3 hours ago"
    at: string | null; // ISO — the exact time, and the day it's grouped under
}

const props = defineProps<{
    /** A page of them (filtered on the server), with counts over the whole history. */
    notifications: { list: Paginated<Note>; all: number; unread: number };
    filter: 'all' | 'unread';
    kind: string | null;
    /** The kinds this person has, to filter by (key → name). */
    kinds: Record<string, string>;
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

const settingsOpen = ref(false);
const list = computed(() => props.notifications.list);
const hasTypes = Object.keys(props.types).length > 0;

// All / Unread and the kind live in the address, so paging and the live refresh keep them.
const setFilter = (filter: 'all' | 'unread') => {
    settingsOpen.value = false;
    visitQuery({ filter: filter === 'unread' ? 'unread' : undefined, page: undefined });
};
const setKind = (e: Event) => visitQuery({ kind: (e.target as HTMLSelectElement).value || undefined, page: undefined });

// Grouped under the day they arrived (Today / Yesterday / Monday, Oct 26), each with its time.
function dayLabel(iso: string | null): string {
    if (!iso) return 'Earlier';
    const d = new Date(iso);
    const today = new Date();
    const yesterday = new Date();
    yesterday.setDate(today.getDate() - 1);
    if (d.toDateString() === today.toDateString()) return 'Today';
    if (d.toDateString() === yesterday.toDateString()) return 'Yesterday';
    return d.toLocaleDateString(undefined, {
        weekday: 'long',
        month: 'short',
        day: 'numeric',
        ...(d.getFullYear() !== today.getFullYear() ? { year: 'numeric' } : {}),
    });
}
const timeOf = (iso: string | null): string => (iso ? new Date(iso).toLocaleTimeString([], { hour: 'numeric', minute: '2-digit' }) : '');
const groups = computed(() => {
    const out: { label: string; items: Note[] }[] = [];
    for (const n of list.value.data) {
        const label = dayLabel(n.at);
        const last = out[out.length - 1];
        if (last?.label === label) last.items.push(n);
        else out.push({ label, items: [n] });
    }
    return out;
});

// Ticking several, to mark read or dismiss together (only what's on this page).
const ticked = ref<string[]>([]);
watch(
    () => list.value.data.map((n) => n.id).join(),
    () => (ticked.value = ticked.value.filter((id) => list.value.data.some((n) => n.id === id))),
);
const allTicked = computed(() => list.value.data.length > 0 && list.value.data.every((n) => ticked.value.includes(n.id)));
const tick = (id: string) => (ticked.value = ticked.value.includes(id) ? ticked.value.filter((t) => t !== id) : [...ticked.value, id]);
const tickAll = () => (ticked.value = allTicked.value ? [] : list.value.data.map((n) => n.id));
const bulk = (action: 'read' | 'dismiss') =>
    router.post('/notifications/bulk', { ids: ticked.value, action }, { preserveScroll: true, onSuccess: () => (ticked.value = []) });

const toggleRead = (n: Note) =>
    router.post(`/notifications/${n.id}/${n.read ? 'unread' : 'read'}`, {}, { preserveScroll: true, preserveState: true });
const dismiss = (n: Note) => router.delete(`/notifications/${n.id}`, { preserveScroll: true, preserveState: true });

const readAll = () => router.post('/notifications/read-all', {}, { preserveScroll: true });
const clearAll = () => {
    if (confirm('Clear all notifications? This cannot be undone.')) {
        router.delete('/notifications', { preserveScroll: true });
    }
};

const emptyText = computed(() =>
    props.kind ? 'Nothing of this kind.' : props.filter === 'unread' ? 'No unread notifications.' : 'You’re all caught up.',
);
</script>

<template>
    <Head title="Notifications" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <template #actions>
            <div class="border-border inline-flex items-center gap-0.5 rounded-md border p-0.5 text-sm">
                <button
                    type="button"
                    class="rounded px-2 py-1 transition-colors"
                    :class="
                        !settingsOpen && props.filter === 'all'
                            ? 'bg-muted text-foreground font-medium'
                            : 'text-muted-foreground hover:text-foreground'
                    "
                    @click="setFilter('all')"
                >
                    All <span class="text-muted-foreground text-xs">{{ props.notifications.all }}</span>
                </button>
                <button
                    type="button"
                    class="rounded px-2 py-1 transition-colors"
                    :class="
                        !settingsOpen && props.filter === 'unread'
                            ? 'bg-muted text-foreground font-medium'
                            : 'text-muted-foreground hover:text-foreground'
                    "
                    @click="setFilter('unread')"
                >
                    Unread
                    <span class="text-xs" :class="props.notifications.unread ? 'text-sky-500' : 'text-muted-foreground'">{{
                        props.notifications.unread
                    }}</span>
                </button>
                <button
                    v-if="hasTypes"
                    type="button"
                    class="inline-flex items-center gap-1.5 rounded px-2 py-1 transition-colors"
                    :class="settingsOpen ? 'bg-muted text-foreground font-medium' : 'text-muted-foreground hover:text-foreground'"
                    title="Choose which notifications you get"
                    @click="settingsOpen = true"
                >
                    <Settings class="size-3.5" /> <span class="hidden sm:inline">Settings</span>
                </button>
            </div>
            <template v-if="!settingsOpen">
                <select
                    v-if="Object.keys(props.kinds).length > 1"
                    :value="props.kind ?? ''"
                    aria-label="Kind"
                    class="border-input bg-background h-8 w-40 rounded-md border px-2 text-sm"
                    @change="setKind"
                >
                    <option value="">Every kind</option>
                    <option v-for="(label, key) in props.kinds" :key="key" :value="key">{{ label }}</option>
                </select>
                <template v-if="ticked.length">
                    <Button size="sm" variant="outline" @click="bulk('read')"><Check class="mr-1 size-3.5" /> Mark {{ ticked.length }} read</Button>
                    <Button size="sm" variant="outline" @click="bulk('dismiss')"><X class="mr-1 size-3.5" /> Dismiss {{ ticked.length }}</Button>
                </template>
                <template v-else>
                    <Button v-if="props.notifications.unread > 0" size="sm" variant="outline" @click="readAll">Mark all read</Button>
                    <Button v-if="props.notifications.all > 0" size="sm" variant="outline" @click="clearAll">Clear all</Button>
                </template>
            </template>
        </template>

        <div class="mx-auto flex w-full max-w-2xl flex-1 flex-col gap-4 p-4">
            <NotificationSettings v-if="settingsOpen" :types="props.types" :preferences="props.preferences" :push-key="props.pushKey" />
            <template v-else>
                <div class="border-border overflow-hidden rounded-xl border">
                    <label v-if="list.data.length" class="bg-muted/40 text-muted-foreground flex items-center gap-3 px-4 py-2 text-xs">
                        <input
                            type="checkbox"
                            class="border-input accent-primary size-4 rounded pointer-coarse:size-5"
                            :checked="allTicked"
                            @change="tickAll"
                        />
                        Select all on this page
                    </label>
                    <template v-for="g in groups" :key="g.label">
                        <h2 class="border-border text-muted-foreground border-t px-4 pt-3 pb-1 text-xs font-semibold tracking-wide uppercase">
                            {{ g.label }}
                        </h2>
                        <ul class="divide-border divide-y text-sm">
                            <li
                                v-for="n in g.items"
                                :key="n.id"
                                class="group hover:bg-muted/40 flex items-start gap-3 px-4 py-3 transition-colors"
                                :class="{ 'bg-muted/30': !n.read }"
                            >
                                <input
                                    type="checkbox"
                                    class="border-input accent-primary mt-2 size-4 shrink-0 rounded pointer-coarse:size-5"
                                    :checked="ticked.includes(n.id)"
                                    :aria-label="`Select “${n.title}”`"
                                    @change="tick(n.id)"
                                />
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
                                    <p class="text-muted-foreground text-xs" :title="n.on ?? undefined">{{ timeOf(n.at) }}</p>
                                </Link>

                                <!-- Finger-sized on touch screens. -->
                                <div class="flex shrink-0 items-center gap-1">
                                    <button
                                        type="button"
                                        class="text-muted-foreground hover:bg-muted hover:text-foreground grid place-items-center rounded p-1.5 transition-colors pointer-coarse:size-11"
                                        :title="n.read ? 'Mark as unread' : 'Mark as read'"
                                        :aria-label="n.read ? 'Mark as unread' : 'Mark as read'"
                                        @click.stop="toggleRead(n)"
                                    >
                                        <component :is="n.read ? RotateCcw : Check" class="size-4 pointer-coarse:size-5" />
                                    </button>
                                    <button
                                        type="button"
                                        class="text-muted-foreground hover:bg-muted hover:text-foreground grid place-items-center rounded p-1.5 transition-colors pointer-coarse:size-11"
                                        title="Dismiss"
                                        aria-label="Dismiss"
                                        @click.stop="dismiss(n)"
                                    >
                                        <X class="size-4 pointer-coarse:size-5" />
                                    </button>
                                </div>
                            </li>
                        </ul>
                    </template>

                    <p v-if="list.data.length === 0" class="text-muted-foreground px-4 py-10 text-center text-sm">
                        <Bell class="mx-auto mb-2 size-6 opacity-50" />
                        {{ emptyText }}
                    </p>
                </div>
                <Pagination v-if="list.last_page > 1" :meta="list" />
            </template>
        </div>
    </AppLayout>
</template>
