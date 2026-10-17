<script setup lang="ts">
/** A note shown as a message from someone: "Ashley wrote", then what they said. */
defineProps<{ author: string }>();
</script>

<template>
    <div class="bg-muted max-w-prose rounded-2xl rounded-tl-md px-4 py-2.5 text-sm">
        <p class="text-muted-foreground text-xs font-medium">{{ author }}</p>
        <p class="whitespace-pre-line"><slot /></p>
    </div>
</template>
