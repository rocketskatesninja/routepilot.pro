<script setup lang="ts">
import { DropdownMenu, DropdownMenuContent, DropdownMenuItem, DropdownMenuTrigger } from '@/components/ui/dropdown-menu';
import { Droplet } from 'lucide-vue-next';
import { computed } from 'vue';
import { LANDING_ICONS, landingIcon } from './icons';

const props = defineProps<{ modelValue?: string }>();
const emit = defineEmits<{ 'update:modelValue': [value: string] }>();

// A service's icon in "What we do" (same list the page renders from); unset = droplet.
const currentIcon = computed(() => landingIcon(props.modelValue) ?? Droplet);
</script>

<template>
    <DropdownMenu>
        <DropdownMenuTrigger as-child>
            <button
                type="button"
                title="Choose an icon"
                aria-label="Choose an icon"
                class="border-input bg-background text-primary hover:bg-accent hover:text-accent-foreground focus-visible:ring-ring flex size-9 shrink-0 items-center justify-center rounded-md border transition-colors focus:outline-none focus-visible:ring-2"
            >
                <component :is="currentIcon" class="size-4" />
            </button>
        </DropdownMenuTrigger>
        <DropdownMenuContent align="start" class="grid grid-cols-5 gap-1 p-1.5">
            <DropdownMenuItem
                v-for="ic in LANDING_ICONS"
                :key="ic.key"
                :title="ic.label"
                class="flex size-9 items-center justify-center rounded-md p-0"
                :class="ic.key === (modelValue || 'droplet') ? 'bg-accent text-primary ring-primary ring-1' : 'text-foreground'"
                @select="emit('update:modelValue', ic.key)"
            >
                <component :is="ic.icon" class="size-4" />
            </DropdownMenuItem>
        </DropdownMenuContent>
    </DropdownMenu>
</template>
