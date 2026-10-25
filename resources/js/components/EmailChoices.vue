<script setup lang="ts">
/**
 * The optional emails a customer can turn on or off, as calm rows with a
 * switch each. Used on the page linked from emails, in the customer's portal
 * settings, and on the customer's panel in the office. NotificationSettings
 * reuses it for this device and the in-app types, with its own notes.
 */
defineProps<{
    types: Record<string, [string, string]>;
    preferences: Record<string, boolean>;
    busy?: boolean;
    /** "you" for the customer, "they" for the office. */
    who?: 'you' | 'they';
}>();
const emit = defineEmits<{ change: [type: string, on: boolean] }>();
</script>

<template>
    <ul class="border-border divide-border divide-y rounded-2xl border">
        <li v-for="(meta, type) in types" :key="type" class="flex items-center justify-between gap-4 px-4 py-3.5 sm:px-5">
            <span class="min-w-0">
                <span :id="`email-${type}`" class="block font-medium">{{ meta[0] }}</span>
                <span class="text-muted-foreground block text-sm">{{ meta[1] }}</span>
            </span>
            <button
                type="button"
                role="switch"
                :aria-checked="preferences[type]"
                :aria-labelledby="`email-${type}`"
                :disabled="busy"
                class="focus-visible:ring-ring relative inline-flex h-6 w-11 shrink-0 items-center rounded-full transition-colors focus-visible:ring-2 focus-visible:ring-offset-2 focus-visible:outline-none disabled:opacity-60"
                :class="preferences[type] ? 'bg-primary' : 'bg-input'"
                @click="emit('change', type as string, !preferences[type])"
            >
                <span class="sr-only">{{ preferences[type] ? 'On' : 'Off' }}</span>
                <span
                    class="inline-block size-5 rounded-full bg-white shadow transition-transform"
                    :class="preferences[type] ? 'translate-x-[22px]' : 'translate-x-0.5'"
                />
            </button>
        </li>
    </ul>
    <p class="text-muted-foreground mt-2 text-sm">
        <slot name="note">
            Receipts, bills and answers to {{ who === 'they' ? 'their' : 'your' }} requests always come, since they’re about
            {{ who === 'they' ? 'their' : 'your' }} account.
        </slot>
    </p>
</template>
