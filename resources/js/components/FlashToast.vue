<script setup lang="ts">
import { usePage } from '@inertiajs/vue3';
import { CheckCircle2, X, XCircle } from 'lucide-vue-next';
import { ref, watch } from 'vue';

interface Toast {
    id: number;
    type: 'success' | 'error';
    message: string;
}

const toasts = ref<Toast[]>([]);
let seq = 0;
const page = usePage();

function dismiss(id: number) {
    toasts.value = toasts.value.filter((t) => t.id !== id);
}

function push(type: 'success' | 'error', message: string) {
    const id = ++seq;
    toasts.value.push({ id, type, message });
    if (typeof window !== 'undefined') {
        window.setTimeout(() => dismiss(id), 5000);
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
        if (success) push('success', success);
        if (error) push('error', error);
    },
    { immediate: true },
);
</script>

<template>
    <div class="pointer-events-none fixed bottom-4 right-4 z-[70] flex w-full max-w-sm flex-col gap-2">
        <TransitionGroup name="rp-toast">
            <div
                v-for="t in toasts"
                :key="t.id"
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
                <button type="button" class="shrink-0 opacity-60 transition-opacity hover:opacity-100" aria-label="Dismiss" @click="dismiss(t.id)">
                    <X class="size-4" />
                </button>
            </div>
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
