<script setup lang="ts">
defineProps<{ n: number; id: string; title: string }>();
</script>

<template>
    <section :id="id" class="scroll-mt-24 rounded-xl border border-border bg-background p-6">
        <h2 class="mb-3 flex items-center gap-2 text-lg font-bold">
            <span class="flex size-7 shrink-0 items-center justify-center rounded-full bg-primary text-xs font-bold text-primary-foreground">{{
                n
            }}</span>
            {{ title }}
        </h2>
        <div
            class="space-y-3 text-sm leading-relaxed text-muted-foreground [&_a]:text-primary [&_a]:underline [&_li]:ml-1 [&_strong]:text-foreground [&_ul]:list-disc [&_ul]:space-y-1 [&_ul]:pl-5"
        >
            <slot />
        </div>
    </section>
</template>
