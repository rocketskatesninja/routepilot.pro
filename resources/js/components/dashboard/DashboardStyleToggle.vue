<script setup lang="ts">
import { router } from '@inertiajs/vue3';
import { LayoutGrid, Rows3 } from 'lucide-vue-next';

/** For customers: switch between the simple Home page and the customizable widget dashboard. */
defineProps<{ current: 'simple' | 'widgets' }>();

const choose = (style: 'simple' | 'widgets') => router.post('/dashboard/style', { style });
</script>

<template>
    <div class="border-input flex rounded-md border p-0.5" role="group" aria-label="Dashboard style">
        <button
            v-for="opt in [
                { key: 'simple', label: 'Simple', icon: Rows3 },
                { key: 'widgets', label: 'Widgets', icon: LayoutGrid },
            ] as const"
            :key="opt.key"
            type="button"
            class="flex items-center gap-1.5 rounded px-2.5 py-1 text-sm font-medium transition-colors"
            :class="current === opt.key ? 'bg-muted text-foreground' : 'text-muted-foreground hover:text-foreground'"
            :aria-pressed="current === opt.key"
            @click="current !== opt.key && choose(opt.key)"
        >
            <component :is="opt.icon" class="size-4" />
            {{ opt.label }}
        </button>
    </div>
</template>
