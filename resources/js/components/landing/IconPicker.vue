<script setup lang="ts">
import { DropdownMenu, DropdownMenuContent, DropdownMenuItem, DropdownMenuTrigger } from '@/components/ui/dropdown-menu';
import { Droplet, Filter, FlaskConical, ShieldCheck, Sparkles, Sun, Thermometer, Waves, Wrench } from 'lucide-vue-next';
import { computed, type Component } from 'vue';

const props = defineProps<{ modelValue?: string }>();
const emit = defineEmits<{ 'update:modelValue': [value: string] }>();

// Same keys + glyphs the ServicesSection uses to render these on the live page.
const ICONS: { key: string; label: string; icon: Component }[] = [
    { key: 'droplet', label: 'Droplet', icon: Droplet },
    { key: 'wrench', label: 'Wrench', icon: Wrench },
    { key: 'sparkles', label: 'Sparkles', icon: Sparkles },
    { key: 'shield', label: 'Shield', icon: ShieldCheck },
    { key: 'waves', label: 'Waves', icon: Waves },
    { key: 'sun', label: 'Sun', icon: Sun },
    { key: 'flask', label: 'Chemistry', icon: FlaskConical },
    { key: 'thermometer', label: 'Heater', icon: Thermometer },
    { key: 'filter', label: 'Filter', icon: Filter },
];

const currentIcon = computed(() => ICONS.find((i) => i.key === props.modelValue)?.icon ?? Droplet);
</script>

<template>
    <DropdownMenu>
        <DropdownMenuTrigger as-child>
            <button
                type="button"
                title="Choose an icon"
                aria-label="Choose an icon"
                class="flex size-9 shrink-0 items-center justify-center rounded-md border border-input bg-background text-primary transition-colors hover:bg-accent hover:text-accent-foreground focus:outline-none focus-visible:ring-2 focus-visible:ring-ring"
            >
                <component :is="currentIcon" class="size-4" />
            </button>
        </DropdownMenuTrigger>
        <DropdownMenuContent align="start" class="grid grid-cols-3 gap-1 p-1.5">
            <DropdownMenuItem
                v-for="ic in ICONS"
                :key="ic.key"
                :title="ic.label"
                class="flex size-9 items-center justify-center rounded-md p-0"
                :class="ic.key === (modelValue || 'droplet') ? 'bg-accent text-primary ring-1 ring-primary' : 'text-foreground'"
                @select="emit('update:modelValue', ic.key)"
            >
                <component :is="ic.icon" class="size-4" />
            </DropdownMenuItem>
        </DropdownMenuContent>
    </DropdownMenu>
</template>
