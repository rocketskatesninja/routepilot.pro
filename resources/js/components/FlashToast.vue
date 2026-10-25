<script setup lang="ts">
import type { LiveNotification } from '@/echo';
import { isDesktop, popupsOn } from '@/lib/push';
import { router, usePage } from '@inertiajs/vue3';
import { Bell, CheckCircle2, X, XCircle } from 'lucide-vue-next';
import { onBeforeUnmount, onMounted, ref, watch } from 'vue';

interface Toast {
    id: number;
    type: 'success' | 'error' | 'notification';
    message: string;
    title?: string;
    url?: string | null;
    notificationId?: string;
}

const toasts = ref<Toast[]>([]);
let seq = 0;
const page = usePage();

function dismiss(id: number) {
    toasts.value = toasts.value.filter((t) => t.id !== id);
}

function push(toast: Omit<Toast, 'id'>, ms = 5000) {
    const id = ++seq;
    toasts.value.push({ id, ...toast });
    if (typeof window !== 'undefined') {
        window.setTimeout(() => dismiss(id), ms);
    }
}

// Surface one-shot flash messages (HandleInertiaRequests shares flash.success /
// flash.error) on every Inertia response.
watch(
    () => {
        const f = page.props.flash as { success?: string | null; error?: string | null } | undefined;
        return [f?.success ?? null, f?.error ?? null] as const;
    },
    ([success, error]) => {
        if (success) push({ type: 'success', message: success });
        if (error) push({ type: 'error', message: error });
    },
    { immediate: true },
);

// A live notification (app.ts re-dispatches it from Echo) pops up in the corner
// on a desktop, unless pop-ups are off on this computer. Phones get web push instead.
function onNotification(event: Event) {
    const n = (event as CustomEvent<LiveNotification>).detail;
    if (!isDesktop() || !popupsOn()) return;
    push({ type: 'notification', title: n.title ?? 'Notification', message: n.body ?? '', url: n.url, notificationId: n.id }, 8000);
}
onMounted(() => window.addEventListener('rp:notification', onNotification));
onBeforeUnmount(() => window.removeEventListener('rp:notification', onNotification));

/** Open it, like the notifications page does: mark it read, then go where it points. */
function open(t: Toast) {
    dismiss(t.id);
    router.post(
        `/notifications/${t.notificationId}/read`,
        {},
        {
            preserveScroll: true,
            onSuccess: () => {
                if (t.url) router.visit(t.url);
            },
        },
    );
}
</script>

<template>
    <div class="pointer-events-none fixed inset-x-4 bottom-4 z-[70] flex flex-col gap-2 sm:inset-x-auto sm:right-4 sm:w-full sm:max-w-sm">
        <TransitionGroup name="rp-toast">
            <template v-for="t in toasts" :key="t.id">
                <div
                    v-if="t.type === 'notification'"
                    role="status"
                    class="border-border bg-background text-foreground pointer-events-auto flex items-start gap-3 rounded-xl border p-3 text-sm shadow-lg"
                >
                    <span class="flex size-8 shrink-0 items-center justify-center rounded-lg bg-sky-500/10">
                        <Bell class="size-4 text-sky-500" />
                    </span>
                    <button type="button" class="min-w-0 flex-1 cursor-pointer text-left" @click="open(t)">
                        <span class="block truncate font-medium">{{ t.title }}</span>
                        <span v-if="t.message" class="text-muted-foreground line-clamp-2">{{ t.message }}</span>
                    </button>
                    <button
                        type="button"
                        class="shrink-0 opacity-60 transition-opacity hover:opacity-100"
                        aria-label="Dismiss"
                        @click="dismiss(t.id)"
                    >
                        <X class="size-4" />
                    </button>
                </div>
                <div
                    v-else
                    role="status"
                    class="pointer-events-auto flex items-start gap-2 rounded-lg border p-3 text-sm shadow-lg"
                    :class="
                        t.type === 'success'
                            ? 'border-emerald-500/30 bg-emerald-50 text-emerald-800 dark:bg-emerald-950/70 dark:text-emerald-200'
                            : 'border-red-500/30 bg-red-50 text-red-800 dark:bg-red-950/70 dark:text-red-200'
                    "
                >
                    <component :is="t.type === 'success' ? CheckCircle2 : XCircle" class="mt-0.5 size-4 shrink-0" />
                    <span class="flex-1">{{ t.message }}</span>
                    <button
                        type="button"
                        class="shrink-0 opacity-60 transition-opacity hover:opacity-100"
                        aria-label="Dismiss"
                        @click="dismiss(t.id)"
                    >
                        <X class="size-4" />
                    </button>
                </div>
            </template>
        </TransitionGroup>
    </div>
</template>

<style scoped>
.rp-toast-enter-active,
.rp-toast-leave-active {
    transition: all 0.25s ease;
}
.rp-toast-enter-from,
.rp-toast-leave-to {
    opacity: 0;
    transform: translateX(1rem);
}
.rp-toast-leave-active {
    position: absolute;
    right: 0;
    width: 100%;
}
</style>
