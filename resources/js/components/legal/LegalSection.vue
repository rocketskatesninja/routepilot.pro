<script setup lang="ts">
defineProps<{ n: number; id: string; title: string }>();
</script>

<template>
    <section :id="id" class="border-border bg-background scroll-mt-24 rounded-xl border p-6">
        <h2 class="mb-3 flex items-center gap-2 text-lg font-bold">
            <span class="bg-primary text-primary-foreground flex size-7 shrink-0 items-center justify-center rounded-full text-xs font-bold">{{
                n
            }}</span>
            {{ title }}
        </h2>
        <div
            class="text-muted-foreground [&_a]:text-primary [&_strong]:text-foreground space-y-3 text-sm leading-relaxed [&_a]:underline [&_li]:ml-1 [&_ul]:list-disc [&_ul]:space-y-1 [&_ul]:pl-5"
        >
            <slot />
        </div>
    </section>
</template>
