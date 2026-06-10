<script setup lang="ts">
import { router, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';

const page = usePage();
const impersonating = computed(() => (page.props.auth as { impersonating?: boolean } | undefined)?.impersonating === true);

const stop = () => router.post('/impersonate/stop');
</script>

<template>
    <div v-if="impersonating" class="flex items-center justify-between gap-3 bg-amber-500 px-4 py-1.5 text-sm font-medium text-amber-950">
        <span>You're signed in as this company's admin (impersonating).</span>
        <button class="rounded bg-amber-950/15 px-2 py-0.5 transition-colors hover:bg-amber-950/25" @click="stop">Stop impersonating</button>
    </div>
</template>
