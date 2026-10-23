<script setup lang="ts">
// What a form turns into once it's sent. No scroll fade-in: it appears after
// page load, so the reveal observer would never show it.
import { CircleCheck } from 'lucide-vue-next';
</script>

<template>
    <div
        role="status"
        class="flex items-start gap-4 rounded-2xl border border-emerald-500/30 bg-emerald-500/10 p-8 text-emerald-800 dark:text-emerald-300"
    >
        <CircleCheck class="mt-0.5 size-6 shrink-0" />
        <p class="text-lg"><slot /></p>
    </div>
</template>
