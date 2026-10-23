<script setup lang="ts">
// A labelled setting in the landing editor, with an optional hint underneath.
import { Label } from '@/components/ui/label';

defineProps<{ label?: string; hint?: string }>();
</script>

<template>
    <div class="grid gap-1.5">
        <Label v-if="label || $slots.label"
            ><slot name="label">{{ label }}</slot></Label
        >
        <slot />
        <p v-if="hint || $slots.hint" class="text-muted-foreground text-xs">
            <slot name="hint">{{ hint }}</slot>
        </p>
    </div>
</template>
