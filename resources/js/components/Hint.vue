<script setup lang="ts">
import { Tooltip, TooltipContent, TooltipProvider, TooltipTrigger } from '@/components/ui/tooltip';

/**
 * A short explanation of what a control does, shown on hover or keyboard focus.
 * Wraps the one element it explains (usually a button). Brings its own provider,
 * so it works in layouts without the sidebar too.
 */
withDefaults(defineProps<{ text: string; side?: 'top' | 'right' | 'bottom' | 'left' }>(), { side: 'bottom' });
</script>

<template>
    <TooltipProvider :delay-duration="300">
        <Tooltip>
            <TooltipTrigger as-child><slot /></TooltipTrigger>
            <TooltipContent :side="side" class="max-w-64 text-xs leading-snug">{{ text }}</TooltipContent>
        </Tooltip>
    </TooltipProvider>
</template>
