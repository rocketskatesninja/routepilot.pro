<script setup lang="ts">
/** A status word in a soft pill. Tone carries meaning; the word always says it too. */
withDefaults(defineProps<{ tone?: 'good' | 'warn' | 'info' | 'bad' | 'muted' }>(), { tone: 'muted' });
</script>

<template>
    <span
        class="inline-flex shrink-0 items-center rounded-full px-2.5 py-0.5 text-xs font-medium whitespace-nowrap"
        :class="
            {
                good: 'bg-emerald-500/15 text-emerald-700 dark:text-emerald-400',
                warn: 'bg-amber-500/15 text-amber-700 dark:text-amber-400',
                info: 'bg-sky-500/15 text-sky-700 dark:text-sky-400',
                bad: 'bg-red-500/15 text-red-700 dark:text-red-400',
                muted: 'bg-muted text-muted-foreground',
            }[tone]
        "
        ><slot
    /></span>
</template>
