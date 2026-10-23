<script setup lang="ts">
// One collapsible group in the landing editor: a settings block or a section of
// the page (which adds its drag handle before the title and its show/hide after).
import { ChevronDown } from 'lucide-vue-next';

defineProps<{ title: string; open: boolean }>();
defineEmits<{ toggle: [] }>();
</script>

<template>
    <section class="border-border rounded-xl border">
        <div class="flex items-center gap-2 px-3 py-2.5">
            <slot name="before" />
            <button
                type="button"
                class="flex flex-1 items-center justify-between gap-2 py-0.5 text-left text-sm font-medium"
                :aria-expanded="open"
                @click="$emit('toggle')"
            >
                {{ title }}
                <ChevronDown class="text-muted-foreground size-4 shrink-0 transition-transform" :class="open ? 'rotate-180' : ''" />
            </button>
            <slot name="after" />
        </div>
        <div v-show="open" class="border-border bg-muted/20 space-y-6 border-t p-4 text-sm"><slot /></div>
    </section>
</template>
