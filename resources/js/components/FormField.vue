<script setup lang="ts">
import { Label } from '@/components/ui/label';

/** A form control with its label above and the server's error (or a hint) below. */
defineProps<{
    label: string;
    /** The control's id, so a click on the label focuses it. */
    for?: string;
    error?: string;
    hint?: string;
}>();
</script>

<template>
    <div class="grid gap-1.5">
        <Label :for="$props.for">{{ label }}</Label>
        <slot />
        <p v-if="error" class="text-xs text-red-600">{{ error }}</p>
        <p v-else-if="hint" class="text-muted-foreground text-xs">{{ hint }}</p>
    </div>
</template>
