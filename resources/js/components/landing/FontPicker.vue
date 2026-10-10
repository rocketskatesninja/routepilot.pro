<script setup lang="ts">
import { TITLE_FONTS } from '@/components/landing/titleStyle';
import { DropdownMenu, DropdownMenuContent, DropdownMenuItem, DropdownMenuTrigger } from '@/components/ui/dropdown-menu';
import { Check, ChevronDown } from 'lucide-vue-next';
import { computed } from 'vue';

// A font <select> can't preview each option in its own typeface — native <option>
// elements ignore font-family on Chromium/Linux. This custom dropdown renders each
// font name in that actual font (all title fonts are loaded by the builder page).
const props = defineProps<{ modelValue: string }>();
const emit = defineEmits<{ 'update:modelValue': [value: string] }>();

const currentStack = computed(() => TITLE_FONTS.find((f) => f.name === props.modelValue)?.stack ?? "'Inter', sans-serif");
</script>

<template>
    <DropdownMenu>
        <DropdownMenuTrigger as-child>
            <button
                type="button"
                class="border-input bg-background hover:bg-accent focus-visible:ring-ring flex h-9 w-full items-center justify-between gap-2 rounded-md border px-2 text-sm transition-colors focus:outline-none focus-visible:ring-2"
            >
                <span class="truncate" :style="{ fontFamily: currentStack }">{{ modelValue }}</span>
                <ChevronDown class="text-muted-foreground size-4 shrink-0" />
            </button>
        </DropdownMenuTrigger>
        <DropdownMenuContent align="start" class="max-h-72 w-56 overflow-y-auto">
            <DropdownMenuItem
                v-for="f in TITLE_FONTS"
                :key="f.name"
                class="flex items-center justify-between gap-2"
                @select="emit('update:modelValue', f.name)"
            >
                <span class="text-base" :style="{ fontFamily: f.stack }">{{ f.name }}</span>
                <Check v-if="f.name === modelValue" class="text-primary size-4 shrink-0" />
            </DropdownMenuItem>
        </DropdownMenuContent>
    </DropdownMenu>
</template>
