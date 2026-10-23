<script setup lang="ts">
// A form on a company's site: its fields (the slot), then the error and the send
// button; once sent, the thank-you (#sent) takes its place. The thank-you has no
// scroll fade-in: it appears after page load, so the reveal observer would never
// show it.
import { CircleCheck } from 'lucide-vue-next';
import { button } from '../layout';
import type { LeadFormState } from '../useLeadForm';

defineProps<{ lead: LeadFormState; label: string; disabled?: boolean }>();
defineEmits<{ submit: [] }>();
</script>

<template>
    <div
        v-if="lead.sent"
        role="status"
        class="flex items-start gap-4 rounded-2xl border border-emerald-500/30 bg-emerald-500/10 p-8 text-emerald-800 dark:text-emerald-300"
    >
        <CircleCheck class="mt-0.5 size-6 shrink-0" />
        <p class="text-lg"><slot name="sent" /></p>
    </div>
    <form v-else class="grid gap-4 @3xl:grid-cols-2" @submit.prevent="$emit('submit')">
        <slot />
        <p v-if="lead.error" class="text-base text-red-600 @3xl:col-span-2 dark:text-red-400">{{ lead.error }}</p>
        <div class="@3xl:col-span-2">
            <button type="submit" :disabled="lead.sending || disabled" class="w-full @3xl:w-auto" :class="button">
                {{ lead.sending ? 'Sending…' : label }}
            </button>
        </div>
    </form>
</template>
