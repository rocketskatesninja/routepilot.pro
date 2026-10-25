<script setup lang="ts">
import EmailChoices from '@/components/EmailChoices.vue';
import HeadingSmall from '@/components/HeadingSmall.vue';
import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/AppLayout.vue';
import { isDesktop, popupsOn, pushState, setPopups, turnOffPush, turnOnPush, type PushState } from '@/lib/push';
import { type BreadcrumbItem } from '@/types';
import { Head, Link, router, usePage } from '@inertiajs/vue3';
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
import { computed, onMounted, ref } from 'vue';

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

const auth = usePage().props.auth;
const isCustomer = auth.role === 'customer';
const hasTypes = Object.keys(props.types).length > 0;
const busy = ref(false);
const setType = (type: string, on: boolean) =>
    router.patch(
        '/notifications/types',
        { [type]: on },
        { preserveScroll: true, preserveState: true, onStart: () => (busy.value = true), onFinish: () => (busy.value = false) },
    );

// This device: pop-ups on a computer, real notifications on a phone. Known only
// in the browser, so it's settled after mount (SSR renders neither).
const device = ref<'computer' | 'phone' | null>(null);
const popups = ref(true);
const phone = ref<PushState>('off');
const deviceBusy = ref(false);
onMounted(async () => {
    if (isDesktop()) {
        device.value = 'computer';
        popups.value = popupsOn();
    } else if (props.pushKey && !auth.impersonating) {
        device.value = 'phone';
        phone.value = await pushState();
    }
});
const deviceRow = computed((): Record<string, [string, string]> | null => {
    if (device.value === 'computer')
        return { device: ['Pop-ups on this computer', 'A note in the corner when one arrives while RoutePilot is open.'] };
    if (device.value === 'phone') return { device: ['Notifications on this phone', 'On your lock screen, even when RoutePilot is closed.'] };
    return null;
});
const deviceOn = computed(() => (device.value === 'computer' ? popups.value : phone.value === 'on'));
async function setDevice(_: string, on: boolean) {
    if (device.value === 'computer') {
        setPopups(on);
        popups.value = on;
        return;
    }
    deviceBusy.value = true;
    try {
        phone.value = on ? await turnOnPush(props.pushKey as string) : await turnOffPush();
    } catch {
        phone.value = await pushState();
    } finally {
        deviceBusy.value = false;
    }
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
            <div v-if="tab === 'settings'" class="space-y-8">
                <section v-if="deviceRow" class="space-y-3">
                    <HeadingSmall :title="device === 'computer' ? 'This computer' : 'This phone'" />
                    <EmailChoices
                        :types="deviceRow"
                        :preferences="{ device: deviceOn }"
                        :busy="deviceBusy || phone === 'blocked' || phone === 'unsupported'"
                        @change="setDevice"
                    >
                        <template #note>
                            <template v-if="device === 'computer'"
                                >Only affects this computer. To get notifications on your phone, open this Settings tab on your phone and turn them on
                                there.</template
                            >
                            <template v-else-if="phone === 'blocked'">
                                Notifications are blocked for this site. Allow them in your browser’s site settings, then come back.
                            </template>
                            <template v-else-if="phone === 'unsupported'">
                                This browser can’t show notifications. On an iPhone, add RoutePilot to your Home Screen and open it from there.
                            </template>
                            <template v-else>Only affects this phone. It gets the same types you choose below.</template>
                        </template>
                    </EmailChoices>
                </section>

                <section class="space-y-3">
                    <HeadingSmall title="What to tell me about" description="Each switch saves right away." />
                    <EmailChoices :types="props.types" :preferences="props.preferences" :busy="busy" @change="setType">
                        <template #note>
                            Types you turn off won’t show up here, pop up or reach your phone.
                            <template v-if="isCustomer">
                                Emails are set separately in
                                <Link href="/settings/emails" class="text-foreground underline underline-offset-4">Email settings</Link>.
                            </template>
                        </template>
                    </EmailChoices>
                </section>
            </div>

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

                        <button type="button" class="min-w-0 flex-1 cursor-pointer text-left" @click="open(n)">
                            <div class="flex items-center gap-2">
                                <span class="truncate font-medium" :class="{ 'text-muted-foreground': n.read }">{{ n.title }}</span>
                                <span v-if="!n.read" class="size-2 shrink-0 rounded-full bg-sky-500" aria-label="Unread" />
                            </div>
                            <p class="text-muted-foreground">{{ n.body }}</p>
                            <p class="text-muted-foreground text-xs">{{ n.on }}</p>
                        </button>

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
