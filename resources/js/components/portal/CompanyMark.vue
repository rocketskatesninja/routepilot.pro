<script setup lang="ts">
/** The company at the top of a page its emails link to: its logo with its name beside it (the name alone without a logo). */
defineProps<{ company: { name: string; logo: string | null } }>();
</script>

<template>
    <div class="flex items-center gap-2.5 self-start">
        <img v-if="company.logo" :src="company.logo" alt="" class="h-10 w-auto shrink-0" />
        <p class="font-display text-lg font-semibold">{{ company.name }}</p>
    </div>
</template>
